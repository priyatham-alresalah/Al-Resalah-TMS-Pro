<?php
/**
 * Upload LPO / Client Order
 * Links LPO to approved quotation
 */
require '../../includes/config.php';
require '../../includes/api_middleware.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';
require '../../includes/branch.php';
require '../../includes/file_upload.php';

// Initialize API middleware (rate limiting for write endpoints)
initAPIMiddleware('/client_orders/create');

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('client_orders', 'create');

$quotationId = trim($_POST['quotation_id'] ?? '');
$lpoNumber = trim($_POST['lpo_number'] ?? '');
$receivedDate = trim($_POST['received_date'] ?? date('Y-m-d'));

if (empty($quotationId) || empty($lpoNumber)) {
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('LPO number is required'));
  exit;
}

// Verify quotation exists and is approved
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$quotation = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=id,status",
    false,
    $ctx
  ),
  true
);

if (empty($quotation)) {
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('Quotation not found'));
  exit;
}

$quotation = $quotation[0];

if ($quotation['status'] !== 'approved' && $quotation['status'] !== 'accepted') {
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('LPO can only be uploaded for approved quotations'));
  exit;
}

// Branch isolation: Check if quotation belongs to user's branch
$branchId = getUserBranchId();
if ($branchId !== null) {
  $quotationBranch = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=branch_id",
      false,
      $ctx
    ),
    true
  );

  if (!empty($quotationBranch) && isset($quotationBranch[0]['branch_id']) && $quotationBranch[0]['branch_id'] !== $branchId) {
    header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('Cannot upload LPO: Quotation belongs to another branch'));
    exit;
  }
}

// Handle file upload if provided (with validation)
$lpoFilePath = null;
if (!empty($_FILES['lpo_file']['tmp_name'])) {
  $uploadValidation = validateFileUpload($_FILES['lpo_file']);
  
  if (!$uploadValidation['valid']) {
    header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode($uploadValidation['error']));
    exit;
  }
  
  $uploadDir = __DIR__ . '/../../uploads/lpos/';
  $moveResult = moveUploadedFileSafe($_FILES['lpo_file'], $uploadDir, $uploadValidation['safe_name']);
  
  if ($moveResult['success']) {
    $lpoFilePath = 'uploads/lpos/' . basename($moveResult['path']);
  } else {
    header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode($moveResult['error']));
    exit;
  }
}

$orderData = [
  'quotation_id' => $quotationId,
  'lpo_number' => $lpoNumber,
  'received_date' => $receivedDate,
  'status' => 'pending',
  'lpo_file_path' => $lpoFilePath
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $orderData['branch_id'] = $branchId;
}

$createCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($orderData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/client_orders",
  false,
  $createCtx
);

if ($response === false) {
  error_log("Failed to create client order for quotation: $quotationId");
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('Failed to upload LPO. Please try again.'));
  exit;
}

$order = json_decode($response, true);
$orderId = $order['id'] ?? null;

if ($orderId) {
  // Audit log
  auditLog('client_orders', 'create', $orderId, [
    'quotation_id' => $quotationId,
    'lpo_number' => $lpoNumber
  ]);
}

header('Location: ' . BASE_PATH . '/pages/client_orders.php?success=' . urlencode("LPO uploaded successfully: $lpoNumber"));
exit;

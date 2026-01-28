<?php
/**
 * Upload LPO / Client Order
 * Links LPO to approved quotation
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

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

// Handle file upload if provided
$lpoFilePath = null;
if (!empty($_FILES['lpo_file']['tmp_name'])) {
  $uploadDir = __DIR__ . '/../../uploads/lpos/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }
  
  $fileName = 'lpo_' . $quotationId . '_' . time() . '_' . basename($_FILES['lpo_file']['name']);
  $targetPath = $uploadDir . $fileName;
  
  if (move_uploaded_file($_FILES['lpo_file']['tmp_name'], $targetPath)) {
    $lpoFilePath = 'uploads/lpos/' . $fileName;
  }
}

$orderData = [
  'quotation_id' => $quotationId,
  'lpo_number' => $lpoNumber,
  'received_date' => $receivedDate,
  'status' => 'pending',
  'lpo_file_path' => $lpoFilePath
];

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

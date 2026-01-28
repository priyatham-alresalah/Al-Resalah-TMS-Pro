<?php
/**
 * Create Quotation from Inquiry
 * Enforces workflow: Inquiry (new) → Quotation (draft/pending_approval)
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/workflow.php';
require '../../includes/audit_log.php';
require '../../includes/branch.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('quotations', 'create');

$inquiryId = trim($_POST['inquiry_id'] ?? '');
$subtotal = floatval($_POST['subtotal'] ?? 0);
$vat = floatval($_POST['vat'] ?? 0);
$discount = floatval($_POST['discount'] ?? 0);
$total = floatval($_POST['total'] ?? 0);
$status = trim($_POST['status'] ?? 'draft'); // draft or pending_approval

if (empty($inquiryId) || $subtotal <= 0) {
  header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . urlencode($inquiryId) . '&error=' . urlencode('Invalid quotation data'));
  exit;
}

// Validate workflow
$workflowCheck = canCreateQuotation($inquiryId);
if (!$workflowCheck['allowed']) {
  header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . urlencode($inquiryId) . '&error=' . urlencode($workflowCheck['reason']));
  exit;
}

// Validate status
$validStatuses = ['draft', 'pending_approval'];
if (!in_array($status, $validStatuses)) {
  $status = 'draft';
}

// Generate quotation number
$year = date('Y');
$quotationNo = 'QT-' . $year . '-' . strtoupper(substr(md5(uniqid()), 0, 8));

$userId = $_SESSION['user']['id'];
$userRole = getUserRole();

// BDO creates as draft, can submit for approval
// BDM can create directly as pending_approval
if ($userRole === 'bdm' && $status === 'draft') {
  $status = 'pending_approval';
}

// Get inquiry to determine branch
$inquiry = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=client_id",
    false,
    stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' =>
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE
      ]
    ])
  ),
  true
);

$clientId = !empty($inquiry) ? ($inquiry[0]['client_id'] ?? null) : null;
$branchId = getUserBranchId();

$quotationData = [
  'inquiry_id' => $inquiryId,
  'quotation_no' => $quotationNo,
  'subtotal' => $subtotal,
  'vat' => $vat,
  'discount' => $discount,
  'total' => $total,
  'status' => $status,
  'created_by' => $userId
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $quotationData['branch_id'] = $branchId;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($quotationData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/quotations",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to create quotation for inquiry: $inquiryId");
  header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . urlencode($inquiryId) . '&error=' . urlencode('Failed to create quotation. Please try again.'));
  exit;
}

$quotation = json_decode($response, true);
$quotationId = $quotation['id'] ?? null;

if ($quotationId) {
  // Update inquiry status to 'quoted'
  $updateCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['status' => 'quoted'])
    ]
  ]);

  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId",
    false,
    $updateCtx
  );

  // Audit log
  auditLog('quotations', 'create', $quotationId, [
    'inquiry_id' => $inquiryId,
    'quotation_no' => $quotationNo,
    'total' => $total,
    'status' => $status
  ]);
}

header('Location: ' . BASE_PATH . '/pages/quotations.php?success=' . urlencode("Quotation created successfully: $quotationNo"));
exit;

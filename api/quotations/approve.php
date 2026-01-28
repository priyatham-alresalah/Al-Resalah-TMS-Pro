<?php
/**
 * Approve/Reject Quotation
 * Only BDM can approve quotations
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

/* Permission Check - Only BDM can approve */
if (getUserRole() !== 'bdm' && !isAdmin()) {
  http_response_code(403);
  die('Access denied: Only BDM can approve quotations');
}

$quotationId = trim($_POST['quotation_id'] ?? '');
$action = trim($_POST['action'] ?? ''); // 'approve' or 'reject'

if (empty($quotationId) || !in_array($action, ['approve', 'reject'])) {
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode('Invalid request'));
  exit;
}

// Validate workflow
$workflowCheck = canApproveQuotation($quotationId);
if (!$workflowCheck['allowed'] && $action === 'approve') {
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

// Branch isolation: Check if quotation belongs to user's branch
$branchId = getUserBranchId();
if ($branchId !== null) {
  $quotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=branch_id",
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

  if (!empty($quotation) && isset($quotation[0]['branch_id']) && $quotation[0]['branch_id'] !== $branchId) {
    http_response_code(403);
    die('Access denied: Cannot approve quotation from another branch');
  }
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';
$userId = $_SESSION['user']['id'];

$updateData = [
  'status' => $newStatus,
  'approved_by' => $userId,
  'approved_at' => date('Y-m-d H:i:s')
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($updateData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to $action quotation: $quotationId");
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode("Failed to $action quotation. Please try again."));
  exit;
}

// Audit log
auditLog('quotations', $action, $quotationId, [
  'status' => $newStatus,
  'approved_by' => $userId
]);

$actionText = $action === 'approve' ? 'approved' : 'rejected';
header('Location: ' . BASE_PATH . '/pages/quotations.php?success=' . urlencode("Quotation $actionText successfully"));
exit;

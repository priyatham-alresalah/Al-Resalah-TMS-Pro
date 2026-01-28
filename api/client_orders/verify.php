<?php
/**
 * Verify/Reject LPO
 * Only authorized roles can verify LPOs
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('client_orders', 'update');

$orderId = trim($_POST['order_id'] ?? '');
$action = trim($_POST['action'] ?? ''); // 'verify' or 'reject'

if (empty($orderId) || !in_array($action, ['verify', 'reject'])) {
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode('Invalid request'));
  exit;
}

$userId = $_SESSION['user']['id'];
$newStatus = $action === 'verify' ? 'verified' : 'rejected';

$updateData = [
  'status' => $newStatus,
  'verified_by' => $userId
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
  SUPABASE_URL . "/rest/v1/client_orders?id=eq.$orderId",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to $action client order: $orderId");
  header('Location: ' . BASE_PATH . '/pages/client_orders.php?error=' . urlencode("Failed to $action LPO. Please try again."));
  exit;
}

// Audit log
auditLog('client_orders', $action, $orderId, [
  'status' => $newStatus,
  'verified_by' => $userId
]);

$actionText = $action === 'verify' ? 'verified' : 'rejected';
header('Location: ' . BASE_PATH . '/pages/client_orders.php?success=' . urlencode("LPO $actionText successfully"));
exit;

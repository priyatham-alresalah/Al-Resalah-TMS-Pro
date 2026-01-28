<?php
/**
 * Revoke Certificate
 * Logs revocation action
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('certificates', 'delete');

$id = trim($_POST['certificate_id'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if (empty($id)) {
  header('Location: ' . BASE_PATH . '/pages/certificates.php?error=' . urlencode('Certificate ID missing'));
  exit;
}

$userId = $_SESSION['user']['id'];

$data = [
  'status' => 'revoked',
  'revoked_at' => date('Y-m-d H:i:s'),
  'revoked_by' => $userId
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to revoke certificate: $id");
  header('Location: ' . BASE_PATH . '/pages/certificates.php?error=' . urlencode('Failed to revoke certificate. Please try again.'));
  exit;
}

// Log revocation
$logData = [
  'certificate_id' => $id,
  'action' => 'revoked',
  'reason' => $reason,
  'performed_by' => $userId
];

$logCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($logData)
  ]
]);

@file_get_contents(
  SUPABASE_URL . "/rest/v1/certificate_issuance_logs",
  false,
  $logCtx
);

// Audit log
auditLog('certificates', 'revoke', $id, [
  'reason' => $reason
]);

header('Location: ' . BASE_PATH . '/pages/certificates.php?success=' . urlencode('Certificate revoked successfully'));
exit;

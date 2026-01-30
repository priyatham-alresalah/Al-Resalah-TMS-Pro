<?php
/**
 * Regenerate Certificate PDF
 * Clears stored file_path so the next download/print generates a fresh PDF.
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('certificates', 'update');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/pages/certificates.php?error=' . urlencode('Invalid request'));
  exit;
}

$id = trim($_POST['certificate_id'] ?? '');

if (empty($id)) {
  header('Location: ' . BASE_PATH . '/pages/certificates.php?error=' . urlencode('Certificate ID missing'));
  exit;
}

$headers =
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

$certResponse = file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id&limit=1",
  false,
  $ctx
);

$certData = json_decode($certResponse, true);
$cert = $certData[0] ?? null;

if (!$cert) {
  header('Location: ' . BASE_PATH . '/pages/certificates.php?error=' . urlencode('Certificate not found'));
  exit;
}

// Delete existing PDF file if present
if (!empty($cert['file_path'])) {
  $baseDir = __DIR__ . '/../../uploads/certificates/';
  $filePath = $baseDir . basename($cert['file_path']);
  if (file_exists($filePath)) {
    @unlink($filePath);
  }
}

// Clear file_path so next download/print generates a fresh PDF
$patchCtx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['file_path' => null])
  ]
]);

$patchResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id",
  false,
  $patchCtx
);

auditLog('certificates', 'regenerate_pdf', $id, []);

header('Location: ' . BASE_PATH . '/pages/certificates.php?success=' . urlencode('Certificate PDF will be regenerated on next download or print.'));
exit;

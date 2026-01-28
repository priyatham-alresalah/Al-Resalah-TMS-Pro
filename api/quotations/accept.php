<?php
/**
 * Accept Quotation (Client Action)
 * Changes status from 'approved' to 'accepted'
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

$quotationId = trim($_POST['quotation_id'] ?? '');

if (empty($quotationId)) {
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode('Invalid request'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Check current status
$quotation = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=id,status",
    false,
    $ctx
  ),
  true
);

if (empty($quotation)) {
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode('Quotation not found'));
  exit;
}

$quotation = $quotation[0];

if ($quotation['status'] !== 'approved') {
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode('Only approved quotations can be accepted'));
  exit;
}

$updateData = [
  'status' => 'accepted'
];

$updateCtx = stream_context_create([
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
  $updateCtx
);

if ($response === false) {
  error_log("Failed to accept quotation: $quotationId");
  header('Location: ' . BASE_PATH . '/pages/quotations.php?error=' . urlencode('Failed to accept quotation. Please try again.'));
  exit;
}

// Audit log
auditLog('quotations', 'accept', $quotationId);

header('Location: ' . BASE_PATH . '/pages/quotations.php?success=' . urlencode('Quotation accepted successfully'));
exit;

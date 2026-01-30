<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';

/* CSRF Protection */
requireCSRF();

/* RBAC Check */
requirePermission('inquiries', 'delete');

$inquiryId = $_POST['inquiry_id'] ?? '';

if (empty($inquiryId)) {
  header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Inquiry ID is required'));
  exit;
}

// Check if inquiry exists and can be deleted (only if status is 'new')
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$inquiry = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=id,status",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$inquiry) {
  header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Inquiry not found'));
  exit;
}

// Only allow deletion of 'new' inquiries
$status = strtolower($inquiry['status'] ?? '');
if ($status !== 'new') {
  header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Only new inquiries can be deleted'));
  exit;
}

// Delete the inquiry
$deleteCtx = stream_context_create([
  'http' => [
    'method' => 'DELETE',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId",
  false,
  $deleteCtx
);

if ($response === false) {
  header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Failed to delete inquiry'));
  exit;
}

header('Location: ' . BASE_PATH . '/pages/inquiries.php?success=' . urlencode('Inquiry deleted successfully'));
exit;

<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  die('Access denied');
}

/* CSRF Protection */
requireCSRF();

/* Input Validation */
$id = trim($_POST['user_id'] ?? '');

if (empty($id)) {
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Invalid user ID'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['user_id' => $id])
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/auth/v1/admin/users/$id/recover",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to reset password for user: $id");
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Failed to send password reset email. Please try again.'));
  exit;
}

header('Location: ' . BASE_PATH . '/pages/users.php?success=' . urlencode('Password reset email sent successfully'));
exit;

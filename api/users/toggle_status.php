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
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

if (empty($id)) {
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Invalid user ID'));
  exit;
}

/* Prevent deactivating own account */
if ($id === $_SESSION['user']['id'] && $isActive === 0) {
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('You cannot deactivate your own account'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['is_active' => (bool)$isActive])
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to toggle user status: $id");
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Failed to update user status. Please try again.'));
  exit;
}

$statusText = $isActive ? 'activated' : 'deactivated';
header('Location: ' . BASE_PATH . '/pages/users.php?success=' . urlencode("User $statusText successfully"));
exit;

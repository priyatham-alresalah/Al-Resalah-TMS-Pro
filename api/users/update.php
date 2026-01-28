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
$id = trim($_POST['id'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');

if (empty($id) || empty($fullName) || empty($role)) {
  header('Location: ' . BASE_PATH . '/pages/user_edit.php?id=' . urlencode($id) . '&error=' . urlencode('All fields are required'));
  exit;
}

$validRoles = ['admin', 'bdm', 'bdo', 'coordinator', 'trainer', 'accounts'];
if (!in_array(strtolower($role), $validRoles)) {
  header('Location: ' . BASE_PATH . '/pages/user_edit.php?id=' . urlencode($id) . '&error=' . urlencode('Invalid role'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode([
      'full_name' => $fullName,
      'role' => strtolower($role)
    ])
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to update user: $id");
  header('Location: ' . BASE_PATH . '/pages/user_edit.php?id=' . urlencode($id) . '&error=' . urlencode('Failed to update user. Please try again.'));
  exit;
}

// If updating own account and role/is_active changed, force logout
if ($id === $_SESSION['user']['id']) {
  $oldRole = $_SESSION['user']['role'] ?? null;
  $newRole = strtolower($role);
  if ($oldRole !== $newRole) {
    // Role changed - destroy session to force re-login
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: ' . BASE_PATH . '/?error=session_invalidated');
    exit;
  }
}

header('Location: ' . BASE_PATH . '/pages/users.php?success=' . urlencode('User updated successfully'));
exit;

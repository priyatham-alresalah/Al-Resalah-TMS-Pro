<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  die('Access denied');
}

requireCSRF();

$type = $_POST['type'] ?? '';
$email = trim($_POST['email'] ?? '');

if (!in_array($type, ['client', 'candidate']) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/pages/users.php?tab=' . ($type ? $type . 's' : 'staff') . '&error=' . urlencode('Invalid request'));
  exit;
}

/* Use same redirect as main reset - set_password.php */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirectTo = $scheme . '://' . $host . BASE_PATH . '/set_password.php';

$payload = json_encode([
  'email' => $email,
  'redirect_to' => $redirectTo
]);

$ctx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  => "Content-Type: application/json\r\napikey: " . SUPABASE_ANON,
    'content' => $payload
  ]
]);

@file_get_contents(SUPABASE_URL . '/auth/v1/recover', false, $ctx);

/* Always show success for security (don't reveal if email exists) */
$label = $type === 'client' ? 'Client' : 'Candidate';
header('Location: ' . BASE_PATH . '/pages/users.php?tab=' . $type . 's&success=' . urlencode("If that $label email is registered with Supabase Auth, a reset link was sent."));
exit;

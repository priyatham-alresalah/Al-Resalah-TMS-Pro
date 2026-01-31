<?php
require '../../includes/config.php';

/* =========================
   POST ONLY
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

/* =========================
   CSRF
========================= */
$token = $_POST['csrf'] ?? '';
if ($token === '' || empty($_SESSION['reset_csrf']) || !hash_equals($_SESSION['reset_csrf'], $token)) {
  header('Location: ' . BASE_PATH . '/index.php?reset_error=1');
  exit;
}
unset($_SESSION['reset_csrf']);

/* =========================
   VALIDATE EMAIL
========================= */
$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/index.php?reset_error=1');
  exit;
}

/* =========================
   REDIRECT URL (set new password page)
========================= */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirectTo = $scheme . '://' . $host . BASE_PATH . '/set_password.php';

/* =========================
   SUPABASE RECOVER
========================= */
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

$response = @file_get_contents(
  SUPABASE_URL . '/auth/v1/recover',
  false,
  $ctx
);

/* =========================
   CHECK RESPONSE (use generic message for security)
========================= */
$ok = false;
if (isset($http_response_header[0]) && preg_match('/^HTTP\/\d\.\d\s+2\d\d\b/', $http_response_header[0])) {
  $ok = true;
}
if ($response !== false) {
  $data = @json_decode($response, true);
  if (isset($data['error']) || isset($data['error_description'])) {
    $ok = false;
  }
}

if ($ok) {
  header('Location: ' . BASE_PATH . '/index.php?reset_sent=1');
} else {
  header('Location: ' . BASE_PATH . '/index.php?reset_error=1');
}
exit;

<?php
require '../../includes/config.php';

/* =========================
   POST ONLY
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/set_password.php?set_password_error=1');
  exit;
}

$accessToken = trim($_POST['access_token'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($accessToken === '' || $password === '' || $password !== $passwordConfirm) {
  header('Location: ' . BASE_PATH . '/set_password.php?set_password_error=1');
  exit;
}

if (strlen($password) < 6) {
  header('Location: ' . BASE_PATH . '/set_password.php?set_password_error=1');
  exit;
}

/* =========================
   SUPABASE UPDATE USER (password)
========================= */
$payload = json_encode(['password' => $password]);
$ctx = stream_context_create([
  'http' => [
    'method'  => 'PUT',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_ANON . "\r\n" .
      "Authorization: Bearer " . $accessToken,
    'content' => $payload
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . '/auth/v1/user',
  false,
  $ctx
);

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
  header('Location: ' . BASE_PATH . '/set_password.php?password_updated=1');
} else {
  header('Location: ' . BASE_PATH . '/set_password.php?set_password_error=1');
}
exit;

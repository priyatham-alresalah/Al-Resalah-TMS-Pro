<?php
require '../../includes/config.php';
require '../../includes/api_middleware.php';

// Initialize API middleware (rate limiting for auth endpoints)
initAPIMiddleware('/auth/login');

/* =========================
   ALLOW POST ONLY
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

/* =========================
   INPUTS
========================= */
$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
  header('Location: ' . BASE_PATH . '/index.php?error=invalid');
  exit;
}

/* =========================
   SUPABASE LOGIN (ANON KEY)
========================= */
$payload = json_encode([
  'email'    => $email,
  'password' => $password
]);

$ctx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_ANON . "\r\n",
    'content' => $payload
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . '/auth/v1/token?grant_type=password',
  false,
  $ctx
);

if ($response === false) {
  header('Location: ' . BASE_PATH . '/index.php?error=invalid');
  exit;
}

$data = json_decode($response, true);

/* =========================
   INVALID LOGIN
========================= */
if (!isset($data['access_token'], $data['user']['id'])) {
  header('Location: ' . BASE_PATH . '/index.php?error=invalid');
  exit;
}

/* =========================
   FETCH USER PROFILE
========================= */
$profileCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$profileResponse = @file_get_contents(
  SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $data['user']['id'] . '&select=*',
  false,
  $profileCtx
);

$profile = json_decode($profileResponse, true)[0] ?? null;

/* =========================
   PROFILE VALIDATION
========================= */
if (!$profile || !$profile['is_active']) {
  header('Location: ' . BASE_PATH . '/index.php?error=inactive');
  exit;
}

/* =========================
   CREATE SESSION
========================= */
$_SESSION['user'] = [
  'id'    => $data['user']['id'],
  'email' => $email,
  'role'  => $profile['role'],
  'name'  => $profile['full_name']
];

/* =========================
   SESSION SECURITY
========================= */
$_SESSION['last_activity'] = time();
$_SESSION['last_status_check'] = time();

/* =========================
   REGENERATE SESSION ID (Security)
========================= */
session_regenerate_id(true);

/* =========================
   SUCCESS
========================= */
header('Location: ' . BASE_PATH . '/pages/dashboard.php');
exit;

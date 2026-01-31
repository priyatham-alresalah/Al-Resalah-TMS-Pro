<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

try {
  require '../../includes/config.php';
} catch (Throwable $e) {
  error_log("Config error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  http_response_code(500);
  die(json_encode(['error' => 'Configuration error']));
}

try {
  require '../../includes/api_middleware.php';
} catch (Throwable $e) {
  error_log("Middleware error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  http_response_code(500);
  die(json_encode(['error' => 'Middleware error']));
}

/* =========================
   ALLOW POST ONLY
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  try {
    header('Location: ' . BASE_PATH . '/index.php');
  } catch (Throwable $e) {
    error_log("Redirect error: " . $e->getMessage());
    http_response_code(500);
    die('Redirect failed');
  }
  exit;
}

// Initialize API middleware (rate limiting for auth endpoints)
try {
  initAPIMiddleware('/auth/login');
} catch (Throwable $e) {
  error_log("Init middleware error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  // Don't die here - allow login to proceed even if rate limiting fails
}

// Wrap entire login logic in try-catch for error handling
try {

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
   AUTO-CREATE PROFILE IF MISSING
   Users added in Supabase Auth (e.g. dashboard) may not have a profiles row.
========================= */
if (!$profile) {
  $authUser = $data['user'];
  $userId = $authUser['id'];
  $userMeta = $authUser['user_metadata'] ?? $authUser['raw_user_meta_data'] ?? [];
  $fullName = $userMeta['full_name'] ?? '';
  if (empty($fullName) && !empty($email)) {
    $fullName = explode('@', $email)[0];
  }
  $fullName = $fullName ?: 'User';
  $role = $userMeta['role'] ?? 'user';

  $createCtx = stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
        "Prefer: return=minimal",
      'content' => json_encode([
        'id'        => $userId,
        'full_name' => $fullName,
        'email'     => $email,
        'role'      => $role,
        'is_active' => true
      ])
    ]
  ]);

  $createResp = @file_get_contents(
    SUPABASE_URL . '/rest/v1/profiles',
    false,
    $createCtx
  );

  if ($createResp !== false && isset($http_response_header) && preg_match('/^HTTP\/\d\.\d\s+2\d\d\b/', $http_response_header[0] ?? '')) {
    $profile = [
      'id'        => $userId,
      'full_name' => $fullName,
      'email'     => $email,
      'role'      => $role,
      'is_active' => true
    ];
  }
}

/* =========================
   PROFILE VALIDATION
   Block only when is_active is explicitly false (treat null/missing as active)
========================= */
if (!$profile) {
  header('Location: ' . BASE_PATH . '/index.php?error=inactive');
  exit;
}
$isInactive = isset($profile['is_active']) && in_array($profile['is_active'], [false, 0, 'f'], true);
if ($isInactive) {
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

} catch (Throwable $e) {
  // Log the error
  error_log("Login error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  
  // Redirect to login with error
  header('Location: ' . BASE_PATH . '/index.php?error=system');
  exit;
}

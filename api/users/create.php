<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

/* ADMIN ONLY */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';
$role      = $_POST['role'] ?? '';

if (!$full_name || !$email || !$password || !$role) {
  die('Missing required fields');
}

/* ================================
   CREATE USER IN SUPABASE AUTH
================================ */
$authPayload = [
  'email' => $email,
  'password' => $password,
  'email_confirm' => true,
  'user_metadata' => [
    'full_name' => $full_name,
    'role' => $role
  ]
];

$authCtx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($authPayload)
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/auth/v1/admin/users",
  false,
  $authCtx
);

if ($response === false) {
  die('Failed to create auth user');
}

$user = json_decode($response, true);
$user_id = $user['id'];

/* ================================
   INSERT INTO PROFILES TABLE
================================ */
$profilePayload = [
  'id' => $user_id,
  'full_name' => $full_name,
  'role' => $role,
  'is_active' => true
];

$profileCtx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($profilePayload)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles",
  false,
  $profileCtx
);

/* ================================
   AUDIT LOG
================================ */
$auditPayload = [
  'user_id' => $_SESSION['user']['id'],
  'action'  => 'create_user',
  'module'  => 'users',
  'record_id' => $user_id
];

file_get_contents(
  SUPABASE_URL . "/rest/v1/audit_logs",
  false,
  stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($auditPayload)
    ]
  ])
);

header('Location: ../../users.php');
exit;

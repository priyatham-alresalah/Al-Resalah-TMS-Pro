<?php
require '../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /training-management-system/");
  exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  die("Email and password required");
}

/* ---------- SUPABASE LOGIN ---------- */
$payload = json_encode([
  "email" => $email,
  "password" => $password
]);

$loginCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_ANON,
    'content' => $payload,
    'ignore_errors' => true
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/auth/v1/token?grant_type=password",
  false,
  $loginCtx
);

$data = json_decode($response, true);

if (!isset($data['user'])) {
  die("Invalid login credentials");
}

/* ---------- FETCH USER ROLE ---------- */
$profileCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$profileResponse = file_get_contents(
  SUPABASE_URL .
  "/rest/v1/profiles?id=eq." . $data['user']['id'] .
  "&select=full_name,role,is_active",
  false,
  $profileCtx
);

$profile = json_decode($profileResponse, true);

if (!$profile || !$profile[0]['is_active']) {
  die("User is inactive");
}

/* ---------- SESSION ---------- */
$_SESSION['user'] = [
  'id'    => $data['user']['id'],
  'email' => $data['user']['email'],
  'name'  => $profile[0]['full_name'],
  'role'  => $profile[0]['role']
];

/* ---------- REDIRECT ---------- */
header("Location: /training-management-system/dashboard.php");
exit;

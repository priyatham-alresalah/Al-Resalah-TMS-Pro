<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';

if ($full_name === '' || $email === '' || $role === '') {
  die('All fields required');
}

/* ---------- CREATE USER (SUPABASE INVITE) ---------- */
$payload = json_encode([
  "email" => $email,
  "email_confirm" => true
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $payload,
    'ignore_errors' => true
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/auth/v1/admin/users",
  false,
  $ctx
);

$data = json_decode($response, true);

if (!isset($data['id'])) {
  die('Failed to create user');
}

$user_id = $data['id'];

/* ---------- INSERT PROFILE ---------- */
$profilePayload = json_encode([
  'id' => $user_id,
  'full_name' => $full_name,
  'role' => $role,
  'is_active' => true
]);

$profileCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $profilePayload
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles",
  false,
  $profileCtx
);

/* ---------- DONE ---------- */
header("Location: /training-management-system/users.php");
exit;

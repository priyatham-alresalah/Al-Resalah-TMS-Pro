<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = json_encode([
  'email' => $_POST['email'],
  'password' => $_POST['password'],
  'user_metadata' => [
    'full_name' => $_POST['full_name'],
    'role' => $_POST['role']
  ]
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

$response = file_get_contents(SUPABASE_URL . "/auth/v1/admin/users", false, $ctx);
$userData = json_decode($response, true);
$userId = $userData['id'] ?? null;

/* Update profiles table with email */
if ($userId) {
  $updateCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['email' => $_POST['email']])
    ]
  ]);
  
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId",
    false,
    $updateCtx
  );
}

header('Location: ../../pages/users.php');

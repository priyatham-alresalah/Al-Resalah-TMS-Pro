<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['user_id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['user_id' => $id])
  ]
]);

file_get_contents(
  SUPABASE_URL . "/auth/v1/admin/users/$id/recover",
  false,
  $ctx
);

header('Location: ../../pages/users.php');

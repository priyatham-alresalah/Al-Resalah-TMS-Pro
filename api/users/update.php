<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode([
      'full_name' => $_POST['full_name'],
      'role' => $_POST['role']
    ])
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
  false,
  $ctx
);

header('Location: ../../pages/users.php');

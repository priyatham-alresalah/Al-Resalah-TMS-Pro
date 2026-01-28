<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['id'];

$data = json_encode([
  'full_name' => trim($_POST['full_name']),
  'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
  'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
  'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates?id=eq.$id",
  false,
  $ctx
);

header("Location: ../../pages/candidates.php");
exit;

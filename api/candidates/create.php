<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = json_encode([
  'client_id' => $_POST['client_id'],
  'full_name' => trim($_POST['full_name']),
  'email' => $_POST['email'] ?: null,
  'phone' => $_POST['phone'] ?: null
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

file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates",
  false,
  $ctx
);

header("Location: ../../candidates.php");
exit;

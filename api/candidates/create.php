<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = json_encode([
  'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
  'full_name' => trim($_POST['full_name']),
  'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
  'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null
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

header("Location: ../../pages/candidates.php");
exit;

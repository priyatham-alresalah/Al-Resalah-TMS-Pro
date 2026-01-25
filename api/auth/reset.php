<?php
require '../../includes/config.php';

$email = $_POST['email'];

$payload = json_encode([
  "email" => $email
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\napikey: ".SUPABASE_ANON,
    'content' => $payload
  ]
]);

file_get_contents(
  SUPABASE_URL . "/auth/v1/recover",
  false,
  $ctx
);

echo "Password reset link sent to your email.";

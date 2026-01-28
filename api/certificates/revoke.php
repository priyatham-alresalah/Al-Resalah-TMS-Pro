<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['certificate_id'] ?? null;
if (!$id) die('Certificate ID missing');

$data = json_encode([
  'status'     => 'revoked',
  'revoked_at' => date('c'),
  'revoked_by' => $_SESSION['user']['id']
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
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id",
  false,
  $ctx
);

header('Location: ../../pages/certificates.php');
exit;

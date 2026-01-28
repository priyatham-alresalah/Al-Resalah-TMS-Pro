<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_GET['id'] ?? null;
if (!$id) die("Certificate ID missing");

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$cert = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?id=eq.$id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$cert || empty($cert['file_path'])) {
  die("Certificate file not found");
}

$path = __DIR__ . "/../../uploads/certificates/" . basename($cert['file_path']);

if (!file_exists($path)) {
  die("File missing");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cert['file_path']) . '"');
readfile($path);
exit;

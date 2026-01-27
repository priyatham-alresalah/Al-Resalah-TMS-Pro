<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$baseUrl = SUPABASE_URL . "/rest/v1/clients?order=created_at.desc";

if ($role !== 'admin') {
  $baseUrl .= "&created_by=eq.$userId";
}

$response = file_get_contents($baseUrl, false, $ctx);

echo $response;

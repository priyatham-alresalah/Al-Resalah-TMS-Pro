<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$role = $_SESSION['user']['role'];

if (!in_array($role, ['admin','bdm','bdo','accounts'])) {
  die('Access denied');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?order=created_at.desc",
  false,
  $ctx
);

echo $response;

<?php
require 'includes/config.php';
require 'includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
    die('Access denied');
}

$id = $_GET['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$user = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
    false,
    $ctx
  ),
  true
)[0];

$newStatus = !$user['is_active'];

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
  false,
  stream_context_create([
    'http' => [
      'method'  => 'PATCH',
      'header'  =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['is_active' => $newStatus])
    ]
  ])
);

header("Location: users.php");
exit;

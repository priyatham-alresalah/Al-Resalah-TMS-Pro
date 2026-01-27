<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
    die('Unauthorized');
}

$id = $_POST['id'];

$payload = [
  'full_name' => $_POST['full_name'],
  'role'      => $_POST['role']
];

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
      'content' => json_encode($payload)
    ]
  ])
);

header("Location: ../../users.php");
exit;

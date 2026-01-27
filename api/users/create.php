<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
    die('Unauthorized');
}

$data = [
  'email'    => $_POST['email'],
  'password' => $_POST['password']
];

/* Create Auth User */
$ctx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = json_decode(
  file_get_contents(SUPABASE_URL . "/auth/v1/admin/users", false, $ctx),
  true
);

$userId = $response['id'];

/* Insert profile */
$profile = [
  'id'        => $userId,
  'full_name' => $_POST['full_name'],
  'role'      => $_POST['role']
];

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles",
  false,
  stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($profile)
    ]
  ])
);

header("Location: ../../users.php");
exit;

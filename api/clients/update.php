<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

/* Admin + Accounts can edit */
if (!in_array($_SESSION['user']['role'], ['admin','accounts'])) {
  die('Access denied');
}

$id = $_POST['id'] ?? '';

$data = [
  'company_name'   => trim($_POST['company_name'] ?? ''),
  'contact_person' => trim($_POST['contact_person'] ?? ''),
  'email'          => trim($_POST['email'] ?? ''),
  'phone'          => trim($_POST['phone'] ?? ''),
  'address'        => trim($_POST['address'] ?? '')
];

if ($id === '' || $data['company_name'] === '') {
  die('Invalid request');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?id=eq." . $id,
  false,
  $ctx
);

header("Location: /training-management-system/clients.php");
exit;

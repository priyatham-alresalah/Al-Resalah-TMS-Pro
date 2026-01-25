<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$role = $_SESSION['user']['role'];

/* Admin + Accounts + BDM + BDO can create */
if (!in_array($role, ['admin','accounts','bdm','bdo'])) {
  die('Access denied');
}

$data = [
  'company_name'   => trim($_POST['company_name'] ?? ''),
  'contact_person' => trim($_POST['contact_person'] ?? ''),
  'email'          => trim($_POST['email'] ?? ''),
  'phone'          => trim($_POST['phone'] ?? ''),
  'address'        => trim($_POST['address'] ?? ''),
  'created_by'     => $_SESSION['user']['id']
];

if ($data['company_name'] === '') {
  die('Company name is required');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/clients",
  false,
  $ctx
);

header("Location: /training-management-system/clients.php");
exit;

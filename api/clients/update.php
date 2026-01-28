<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

$id = $_POST['id'] ?? '';
if ($id === '') {
  die('Invalid request');
}

$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'];

/* Fetch client to check ownership */
$checkCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$existing = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.$id&select=id,created_by",
    false,
    $checkCtx
  ),
  true
);

if (empty($existing[0])) {
  die('Client not found');
}

$ownerId = $existing[0]['created_by'] ?? null;

/* Only admin or creator can edit */
if ($role !== 'admin' && $ownerId !== $userId) {
  die('Access denied');
}

$data = [
  'company_name'   => trim($_POST['company_name'] ?? ''),
  'contact_person' => trim($_POST['contact_person'] ?? ''),
  'email'          => trim($_POST['email'] ?? ''),
  'phone'          => trim($_POST['phone'] ?? ''),
  'address'        => trim($_POST['address'] ?? '')
];

if ($data['company_name'] === '') {
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

header("Location: " . BASE_PATH . "/pages/clients.php");
exit;
?>

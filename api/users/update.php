<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$id = $_POST['id'] ?? '';
$role = $_POST['role'] ?? '';
$is_active = $_POST['is_active'] ?? '';

if ($id === '' || $role === '' || $is_active === '') {
  die('Invalid request');
}

$payload = json_encode([
  'role' => $role,
  'is_active' => (bool)$is_active
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $payload
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq." . $id,
  false,
  $ctx
);

header("Location: /training-management-system/users.php");
exit;

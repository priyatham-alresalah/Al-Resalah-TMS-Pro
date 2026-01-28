<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if (!in_array($_SESSION['user']['role'], ['admin','accounts'])) {
  die('Access denied');
}

$id = $_POST['id'] ?? '';

$data = [
  'course_name' => trim($_POST['course_name'] ?? ''),
  'status'      => $_POST['status'] ?? 'new'
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: ".SUPABASE_SERVICE."\r\n" .
      "Authorization: Bearer ".SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL."/rest/v1/inquiries?id=eq.$id",
  false,
  $ctx
);

header("Location: /training-management-system/pages/inquiries.php");
exit;

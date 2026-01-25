<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = [
  'client_id'   => $_POST['client_id'],
  'course_name' => $_POST['course_name'],
  'status'      => 'NEW'
];

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

file_get_contents(SUPABASE_URL . "/rest/v1/inquiries", false, $ctx);

header('Location: ../../inquiries.php');
exit;

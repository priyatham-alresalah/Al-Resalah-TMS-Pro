<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = [
  'course_name' => $_POST['course_name'],
  'duration'    => $_POST['duration'],
  'is_active'   => true
];

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

file_get_contents(
  SUPABASE_URL . "/rest/v1/training_master",
  false,
  $ctx
);

header('Location: ' . BASE_PATH . '/pages/training_master.php');
exit;

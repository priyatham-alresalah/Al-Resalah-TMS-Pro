<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['id'];

$data = [
  'course_name' => $_POST['course_name'],
  'duration'    => $_POST['duration'],
  'is_active'   => $_POST['is_active'] === 'true'
];

$ctx = stream_context_create([
  'http' => [
    'method'  => 'PATCH',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/training_master?id=eq.$id",
  false,
  $ctx
);

header('Location: /training_master.php');

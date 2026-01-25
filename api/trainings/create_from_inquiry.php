<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = [
  'inquiry_id'   => $_POST['inquiry_id'],
  'client_id'    => $_POST['client_id'],
  'course_name'  => $_POST['course_name'],
  'trainer_id'   => $_POST['trainer_id'] ?: null,
  'training_date'=> $_POST['training_date'],
  'status'       => 'SCHEDULED'
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

file_get_contents(SUPABASE_URL . "/rest/v1/trainings", false, $ctx);

// redirect
header('Location: ../../trainings.php');
exit;

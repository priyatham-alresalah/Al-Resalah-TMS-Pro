<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = json_encode([
  'inquiry_id' => $_POST['inquiry_id'],
  'client_id' => $_POST['client_id'],
  'trainer_id' => $_POST['trainer_id'] ?: null,
  'training_date' => $_POST['training_date'],
  'status' => 'scheduled'
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

file_get_contents(SUPABASE_URL . '/rest/v1/trainings', false, $ctx);

/* CLOSE INQUIRY */
$closeCtx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['status' => 'closed'])
  ]
]);

file_get_contents(
  SUPABASE_URL . '/rest/v1/inquiries?id=eq.' . $_POST['inquiry_id'],
  false,
  $closeCtx
);

header('Location: ../../trainings.php');

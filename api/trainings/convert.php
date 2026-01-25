<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if (!in_array($_SESSION['user']['role'], ['admin','accounts','coordinator'])) {
  die('Access denied');
}

$inquiry_id = $_POST['inquiry_id'] ?? '';

if ($inquiry_id === '') {
  die('Invalid inquiry');
}

/* Check if already converted */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE
  ]
]);

$existing = json_decode(
  file_get_contents(
    SUPABASE_URL."/rest/v1/trainings?inquiry_id=eq.$inquiry_id",
    false,
    $ctx
  ),
  true
);

if (!empty($existing)) {
  die('Inquiry already converted to training');
}

/* Fetch inquiry */
$inq = json_decode(
  file_get_contents(
    SUPABASE_URL."/rest/v1/inquiries?id=eq.$inquiry_id",
    false,
    $ctx
  ),
  true
)[0];

/* Create training */
$data = [
  'inquiry_id'   => $inquiry_id,
  'client_id'    => $inq['client_id'],
  'status'       => 'scheduled'
];

$ctx_create = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n".
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = file_get_contents(
  SUPABASE_URL."/rest/v1/trainings",
  false,
  $ctx_create
);

/* Update inquiry status */
$ctx_update = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n".
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE,
    'content' => json_encode(['status' => 'converted'])
  ]
]);

file_get_contents(
  SUPABASE_URL."/rest/v1/inquiries?id=eq.$inquiry_id",
  false,
  $ctx_update
);

/* Get created training */
$newTraining = json_decode($response, true)[0];

header("Location: /training-management-system/training_edit.php?id=".$newTraining['id']);
exit;

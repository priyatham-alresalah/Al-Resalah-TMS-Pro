<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

$training_id  = $_POST['training_id'] ?? null;
$candidate_id = $_POST['candidate_id'] ?? null;

if (!$training_id || !$candidate_id) {
  die('Missing data');
}

$ctx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode([
      'training_id'  => $training_id,
      'candidate_id' => $candidate_id
    ])
  ]
]);

/* INSERT */
$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/training_candidates",
  false,
  $ctx
);

/* Supabase returns empty body on success */
header("Location: ../../training_candidates.php?training_id=$training_id");
exit;

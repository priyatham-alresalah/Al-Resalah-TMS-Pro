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
    'method' => 'DELETE',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

file_get_contents(
  SUPABASE_URL .
  "/rest/v1/training_candidates?training_id=eq.$training_id&candidate_id=eq.$candidate_id",
  false,
  $ctx
);

header("Location: ../../pages/training_candidates.php?training_id=$training_id");
exit;

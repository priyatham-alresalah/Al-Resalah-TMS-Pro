<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$training_id = $_GET['training_id'] ?? '';

if (empty($training_id)) {
  echo json_encode([]);
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$assigned = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
) ?: [];

$candidateIds = array_column($assigned, 'candidate_id');

header('Content-Type: application/json');
echo json_encode($candidateIds);
exit;

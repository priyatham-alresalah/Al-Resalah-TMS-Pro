<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$training_id = $_POST['training_id'];
$candidates = $_POST['candidates'] ?? [];

$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

// 1️⃣ Remove old assignments
$ctx_delete = stream_context_create([
  'http' => [
    'method' => 'DELETE',
    'header' => $headers
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id",
  false,
  $ctx_delete
);

// 2️⃣ Insert new ones
foreach ($candidates as $cid) {
  $ctx_insert = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => $headers,
      'content' => json_encode([
        'training_id' => $training_id,
        'candidate_id' => $cid
      ])
    ]
  ]);

  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates",
    false,
    $ctx_insert
  );
}

header("Location: ../../trainings.php");
exit;

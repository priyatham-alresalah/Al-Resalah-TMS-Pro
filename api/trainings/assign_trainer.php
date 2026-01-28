<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$trainingId = $_POST['training_id'] ?? '';
$trainerId = $_POST['trainer_id'] ?? null;

if (!$trainingId) {
  die('Training ID missing');
}

$data = [
  'trainer_id' => $trainerId ?: null
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings?id=eq.$trainingId",
  false,
  $ctx
);

header('Location: ../../pages/trainings.php?success=' . urlencode('Trainer assigned successfully'));
exit;

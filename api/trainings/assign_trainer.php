<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('trainings', 'update');

$trainingId = trim($_POST['training_id'] ?? '');
$trainerId = $_POST['trainer_id'] ?? null;

if (empty($trainingId)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Training ID missing'));
  exit;
}

// Get current trainer for audit log
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$currentTraining = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$trainingId&select=trainer_id",
    false,
    $ctx
  ),
  true
);

$oldTrainerId = !empty($currentTraining) ? ($currentTraining[0]['trainer_id'] ?? null) : null;

$data = [
  'trainer_id' => $trainerId ?: null
];

$updateResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings?id=eq.$trainingId",
  false,
  stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($data)
    ]
  ])
);

if ($updateResponse === false) {
  error_log("Failed to assign trainer to training: $trainingId");
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Failed to assign trainer. Please try again.'));
  exit;
}

// Audit log
auditLog('trainings', 'assign_trainer', $trainingId, [
  'old_trainer_id' => $oldTrainerId,
  'new_trainer_id' => $trainerId
]);

header('Location: ' . BASE_PATH . '/pages/trainings.php?success=' . urlencode('Trainer assigned successfully'));
exit;

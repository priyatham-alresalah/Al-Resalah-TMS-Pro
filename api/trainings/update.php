<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/workflow.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('trainings', 'update');

$id = trim($_POST['id'] ?? '');
$newStatus = trim($_POST['status'] ?? '');
$newDate = $_POST['training_date'] ?? null;
$trainer = $_POST['trainer_id'] ?? null;

if (empty($id) || empty($newStatus)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Invalid request'));
  exit;
}

/* Supabase context */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch existing training */
$current = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$id&select=id,status",
    false,
    $ctx
  ),
  true
);

if (empty($current)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Training not found'));
  exit;
}

$current = $current[0];

// Enforce checkpoint requirement for completion
if ($current['status'] !== 'completed' && $newStatus === 'completed') {
  $completionCheck = canCompleteTraining($id);
  if (!$completionCheck['allowed']) {
    header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode($completionCheck['reason']));
    exit;
  }
}

/* Update training */
$payload = [
  'trainer_id'    => $trainer,
  'training_date' => $newDate,
  'status'        => $newStatus
];

$updateResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings?id=eq.$id",
  false,
  stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($payload)
    ]
  ])
);

if ($updateResponse === false) {
  error_log("Failed to update training: $id");
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Failed to update training. Please try again.'));
  exit;
}

// Audit log
auditLog('trainings', 'update', $id, [
  'old_status' => $current['status'],
  'new_status' => $newStatus,
  'trainer_id' => $trainer
]);

header('Location: ' . BASE_PATH . '/pages/trainings.php?success=' . urlencode('Training updated successfully'));
exit;

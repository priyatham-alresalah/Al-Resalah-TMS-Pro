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

$training_id = trim($_POST['training_id'] ?? '');

if (empty($training_id)) {
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Training ID missing'));
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

// Get the attendance checkpoint
$checkpoint = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_checkpoints?training_id=eq.$training_id&checkpoint=eq.attendance_verified&select=id",
    false,
    $ctx
  ),
  true
);

$userId = $_SESSION['user']['id'];
$checkpointId = null;

// If checkpoint doesn't exist, create it
if (empty($checkpoint)) {
  $checkpointData = [
    'training_id' => $training_id,
    'checkpoint' => 'attendance_verified',
    'completed' => false
  ];
  
  $createCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($checkpointData)
    ]
  ]);
  
  $createResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_checkpoints",
    false,
    $createCtx
  );
  
  if ($createResponse === false) {
    error_log("Failed to create attendance checkpoint for training: $training_id");
    header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to create attendance checkpoint. Please try again.'));
    exit;
  }
  
  $createdCheckpoint = json_decode($createResponse, true);
  $checkpointId = $createdCheckpoint[0]['id'] ?? null;
  
  if (!$checkpointId) {
    error_log("Failed to get checkpoint ID after creation for training: $training_id");
    header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to create attendance checkpoint. Please try again.'));
    exit;
  }
} else {
  $checkpointId = $checkpoint[0]['id'];
}

// Update checkpoint to completed
$updateCtx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode([
      'completed' => true,
      'completed_by' => $userId,
      'completed_at' => date('Y-m-d H:i:s')
    ])
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/training_checkpoints?id=eq.$checkpointId",
  false,
  $updateCtx
);

if ($response === false) {
  error_log("Failed to verify attendance for training: $training_id");
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to verify attendance. Please try again.'));
  exit;
}

// Audit log
auditLog('trainings', 'verify_attendance', $training_id, [
  'checkpoint_id' => $checkpointId,
  'verified_by' => $userId
]);

header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&success=' . urlencode('Attendance verified successfully. You can now complete the training.'));
exit;

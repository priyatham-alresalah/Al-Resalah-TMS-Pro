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
$candidate_id = trim($_POST['candidate_id'] ?? '');
$attended = isset($_POST['attended']) ? ($_POST['attended'] === 'true' || $_POST['attended'] === '1') : false;

if (empty($training_id) || empty($candidate_id)) {
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Missing data'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['attended' => $attended])
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&candidate_id=eq.$candidate_id",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to update attendance for training: $training_id, candidate: $candidate_id");
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to update attendance. Please try again.'));
  exit;
}

// Audit log
auditLog('trainings', 'update_attendance', $training_id, [
  'candidate_id' => $candidate_id,
  'attended' => $attended
]);

header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&success=' . urlencode('Attendance updated successfully'));
exit;

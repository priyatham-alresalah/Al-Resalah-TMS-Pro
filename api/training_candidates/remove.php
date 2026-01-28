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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Invalid request'));
  exit;
}

$training_id  = trim($_POST['training_id'] ?? '');
$candidate_id = trim($_POST['candidate_id'] ?? '');

if (empty($training_id) || empty($candidate_id)) {
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Missing data'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'DELETE',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$deleteResponse = @file_get_contents(
  SUPABASE_URL .
  "/rest/v1/training_candidates?training_id=eq.$training_id&candidate_id=eq.$candidate_id",
  false,
  $ctx
);

if ($deleteResponse === false) {
  error_log("Failed to remove candidate from training: $training_id, candidate: $candidate_id");
  header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to remove candidate. Please try again.'));
  exit;
}

// Audit log
auditLog('trainings', 'remove_candidate', $training_id, [
  'candidate_id' => $candidate_id
]);

header('Location: ' . BASE_PATH . '/pages/training_candidates.php?training_id=' . urlencode($training_id) . '&success=' . urlencode('Candidate removed successfully'));
exit;

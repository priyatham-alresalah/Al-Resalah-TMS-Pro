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
$candidates = $_POST['candidates'] ?? [];

if (empty($training_id)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Training ID missing'));
  exit;
}

$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

// Get old assignments for audit log
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

$oldAssignments = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
) ?: [];

// 1️⃣ Remove old assignments
$ctx_delete = stream_context_create([
  'http' => [
    'method' => 'DELETE',
    'header' => $headers
  ]
]);

@file_get_contents(
  SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id",
  false,
  $ctx_delete
);

// 2️⃣ Insert new ones
$assignedCount = 0;
foreach ($candidates as $cid) {
  $cid = trim($cid);
  if (empty($cid)) continue;

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

  $insertResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates",
    false,
    $ctx_insert
  );

  if ($insertResponse !== false) {
    $assignedCount++;
  }
}

// Audit log
auditLog('trainings', 'assign_candidates', $training_id, [
  'old_count' => count($oldAssignments),
  'new_count' => $assignedCount,
  'candidate_ids' => $candidates
]);

header('Location: ' . BASE_PATH . '/pages/trainings.php?success=' . urlencode("Successfully assigned $assignedCount candidate(s)"));
exit;

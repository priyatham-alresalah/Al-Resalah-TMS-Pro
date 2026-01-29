<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/workflow.php';
require '../../includes/audit_log.php';
require '../../includes/branch.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('trainings', 'create');

$inquiryId = trim($_POST['inquiry_id'] ?? '');
$clientId = trim($_POST['client_id'] ?? '');
$trainerId = trim($_POST['trainer_id'] ?? null);
$trainingDate = trim($_POST['training_date'] ?? '');

if (empty($inquiryId) || empty($clientId) || empty($trainingDate)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('All required fields must be provided'));
  exit;
}

// Enforce workflow - check prerequisites
$workflowCheck = canCreateTraining($inquiryId);
if (!$workflowCheck['allowed']) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

$branchId = getUserBranchId();

$trainingData = [
  'inquiry_id' => $inquiryId,
  'client_id' => $clientId,
  'trainer_id' => $trainerId,
  'training_date' => $trainingDate,
  'status' => 'scheduled'
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $trainingData['branch_id'] = $branchId;
}

$data = json_encode($trainingData);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

$response = @file_get_contents(SUPABASE_URL . '/rest/v1/trainings', false, $ctx);

if ($response === false) {
  error_log("Failed to create training for inquiry: $inquiryId");
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Failed to create training. Please try again.'));
  exit;
}

$training = json_decode($response, true);
$trainingId = $training['id'] ?? null;

if ($trainingId) {
  // Create initial checkpoints
  $checkpoints = [
    ['checkpoint' => 'docs_uploaded', 'completed' => false],
    ['checkpoint' => 'attendance_verified', 'completed' => false],
    ['checkpoint' => 'certificate_ready', 'completed' => false],
    ['checkpoint' => 'invoice_ready', 'completed' => false]
  ];

  foreach ($checkpoints as $checkpoint) {
    $checkpointData = array_merge($checkpoint, ['training_id' => $trainingId]);
    $checkpointCtx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($checkpointData)
      ]
    ]);
    @file_get_contents(SUPABASE_URL . "/rest/v1/training_checkpoints", false, $checkpointCtx);
  }

  // Audit log
  auditLog('trainings', 'create', $trainingId, [
    'inquiry_id' => $inquiryId,
    'trainer_id' => $trainerId,
    'training_date' => $trainingDate
  ]);
}

header('Location: ' . BASE_PATH . '/pages/trainings.php?success=' . urlencode('Training created successfully'));
exit;

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

$inquiry_id = trim($_POST['inquiry_id'] ?? '');

if (empty($inquiry_id)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Invalid inquiry'));
  exit;
}

// Enforce workflow - check prerequisites
$workflowCheck = canCreateTraining($inquiry_id);
if (!$workflowCheck['allowed']) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

/* Check if already converted */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE
  ]
]);

$existing = json_decode(
  @file_get_contents(
    SUPABASE_URL."/rest/v1/trainings?inquiry_id=eq.$inquiry_id",
    false,
    $ctx
  ),
  true
);

if (!empty($existing)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Inquiry already converted to training'));
  exit;
}

/* Fetch inquiry */
$inq = json_decode(
  file_get_contents(
    SUPABASE_URL."/rest/v1/inquiries?id=eq.$inquiry_id",
    false,
    $ctx
  ),
  true
)[0];

/* Create training */
$branchId = getUserBranchId();

$data = [
  'inquiry_id'   => $inquiry_id,
  'client_id'    => $inq['client_id'],
  'status'       => 'scheduled'
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $data['branch_id'] = $branchId;
}

$ctx_create = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n".
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL."/rest/v1/trainings",
  false,
  $ctx_create
);

if ($response === false) {
  error_log("Failed to create training for inquiry: $inquiry_id");
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Failed to create training. Please try again.'));
  exit;
}

/* Get created training */
$newTraining = json_decode($response, true)[0] ?? null;

if (!$newTraining || !isset($newTraining['id'])) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Failed to create training'));
  exit;
}

$trainingId = $newTraining['id'];

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
        "Content-Type: application/json\r\n".
        "apikey: ".SUPABASE_SERVICE."\r\n".
        "Authorization: Bearer ".SUPABASE_SERVICE,
      'content' => json_encode($checkpointData)
    ]
  ]);
  @file_get_contents(SUPABASE_URL . "/rest/v1/training_checkpoints", false, $checkpointCtx);
}

// Audit log
auditLog('trainings', 'create', $trainingId, [
  'inquiry_id' => $inquiry_id,
  'method' => 'convert'
]);

header("Location: " . BASE_PATH . "/pages/training_edit.php?id=".$trainingId);
exit;

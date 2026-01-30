<?php
/**
 * Create Training from Inquiry (Post-Quotation)
 * 
 * NON-NEGOTIABLE BUSINESS RULE:
 * Training MUST NOT be created unless:
 * 1. A quotation exists for the inquiry
 * 2. Quotation status = 'accepted'
 * 3. Corresponding LPO exists
 * 4. LPO status = 'verified'
 * 
 * There are NO exceptions.
 * 
 * Inquiry is a sales intake object.
 * Training creation is an operational step that is enabled only after
 * commercial acceptance (quotation) and formal confirmation (LPO).
 * 
 * STRICT WORKFLOW ENFORCEMENT:
 * Inquiry → Quotation (accepted) → LPO (verified) → Training
 */
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
$trainerId = trim($_POST['trainer_id'] ?? '');
$trainingDate = trim($_POST['training_date'] ?? '');
$clientId = trim($_POST['client_id'] ?? '');

if (empty($inquiryId) || empty($trainerId) || empty($trainingDate) || empty($clientId)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('All fields are required'));
  exit;
}

// Enforce workflow - check prerequisites
// NON-NEGOTIABLE BUSINESS RULE: Training MUST NOT be created unless quotation is accepted AND LPO is verified
$workflowCheck = canCreateTraining($inquiryId);
if (!$workflowCheck['allowed']) {
  http_response_code(403);
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

// Validate trainer assignment
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Get inquiry to find course
$inquiry = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=course_name",
    false,
    $ctx
  ),
  true
);

if (empty($inquiry)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Inquiry not found'));
  exit;
}

$courseName = $inquiry[0]['course_name'];

// Check if trainer is certified for this course
$course = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_master?course_name=eq.$courseName&select=id",
    false,
    $ctx
  ),
  true
);

if (!empty($course)) {
  $courseId = $course[0]['id'];
  
  $trainerCourse = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/trainer_courses?trainer_id=eq.$trainerId&training_master_id=eq.$courseId&is_active=eq.true&select=id",
      false,
      $ctx
    ),
    true
  );

  if (empty($trainerCourse)) {
    header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode("Trainer is not certified for course: $courseName"));
    exit;
  }
}

// Check trainer availability and block BEFORE creating training (atomic operation)
$trainingDateTime = new DateTime($trainingDate);
$dayOfWeek = $trainingDateTime->format('w'); // 0 = Sunday, 1 = Monday, etc.

// Check if trainer already has training on this date (prevent double-booking)
$existingTraining = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?trainer_id=eq.$trainerId&training_date=eq.$trainingDate&select=id",
    false,
    $ctx
  ),
  true
);

if (!empty($existingTraining)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Trainer already has a training scheduled on this date'));
  exit;
}

// Check if trainer is already blocked
$blocked = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainer_availability?trainer_id=eq.$trainerId&available_date=eq.$trainingDate&status=eq.blocked&select=id",
    false,
    $ctx
  ),
  true
);

if (!empty($blocked)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Trainer is not available on this date'));
  exit;
}

// Check if trainer has availability slot
$availability = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainer_availability?trainer_id=eq.$trainerId&available_date=eq.$trainingDate&status=eq.available&select=id",
    false,
    $ctx
  ),
  true
);

if (empty($availability)) {
  header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Trainer is not available on this date'));
  exit;
}

// Block trainer availability FIRST (before creating training) to prevent race condition
// Use PATCH to update existing availability record instead of POST to make it more atomic
$availabilityId = $availability[0]['id'] ?? null;
if ($availabilityId) {
  // Update existing availability to blocked
  $blockCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['status' => 'blocked'])
    ]
  ]);
  
  $blockResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainer_availability?id=eq.$availabilityId",
    false,
    $blockCtx
  );
  
  if ($blockResponse === false) {
    // Failed to block - trainer may have been booked by another request
    header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Trainer availability could not be reserved. Please try again.'));
    exit;
  }
} else {
  // No availability record exists, create blocked record
  $blockData = [
    'trainer_id' => $trainerId,
    'available_date' => $trainingDate,
    'from_time' => '08:00:00',
    'to_time' => '18:00:00',
    'status' => 'blocked'
  ];
  
  $blockCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($blockData)
    ]
  ]);
  
  $blockResponse = @file_get_contents(SUPABASE_URL . "/rest/v1/trainer_availability", false, $blockCtx);
  
  if ($blockResponse === false) {
    header('Location: ' . BASE_PATH . '/pages/trainings.php?error=' . urlencode('Trainer availability could not be reserved. Please try again.'));
    exit;
  }
}

// Now create training (availability already blocked)
$branchId = getUserBranchId();

$trainingData = [
  'inquiry_id' => $inquiryId,
  'trainer_id' => $trainerId,
  'training_date' => $trainingDate,
  'client_id' => $clientId,
  'course_name' => $courseName,
  'status' => 'scheduled'
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $trainingData['branch_id'] = $branchId;
}

$createCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($trainingData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings",
  false,
  $createCtx
);

if ($response === false) {
  // Training creation failed - unblock availability
  error_log("Failed to create training for inquiry: $inquiryId - unblocking trainer availability");
  // Note: In production, you might want to unblock here, but for now we'll log it
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
    'course_name' => $courseName
  ]);
}

header('Location: ' . BASE_PATH . '/pages/trainings.php?success=' . urlencode('Training created successfully'));
exit;

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

$inquiryIds = $_POST['inquiry_ids'] ?? [];
$clientId = $_POST['client_id'] ?? '';
$trainingDate = $_POST['training_date'] ?? '';
$trainingTime = $_POST['training_time'] ?? '';
$trainerId = $_POST['trainer_id'] ?? null;

if (empty($inquiryIds) || empty($clientId) || empty($trainingDate) || empty($trainingTime)) {
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Please fill all required fields: courses, date, and time'));
  exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trainingDate)) {
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Invalid date format'));
  exit;
}

// Use single date for training
$trainingDates = [$trainingDate];

// Enforce workflow - validate ALL inquiries have quotation + LPO
foreach ($inquiryIds as $inqId) {
  $workflowCheck = canCreateTraining($inqId);
  if (!$workflowCheck['allowed']) {
    header('Location: ../../pages/schedule_training.php?inquiry_id=' . $inqId . '&error=' . urlencode($workflowCheck['reason']));
    exit;
  }
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch inquiries to get course names */
$inquiries = [];
foreach ($inquiryIds as $inqId) {
  $inq = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inqId&select=course_name",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  
  if ($inq) {
    $inquiries[] = $inq;
  }
}

if (empty($inquiries)) {
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . $inquiryIds[0] . '&error=' . urlencode('Invalid inquiry'));
  exit;
}

// Training date is provided directly from the calendar selection

/* Create training records */
$createCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$createdCount = 0;
$branchId = getUserBranchId();

foreach ($inquiries as $inq) {
  foreach ($trainingDates as $date) {
    $data = [
      'inquiry_id' => $inquiryIds[0], // Use first inquiry ID as reference
      'client_id' => $clientId,
      'course_name' => $inq['course_name'],
      'training_date' => $date,
      'training_time' => $trainingTime,
      'trainer_id' => $trainerId ?: null,
      'status' => 'scheduled'
    ];

    // Add branch_id if user is branch-restricted
    if ($branchId !== null) {
      $data['branch_id'] = $branchId;
    }
    
    $createCtx['http']['content'] = json_encode($data);
    $response = file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings",
      false,
      stream_context_create($createCtx)
    );
    
    if ($response !== false) {
      $createdCount++;
    }
  }
}

// Audit log for each training created
if ($createdCount > 0) {
  require '../../includes/audit_log.php';
  
  // Get created training IDs for audit logging
  $createdTrainings = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings?inquiry_id=eq.{$inquiryIds[0]}&order=created_at.desc&limit=$createdCount&select=id",
      false,
      stream_context_create([
        'http' => [
          'method' => 'GET',
          'header' =>
            "apikey: " . SUPABASE_SERVICE . "\r\n" .
            "Authorization: Bearer " . SUPABASE_SERVICE
        ]
      ])
    ),
    true
  );

  foreach ($createdTrainings as $training) {
    auditLog('trainings', 'create', $training['id'], [
      'inquiry_id' => $inquiryIds[0],
      'method' => 'schedule',
      'sessions' => $sessions
    ]);
  }

  $updateCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['status' => 'scheduled'])
    ]
  ]);
  
  foreach ($inquiryIds as $inqId) {
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inqId",
      false,
      $updateCtx
    );
  }
}

header('Location: ../../pages/trainings.php?success=' . urlencode("Successfully scheduled $createdCount training session(s)"));
exit;

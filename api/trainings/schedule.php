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
$days = $_POST['days'] ?? [];
$trainingTime = $_POST['training_time'] ?? '';
$startDate = $_POST['start_date'] ?? '';
$sessions = intval($_POST['sessions'] ?? 1);
$trainerId = $_POST['trainer_id'] ?? null;

if (empty($inquiryIds) || empty($clientId) || empty($days) || empty($trainingTime) || empty($startDate)) {
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Please fill all required fields'));
  exit;
}

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

/* Calculate training dates based on selected days and start date */
$startDateTime = new DateTime($startDate);
$trainingDates = [];
$dayMap = [
  'monday' => 1,
  'tuesday' => 2,
  'wednesday' => 3,
  'thursday' => 4,
  'friday' => 5,
  'saturday' => 6,
  'sunday' => 0
];

$selectedDayNumbers = [];
foreach ($days as $day) {
  $selectedDayNumbers[] = $dayMap[strtolower($day)];
}

/* Generate dates for the specified number of sessions */
$currentDate = clone $startDateTime;
$sessionCount = 0;
$maxIterations = $sessions * 7; // Prevent infinite loop

while ($sessionCount < $sessions && $maxIterations > 0) {
  $dayOfWeek = (int)$currentDate->format('w'); // 0 = Sunday, 1 = Monday, etc.
  
  if (in_array($dayOfWeek, $selectedDayNumbers)) {
    $trainingDates[] = $currentDate->format('Y-m-d');
    $sessionCount++;
  }
  
  $currentDate->modify('+1 day');
  $maxIterations--;
}

if (empty($trainingDates)) {
  header('Location: ../../schedule_training.php?inquiry_id=' . $inquiryIds[0] . '&error=' . urlencode('Could not generate training dates. Please check your selections.'));
  exit;
}

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

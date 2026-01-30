<?php
/**
 * Schedule Training (Post-Quotation)
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

// Handle both inquiry_ids[] (array) and inquiry_id (single) from form
$inquiryIds = $_POST['inquiry_ids'] ?? [];
$inquiryId = $_POST['inquiry_id'] ?? '';

// If inquiry_ids is empty but inquiry_id exists, use inquiry_id
if (empty($inquiryIds) && !empty($inquiryId)) {
  $inquiryIds = [$inquiryId];
}

$clientId = $_POST['client_id'] ?? '';
$trainingDate = $_POST['training_date'] ?? '';
$trainingTime = $_POST['training_time'] ?? '';
$trainerId = $_POST['trainer_id'] ?? '';

// Ensure inquiry_ids is an array (handle case where it might be a single value or empty)
if (!is_array($inquiryIds)) {
  $inquiryIds = !empty($inquiryIds) ? [$inquiryIds] : [];
}

// Log received data for debugging
error_log("Schedule training received - POST data: " . json_encode($_POST));
error_log("Schedule training processed - inquiry_ids: " . json_encode($inquiryIds) . ", client_id: $clientId, date: $trainingDate, time: $trainingTime");

if (empty($inquiryIds) || empty($clientId) || empty($trainingDate) || empty($trainingTime)) {
  $missingFields = [];
  if (empty($inquiryIds)) $missingFields[] = 'courses';
  if (empty($clientId)) $missingFields[] = 'client';
  if (empty($trainingDate)) $missingFields[] = 'date';
  if (empty($trainingTime)) $missingFields[] = 'time';
  error_log("Schedule training validation failed - missing fields: " . implode(', ', $missingFields));
  error_log("Received POST data: " . json_encode($_POST));
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? $inquiryId ?? '') . '&error=' . urlencode('Please fill all required fields: ' . implode(', ', $missingFields)));
  exit;
}

// Require trainer - do not allow scheduling without a trainer
$trainerId = trim($trainerId ?? '') ?: null;
if (empty($trainerId)) {
  error_log("Schedule training validation failed - trainer is required");
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? $inquiryId ?? '') . '&error=' . urlencode('Please select a trainer. Training cannot be scheduled without a trainer.'));
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
// NON-NEGOTIABLE BUSINESS RULE: Training MUST NOT be created unless quotation is accepted AND LPO is verified
foreach ($inquiryIds as $inqId) {
  $workflowCheck = canCreateTraining($inqId);
  if (!$workflowCheck['allowed']) {
    http_response_code(403);
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
$createdCount = 0;
$branchId = getUserBranchId();
$userId = $_SESSION['user']['id'] ?? null;

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

    // Add created_by if user is logged in
    if ($userId) {
      $data['created_by'] = $userId;
    }

    // Add branch_id if user is branch-restricted
    if ($branchId !== null) {
      $data['branch_id'] = $branchId;
    }
    
    // Create context for each request
    $createCtx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($data)
      ]
    ]);
    
    // Capture HTTP response headers to check status code
    $httpResponseHeaders = [];
    $response = @file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings",
      false,
      $createCtx
    );
    
    // Get HTTP response code from headers (superglobal available after file_get_contents)
    $httpResponseHeaders = $http_response_header ?? [];
    $httpStatusCode = null;
    foreach ($httpResponseHeaders as $header) {
      if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
        $httpStatusCode = intval($matches[1]);
        break;
      }
    }
    
    // Log the raw response and HTTP status for debugging
    error_log("Training creation attempt for '{$inq['course_name']}' - HTTP Status: " . ($httpStatusCode ?? 'unknown') . ", Response: " . substr($response, 0, 500));
    
    // Check if response is valid JSON and contains created record
    if ($response !== false && $response !== '') {
      $responseData = json_decode($response, true);
      
      // Check for Supabase error format: {"message": "...", "code": "...", "details": "..."}
      if (isset($responseData['message']) || isset($responseData['code']) || isset($responseData['hint'])) {
        // Error response from Supabase
        $errorMsg = $responseData['message'] ?? ($responseData['hint'] ?? 'Unknown error');
        $errorCode = $responseData['code'] ?? 'unknown';
        $errorDetails = $responseData['details'] ?? '';
        error_log("SUPABASE ERROR creating training for '{$inq['course_name']}': [$errorCode] $errorMsg. Details: $errorDetails. Data: " . json_encode($data));
      } elseif (is_array($responseData)) {
        // Supabase POST returns array - check if it contains created record(s)
        if (!empty($responseData) && (isset($responseData[0]['id']) || isset($responseData['id']))) {
          // Success - array contains at least one record with id
          $createdCount++;
          $createdId = $responseData[0]['id'] ?? $responseData['id'] ?? 'unknown';
          error_log("Successfully created training for '{$inq['course_name']}' with ID: $createdId");
        } elseif (empty($responseData)) {
          // Empty array - might be a constraint violation or validation error
          error_log("EMPTY RESPONSE ARRAY for training creation - likely constraint violation. Data: " . json_encode($data));
        } else {
          // Array but no id field - unexpected format
          error_log("UNEXPECTED RESPONSE format (array without id): " . json_encode($responseData) . ". Data: " . json_encode($data));
        }
      } elseif (is_array($responseData) && isset($responseData['id'])) {
        // Single object with id (less common but possible)
        $createdCount++;
        error_log("Successfully created training for '{$inq['course_name']}' with ID: " . $responseData['id']);
      } else {
        // Unexpected response format
        error_log("UNEXPECTED RESPONSE format: " . json_encode($responseData) . ". Raw response: " . substr($response, 0, 200) . ". Data: " . json_encode($data));
      }
    } else {
      // HTTP request failed or empty response
      $httpError = error_get_last();
      $errorMsg = $httpError['message'] ?? 'Empty or false response';
      error_log("HTTP REQUEST FAILED for training creation: $errorMsg. Data: " . json_encode($data));
      if (!empty($httpResponseHeaders)) {
        error_log("HTTP Response Headers: " . json_encode($httpResponseHeaders));
      }
    }
  }
}

// Audit log for each training created and create checkpoints
if ($createdCount > 0) {
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
    $trainingId = $training['id'];
    
    // Create initial checkpoints for each training
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
      
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/training_checkpoints",
        false,
        $checkpointCtx
      );
    }
    
    auditLog('trainings', 'create', $trainingId, [
      'inquiry_id' => $inquiryIds[0],
      'method' => 'schedule',
      'training_date' => $trainingDate,
      'training_time' => $trainingTime,
      'courses_count' => count($inquiries)
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

// If no trainings were created, redirect with error instead of success
if ($createdCount === 0) {
  error_log("WARNING: No trainings were created. Check logs above for details.");
  header('Location: ../../pages/schedule_training.php?inquiry_id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Failed to create training sessions. Please check the logs or try again.'));
  exit;
}

header('Location: ../../pages/trainings.php?success=' . urlencode("Successfully scheduled $createdCount training session(s)"));
exit;

<?php
/**
 * Issue Certificates in Bulk
 * Enforces: Training completed + Documents uploaded → Certificate
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/workflow.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('certificates', 'create');

$training_id = $_POST['training_id'] ?? null;
if (!$training_id) {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Training ID missing'));
  exit;
}

// Enforce workflow - check prerequisites
$workflowCheck = canIssueCertificate($training_id);
if (!$workflowCheck['allowed']) {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode($workflowCheck['reason']));
  exit;
}

$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

/* Fetch candidates for training */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

$candidates = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
);

if (!$candidates) {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('No candidates found'));
  exit;
}

$selectedCandidates = $_POST['candidates'] ?? [];
if (empty($selectedCandidates)) {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Please select at least one candidate'));
  exit;
}

/* Fetch existing certificates */
$existing = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
) ?: [];

$issuedMap = [];
foreach ($existing as $e) {
  $issuedMap[$e['candidate_id']] = true;
}

/* Issue certificates with atomic certificate number generation */
$year = date('Y');
$userId = $_SESSION['user']['id'];
$issuedCount = 0;

foreach ($candidates as $c) {
  $cid = $c['candidate_id'];
  
  if (!in_array($cid, $selectedCandidates)) {
    continue; // Skip unselected candidates
  }

  if (isset($issuedMap[$cid])) {
    continue; // already issued
  }

  // Atomic certificate number generation - get and increment counter for each certificate
  $counterCtx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => $headers
    ]
  ]);

  $counter = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year&select=last_number&limit=1",
      false,
      $counterCtx
    ),
    true
  );

  $lastNumber = !empty($counter) ? intval($counter[0]['last_number']) : 0;
  $newNumber = $lastNumber + 1;

  // Update counter atomically BEFORE creating certificate
  $updateCounterData = ['last_number' => $newNumber];
  $updateCounterCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' => $headers,
      'content' => json_encode($updateCounterData)
    ]
  ]);

  $counterUpdateResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year",
    false,
    $updateCounterCtx
  );

  // If counter doesn't exist, create it
  if ($counterUpdateResponse === false && empty($counter)) {
    $createCounterData = [
      'year' => $year,
      'last_number' => $newNumber
    ];
    $createCounterCtx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => json_encode($createCounterData)
      ]
    ]);
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/certificate_counters",
      false,
      $createCounterCtx
    );
  }

  // Generate unique certificate number
  $cert_no = "AR-$year-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

  $payload = json_encode([
    'training_id' => $training_id,
    'candidate_id' => $cid,
    'certificate_no' => $cert_no,
    'issued_date' => date('Y-m-d'),
    'issued_by' => $userId,
    'status' => 'active'
  ]);

  $ctxInsert = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => $headers,
      'content' => $payload
    ]
  ]);

  $result = @file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates",
    false,
    $ctxInsert
  );
  
  if ($result !== false) {
    $certData = json_decode($result, true);
    $certId = $certData['id'] ?? null;
    
    if ($certId) {
      // Log certificate issuance
      $logData = [
        'certificate_id' => $certId,
        'action' => 'issued',
        'performed_by' => $userId
      ];
      
      $logCtx = stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' => $headers,
          'content' => json_encode($logData)
        ]
      ]);
      
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/certificate_issuance_logs",
        false,
        $logCtx
      );
      
      // Audit log
      auditLog('certificates', 'issue', $certId, [
        'certificate_no' => $cert_no,
        'candidate_id' => $cid,
        'training_id' => $training_id
      ]);
      
      $issuedCount++;
    }
  }
}

// Update training checkpoint (only once after all certificates issued)
if ($issuedCount > 0) {
  $checkpointCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' => $headers,
      'content' => json_encode([
        'completed' => true,
        'completed_by' => $userId,
        'completed_at' => date('Y-m-d H:i:s')
      ])
    ]
  ]);
  
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_checkpoints?training_id=eq.$training_id&checkpoint=eq.certificate_ready",
    false,
    $checkpointCtx
  );
}

header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&success=' . urlencode("Successfully issued $issuedCount certificate(s)"));
exit;

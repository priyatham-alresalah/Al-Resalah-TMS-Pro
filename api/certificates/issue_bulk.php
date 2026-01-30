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
require '../../includes/branch.php';
require '../../includes/certificate_number.php';

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
$skippedCount = 0;
$errors = [];

foreach ($candidates as $c) {
  $cid = $c['candidate_id'];
  
  if (!in_array($cid, $selectedCandidates)) {
    continue; // Skip unselected candidates
  }

  if (isset($issuedMap[$cid])) {
    $skippedCount++; // already issued
    continue;
  }

  // Use helper function for certificate number generation with retry logic
  $maxRetries = 5;
  $cert_no = null;
  $retryCount = 0;
  
  while ($retryCount < $maxRetries) {
    try {
      $cert_no = getNextCertificateNumber();
      
      // Verify certificate number doesn't already exist (race condition check)
      $existingCert = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/certificates?certificate_no=eq.$cert_no&select=id",
          false,
          $ctx
        ),
        true
      );
      
      if (empty($existingCert)) {
        break; // Number is unique, proceed
      }
      
      // Number exists, retry
      $retryCount++;
      usleep(100000 * $retryCount); // Exponential backoff: 100ms, 200ms, 300ms...
    } catch (Exception $e) {
      error_log("Certificate number generation error: " . $e->getMessage());
      $retryCount++;
      if ($retryCount >= $maxRetries) {
        header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to generate certificate number. Please try again.'));
        exit;
      }
      usleep(100000 * $retryCount);
    }
  }
  
  if (!$cert_no) {
    header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to generate certificate number after retries. Please try again.'));
    exit;
  }

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
    } else {
      $errors[] = "Failed to issue certificate for candidate: $cid";
      error_log("Failed to issue certificate for candidate $cid: " . substr($result, 0, 200));
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

  // Auto-create invoice for this training (amounts from approved quotation)
  $invoiceResult = createInvoiceForTraining($training_id, [
    'completed_by' => $userId,
    'completed_at' => date('Y-m-d H:i:s'),
    'branch_id' => function_exists('getUserBranchId') ? getUserBranchId() : null
  ]);
  if ($invoiceResult['created'] && $invoiceResult['invoice_id']) {
    auditLog('invoices', 'create', $invoiceResult['invoice_id'], [
      'invoice_no' => $invoiceResult['invoice_no'],
      'training_id' => $training_id,
      'auto' => true
    ]);
  }
}

// Build appropriate message
if ($issuedCount > 0) {
  $message = "Successfully issued $issuedCount certificate(s)";
  if ($skippedCount > 0) {
    $message .= ". $skippedCount certificate(s) were already issued.";
  }
  if (!empty($invoiceResult['created']) && !empty($invoiceResult['invoice_no'])) {
    $message .= " Invoice " . $invoiceResult['invoice_no'] . " was created automatically.";
  }
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&success=' . urlencode($message));
} elseif ($skippedCount > 0) {
  // All selected certificates were already issued
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&info=' . urlencode("All selected candidates already have certificates issued ($skippedCount certificate(s))."));
} elseif (!empty($errors)) {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('Failed to issue certificates. Please try again.'));
} else {
  header('Location: ' . BASE_PATH . '/pages/issue_certificates.php?training_id=' . urlencode($training_id) . '&error=' . urlencode('No certificates were issued. Please check your selections.'));
}
exit;

<?php
// Start output buffering FIRST, before any includes
// This prevents any output (including whitespace, errors, warnings) from being sent before headers
if (!ob_get_level()) {
  ob_start();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$errorLogPath = __DIR__ . '/../../logs/php_errors.log';
if (is_writable(dirname($errorLogPath)) || is_writable($errorLogPath)) {
  ini_set('error_log', $errorLogPath);
}

// Log that we're starting
error_log("create_quote.php: Starting quote creation process");

try {
  require '../../includes/config.php';
  require '../../includes/auth_check.php';
  require '../../includes/csrf.php';

  /* CSRF Protection */
  requireCSRF();

  // Ensure output buffer is clean
  if (ob_get_level()) {
    ob_clean();
  }
  
  error_log("create_quote.php: Includes loaded successfully");
  
  // Ensure output buffer is clean
  if (ob_get_level()) {
    ob_clean();
  }
  
  // Don't require quote_pdf.php here - it will be required later when needed
  // This prevents function redeclaration errors
} catch (Exception $e) {
  // If includes fail, log and redirect
  error_log("create_quote.php: Error loading includes: " . $e->getMessage());
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('System error. Please try again.'));
  }
  exit;
}

$clientId = $_POST['client_id'] ?? '';
$inquiryIds = $_POST['inquiry_ids'] ?? [];
$amounts = $_POST['amount'] ?? [];
$candidates = $_POST['candidates'] ?? [];
$vats = $_POST['vat'] ?? [];
$notes = trim($_POST['notes'] ?? '');

if (empty($inquiryIds) || empty($clientId)) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Please select at least one course'));
  }
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch client */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.$clientId&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$client) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Client not found'));
  }
  exit;
}

/* Prepare quote data */
$quoteCourses = [];
$grandTotal = 0;

foreach ($inquiryIds as $inqId) {
  $amountPerCandidate = floatval($amounts[$inqId] ?? 0);
  $numCandidates = intval($candidates[$inqId] ?? 1);
  $vat = floatval($vats[$inqId] ?? 5);
  
  // Validate candidates: 1 to 10000
  if ($numCandidates < 1 || $numCandidates > 10000) {
    if (ob_get_level()) {
      ob_end_clean();
    }
    if (!headers_sent()) {
      header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode('Number of candidates must be between 1 and 10000'));
    }
    exit;
  }
  
  if ($amountPerCandidate <= 0) {
    if (ob_get_level()) {
      ob_end_clean();
    }
    if (!headers_sent()) {
      header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode('Amount per candidate must be greater than 0'));
    }
    exit;
  }
  
  // Calculate: (amount per candidate * number of candidates) + VAT
  $subtotal = $amountPerCandidate * $numCandidates;
  $vatAmount = $subtotal * $vat / 100;
  $total = $subtotal + $vatAmount;
  $grandTotal += $total;
  
  /* Fetch inquiry for course name */
  $inquiry = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inqId&select=course_name",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  
  if ($inquiry) {
    $quoteCourses[] = [
      'inquiry_id' => $inqId,
      'course_name' => $inquiry['course_name'],
      'candidates' => $numCandidates,
      'amount_per_candidate' => $amountPerCandidate,
      'amount' => $subtotal, // Total amount before VAT
      'vat' => $vat,
      'vat_amount' => $vatAmount,
      'total' => $total
    ];
  }
}

if (empty($quoteCourses)) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode('Please enter valid amounts'));
  }
  exit;
}

try {
  /* Generate quote number */
  $quoteNo = 'QUOTE-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

  /* Generate PDF */
  $pdfFileName = null;
  try {
    // Only require once, check if function exists first
    if (!function_exists('generateQuotePDF')) {
      require_once '../../includes/quote_pdf.php';
    }
    if (isFPDFAvailable() && function_exists('generateQuotePDF')) {
      // Log quote courses data for debugging
      error_log("Generating PDF with quote courses: " . json_encode($quoteCourses));
      error_log("Grand total: $grandTotal");
      
      $pdfFileName = generateQuotePDF(
        $quoteNo,
        $client['company_name'] ?? '',
        $client['email'] ?? '',
        $client['address'] ?? '',
        $quoteCourses,
        $grandTotal,
        $notes
      );
      
      if ($pdfFileName) {
        error_log("PDF generated successfully: $pdfFileName");
      } else {
        error_log("PDF generation returned null");
      }
    } else {
      error_log("FPDF not available or generateQuotePDF function not found");
    }
  } catch (Exception $e) {
    // If FPDF not available, continue without PDF for now
    $pdfFileName = null;
    error_log("PDF generation failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
  }

  $userId = $_SESSION['user']['id'] ?? null;
  if (!$userId) {
    if (ob_get_level()) {
      ob_end_clean();
    }
    if (!headers_sent()) {
      header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('User session expired. Please login again.'));
    }
    exit;
  }

  // Calculate totals for the quotation
  $subtotal = 0;
  $vatTotal = 0;
  $discount = 0;
  
  foreach ($quoteCourses as $qc) {
    $subtotal += $qc['amount']; // Amount before VAT
    $vatTotal += $qc['vat_amount']; // VAT amount
  }
  
  $total = $subtotal + $vatTotal - $discount;

  // Validate that the primary inquiry exists
  $primaryInquiry = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$inquiryIds[0]}&select=id,status",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  
  if (!$primaryInquiry) {
    throw new Exception('Primary inquiry not found. Please refresh and try again.');
  }

  // Create quotation record in quotations table
  $quotationData = [
    'inquiry_id' => $inquiryIds[0], // Use first inquiry as primary
    'quotation_no' => $quoteNo,
    'subtotal' => round($subtotal, 2),
    'vat' => round($vatTotal, 2),
    'discount' => round($discount, 2),
    'total' => round($total, 2),
    'status' => 'draft',
    'created_by' => $userId
  ];
  
  // Log the data being sent for debugging
  error_log("Creating quotation with data: " . json_encode($quotationData));

  $createCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "Prefer: return=representation\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($quotationData),
      'ignore_errors' => true
    ]
  ]);

  $quotationResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/quotations",
    false,
    $createCtx
  );

  if ($quotationResponse === false) {
    $httpResponse = isset($http_response_header) ? $http_response_header : [];
    $errorMsg = 'Failed to create quotation';
    if (!empty($httpResponse[0])) {
      $errorMsg .= ': ' . $httpResponse[0];
    }
    error_log("Failed to create quotation: " . print_r($httpResponse, true));
    throw new Exception($errorMsg);
  }

  $quotation = json_decode($quotationResponse, true);
  
  // Handle array response (PostgREST returns array with Prefer: return=representation)
  if (is_array($quotation) && isset($quotation[0])) {
    $quotation = $quotation[0];
  }
  
  if (empty($quotation) || !isset($quotation['id'])) {
    error_log("Invalid quotation response: " . $quotationResponse);
    throw new Exception('Failed to create quotation. Invalid response from server.');
  }

  // Update inquiries status to 'quoted' (only status field, no quote data)
  foreach ($quoteCourses as $qc) {
    $updateData = [
      'status' => 'quoted'
    ];
    
    $updateCtx = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($updateData),
        'ignore_errors' => true
      ]
    ]);
    
    $updateResponse = @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$qc['inquiry_id']}",
      false,
      $updateCtx
    );
    
    // Log if update fails but don't break the process
    if ($updateResponse === false) {
      $httpResponse = isset($http_response_header) ? $http_response_header : [];
      $errorMsg = 'Unknown error';
      if (!empty($httpResponse[0])) {
        $errorMsg = $httpResponse[0];
      }
      error_log("Failed to update inquiry {$qc['inquiry_id']} status: " . $errorMsg);
    }
  }

  // Clean and end output buffer before redirect
  if (ob_get_level()) {
    ob_end_clean();
  }
  
  // Ensure headers haven't been sent
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?success=' . urlencode("Quote created successfully! Quote No: $quoteNo" . ($pdfFileName ? ' (PDF generated)' : ' (Note: Install FPDF for PDF generation)')));
    exit;
  } else {
    // Headers already sent, use JavaScript redirect as fallback
    echo '<script>window.location.href="' . BASE_PATH . '/pages/inquiries.php?success=' . urlencode("Quote created successfully! Quote No: $quoteNo") . '";</script>';
    exit;
  }
} catch (Exception $e) {
  // Log the error with full details
  error_log("Quote creation error: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  error_log("POST data: " . print_r($_POST, true));
  
  // Clear output buffer
  ob_end_clean();
  
  // Redirect with error message (truncate if too long for URL)
  $errorMsg = 'An error occurred while creating the quote: ' . $e->getMessage();
  if (strlen($errorMsg) > 200) {
    $errorMsg = substr($errorMsg, 0, 197) . '...';
  }
  
  if (isset($inquiryIds[0])) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode($errorMsg));
  } else {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode($errorMsg));
  }
  exit;
}

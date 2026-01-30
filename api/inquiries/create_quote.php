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
$firstClientId = null;

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
  
  /* Fetch inquiry for course name and status validation */
  $inquiry = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inqId&select=id,course_name,status,client_id",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  
  if (!$inquiry) {
    if (ob_get_level()) {
      ob_end_clean();
    }
    if (!headers_sent()) {
      header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode("Inquiry $inqId not found"));
    }
    exit;
  }
  
  // Validate inquiry status is 'new'
  if (strtolower($inquiry['status'] ?? '') !== 'new') {
    if (ob_get_level()) {
      ob_end_clean();
    }
    if (!headers_sent()) {
      header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode("Inquiry '{$inquiry['course_name']}' is already quoted or closed"));
    }
    exit;
  }
  
  // Validate all inquiries belong to same client
  if ($inqId === $inquiryIds[0]) {
    $firstClientId = $inquiry['client_id'] ?? null;
  } else {
    if (($inquiry['client_id'] ?? null) !== $firstClientId) {
      if (ob_get_level()) {
        ob_end_clean();
      }
      if (!headers_sent()) {
        header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode("All inquiries must belong to the same client"));
      }
      exit;
    }
  }
  
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
  /* Generate quote number - ensure uniqueness */
  $maxAttempts = 10;
  $quoteNo = '';
  for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
    $quoteNo = 'QUOTE-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    
    // Check if this quote number already exists
    $checkCtx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' =>
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE
      ]
    ]);
    
    $existing = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/quotations?quotation_no=eq.$quoteNo&select=id&limit=1",
        false,
        $checkCtx
      ),
      true
    );
    
    if (empty($existing)) {
      break; // Quote number is unique
    }
    
    // If we've exhausted attempts, use timestamp for uniqueness
    if ($attempt === $maxAttempts - 1) {
      $quoteNo = 'QUOTE-' . date('Y') . '-' . time() . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
    }
  }

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
  // Note: For multi-inquiry quotations, we'll create a separate junction table entry
  // or use the quotation_no as a reference to link all inquiries
  
  $quotationData = [
    'inquiry_id' => $inquiryIds[0], // Use first inquiry as primary (for backward compatibility)
    'quotation_no' => $quoteNo,
    'subtotal' => round($subtotal, 2),
    'vat' => round($vatTotal, 2),
    'discount' => round($discount, 2),
    'total' => round($total, 2),
    'status' => 'draft',
    'created_by' => $userId
    // Note: 'notes' column doesn't exist in quotations table
    // Multi-inquiry support: All inquiries will be linked via quotation_no lookup
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

  // Get HTTP response code
  $httpCode = 0;
  if (isset($http_response_header) && !empty($http_response_header[0])) {
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches)) {
      $httpCode = intval($matches[1]);
    }
  }

  if ($quotationResponse === false) {
    $httpResponse = isset($http_response_header) ? $http_response_header : [];
    $errorMsg = 'Failed to create quotation';
    if (!empty($httpResponse[0])) {
      $errorMsg .= ': ' . $httpResponse[0];
    }
    error_log("Failed to create quotation: " . print_r($httpResponse, true));
    error_log("Quotation data sent: " . json_encode($quotationData));
    throw new Exception($errorMsg);
  }

  // Check for HTTP error codes
  if ($httpCode >= 400) {
    error_log("HTTP Error $httpCode when creating quotation. Response: " . $quotationResponse);
    error_log("Quotation data sent: " . json_encode($quotationData));
    
    // Try to parse error message from response
    $errorData = json_decode($quotationResponse, true);
    if (isset($errorData['message'])) {
      throw new Exception('Failed to create quotation: ' . $errorData['message']);
    } elseif (isset($errorData['error']) || isset($errorData['hint'])) {
      $errorMsg = isset($errorData['error']) ? $errorData['error'] : '';
      $hintMsg = isset($errorData['hint']) ? ' (' . $errorData['hint'] . ')' : '';
      throw new Exception('Failed to create quotation: ' . $errorMsg . $hintMsg);
    } else {
      throw new Exception('Failed to create quotation. HTTP ' . $httpCode . ': ' . substr($quotationResponse, 0, 200));
    }
  }

  $quotation = json_decode($quotationResponse, true);
  
  // Check if response is an error object
  if (isset($quotation['message']) || isset($quotation['error'])) {
    $errorMsg = isset($quotation['message']) ? $quotation['message'] : $quotation['error'];
    error_log("Supabase error response: " . $quotationResponse);
    error_log("Quotation data sent: " . json_encode($quotationData));
    throw new Exception('Failed to create quotation: ' . $errorMsg);
  }
  
  // Handle array response (PostgREST returns array with Prefer: return=representation)
  if (is_array($quotation) && isset($quotation[0])) {
    $quotation = $quotation[0];
  }
  
  if (empty($quotation) || !isset($quotation['id'])) {
    error_log("Invalid quotation response: " . $quotationResponse);
    error_log("HTTP Code: $httpCode");
    error_log("Quotation data sent: " . json_encode($quotationData));
    
    // Try to extract error from response
    if (is_string($quotationResponse) && !empty($quotationResponse)) {
      $errorData = json_decode($quotationResponse, true);
      if (isset($errorData['message'])) {
        throw new Exception('Failed to create quotation: ' . $errorData['message']);
      }
    }
    
    throw new Exception('Failed to create quotation. Invalid response from server. Please check the logs for details.');
  }

  // Update inquiries status to 'quoted'
  // Note: For multi-inquiry quotations, we'll use quotation_no as a reference
  // All inquiries in the batch will be linked via the same quotation_no
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

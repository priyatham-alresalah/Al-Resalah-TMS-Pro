<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

// Try to require quote PDF function, but don't fail if it doesn't exist
if (file_exists('../../includes/quote_pdf.php')) {
  require '../../includes/quote_pdf.php';
}

$clientId = $_POST['client_id'] ?? '';
$inquiryIds = $_POST['inquiry_ids'] ?? [];
$amounts = $_POST['amount'] ?? [];
$candidates = $_POST['candidates'] ?? [];
$vats = $_POST['vat'] ?? [];
$notes = trim($_POST['notes'] ?? '');

if (empty($inquiryIds) || empty($clientId)) {
  header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Please select at least one course'));
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
  header('Location: ../../pages/inquiries.php?error=' . urlencode('Client not found'));
  exit;
}

/* Prepare quote data */
$quoteCourses = [];
$grandTotal = 0;

foreach ($inquiryIds as $inqId) {
  $amountPerCandidate = floatval($amounts[$inqId] ?? 0);
  $numCandidates = intval($candidates[$inqId] ?? 1);
  $vat = floatval($vats[$inqId] ?? 5);
  
  if ($amountPerCandidate <= 0 || $numCandidates < 1) continue;
  
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
  header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode('Please enter valid amounts'));
  exit;
}

/* Generate quote number */
$quoteNo = 'QUOTE-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

/* Generate PDF */
$pdfFileName = null;
try {
  if (function_exists('generateQuotePDF')) {
    $pdfFileName = generateQuotePDF(
      $quoteNo,
      $client['company_name'] ?? '',
      $client['email'] ?? '',
      $client['address'] ?? '',
      $quoteCourses,
      $grandTotal,
      $notes
    );
  }
} catch (Exception $e) {
  // If FPDF not available, continue without PDF for now
  $pdfFileName = null;
  error_log("PDF generation failed: " . $e->getMessage());
}

/* Update inquiries status to 'quoted' and store quote data */
foreach ($quoteCourses as $qc) {
  // Build quote data - only include fields that exist in database
  $quoteData = [
    'status' => 'quoted',
    'quote_no' => $quoteNo,
    'quote_amount' => $qc['amount'], // Total amount before VAT
    'quote_vat' => $qc['vat'],
    'quote_total' => $qc['total'],
    'quoted_at' => date('Y-m-d H:i:s'),
    'quoted_by' => $_SESSION['user']['id']
  ];
  
  // Note: If database has quote_candidates and quote_amount_per_candidate fields, uncomment below:
  // $quoteData['quote_candidates'] = $qc['candidates'];
  // $quoteData['quote_amount_per_candidate'] = $qc['amount_per_candidate'];
  
  if ($pdfFileName) {
    $quoteData['quote_pdf'] = $pdfFileName;
  }
  
  // Create context for each update with the quote data
  $updateCtxFinal = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($quoteData)
    ]
  ]);
  
  $updateResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$qc['inquiry_id']}",
    false,
    $updateCtxFinal
  );
  
  // If update fails, log but continue (don't break the whole process)
  if ($updateResponse === false) {
    $httpResponse = isset($http_response_header) ? $http_response_header : [];
    $errorMsg = 'Unknown error';
    if (!empty($httpResponse[0])) {
      $errorMsg = $httpResponse[0];
    }
    error_log("Failed to update inquiry {$qc['inquiry_id']} with quote data: " . $errorMsg);
    // Continue processing other inquiries even if one fails
  }
}

  header('Location: ' . BASE_PATH . '/pages/inquiries.php?success=' . urlencode("Quote created successfully! Quote No: $quoteNo" . ($pdfFileName ? ' (PDF generated)' : ' (Note: Install FPDF for PDF generation)')));
  exit;
  
} catch (Exception $e) {
  // Log the error
  error_log("Quote creation error: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  
  // Redirect with error message
  $errorMsg = 'An error occurred while creating the quote. Please try again.';
  if (isset($inquiryIds[0])) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode($errorMsg));
  } else {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode($errorMsg));
  }
  exit;
}

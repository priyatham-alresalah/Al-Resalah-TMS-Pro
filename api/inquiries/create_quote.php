<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/quote_pdf.php';

$clientId = $_POST['client_id'] ?? '';
$inquiryIds = $_POST['inquiry_ids'] ?? [];
$amounts = $_POST['amount'] ?? [];
$vats = $_POST['vat'] ?? [];
$notes = trim($_POST['notes'] ?? '');

if (empty($inquiryIds) || empty($clientId)) {
  header('Location: ../../pages/inquiry_quote.php?id=' . ($inquiryIds[0] ?? '') . '&error=' . urlencode('Please select at least one course'));
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
  $amount = floatval($amounts[$inqId] ?? 0);
  $vat = floatval($vats[$inqId] ?? 5);
  
  if ($amount <= 0) continue;
  
  $vatAmount = $amount * $vat / 100;
  $total = $amount + $vatAmount;
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
      'amount' => $amount,
      'vat' => $vat,
      'total' => $total
    ];
  }
}

if (empty($quoteCourses)) {
  header('Location: ../../inquiry_quote.php?id=' . $inquiryIds[0] . '&error=' . urlencode('Please enter valid amounts'));
  exit;
}

/* Generate quote number */
$quoteNo = 'QUOTE-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

/* Generate PDF */
try {
  $pdfFileName = generateQuotePDF(
    $quoteNo,
    $client['company_name'] ?? '',
    $client['email'] ?? '',
    $client['address'] ?? '',
    $quoteCourses,
    $grandTotal,
    $notes
  );
} catch (Exception $e) {
  // If FPDF not available, continue without PDF for now
  $pdfFileName = null;
  // You'll need to install FPDF: composer require setasign/fpdf
  // Or download from https://www.fpdf.org/
}

/* Update inquiries status to 'quoted' and store quote data */
$updateCtx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

foreach ($quoteCourses as $qc) {
  $quoteData = [
    'status' => 'quoted',
    'quote_no' => $quoteNo,
    'quote_amount' => $qc['amount'],
    'quote_vat' => $qc['vat'],
    'quote_total' => $qc['total'],
    'quoted_at' => date('Y-m-d H:i:s'),
    'quoted_by' => $_SESSION['user']['id']
  ];
  
  if ($pdfFileName) {
    $quoteData['quote_pdf'] = $pdfFileName;
  }
  
  $updateCtx['http']['content'] = json_encode($quoteData);
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$qc['inquiry_id']}",
    false,
    stream_context_create($updateCtx)
  );
}

header('Location: ../../pages/inquiries.php?success=' . urlencode("Quote created successfully! Quote No: $quoteNo" . ($pdfFileName ? ' (PDF generated)' : ' (Note: Install FPDF for PDF generation)')));
exit;

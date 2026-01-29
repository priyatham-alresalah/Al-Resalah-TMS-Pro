<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

// Start output buffering
ob_start();

$file = $_GET['file'] ?? null;
$inquiryId = $_GET['inquiry_id'] ?? null;

$path = null;

// If inquiry_id is provided, fetch inquiry and get/generate PDF
if ($inquiryId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  $inquiry = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=*",
      false,
      $ctx
    ),
    true
  )[0] ?? null;

  if (!$inquiry) {
    ob_end_clean();
    die("Inquiry not found");
  }

  // Fetch quotation record (quote data is stored in quotations table)
  // Try multiple ways to find the quotation:
  // 1. By inquiry_id (direct link)
  // 2. By client_id and status (if inquiry was quoted)
  $quotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.$inquiryId&select=*&order=created_at.desc&limit=1",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  
  // If not found by inquiry_id, try to find any quotation for this client's quoted inquiries
  if (!$quotation && !empty($inquiry['client_id'])) {
    // Get all quoted inquiries for this client
    $quotedInquiries = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&status=eq.quoted&select=id&limit=10",
        false,
        $ctx
      ),
      true
    ) ?: [];
    
    if (!empty($quotedInquiries)) {
      // Try to find quotation for any of these inquiries
      $inquiryIds = array_column($quotedInquiries, 'id');
      $inquiryIdsStr = implode(',', array_map(function($id) { return "eq.$id"; }, array_slice($inquiryIds, 0, 10)));
      
      // Try first inquiry
      if (!empty($inquiryIds[0])) {
        $quotation = json_decode(
          @file_get_contents(
            SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.{$inquiryIds[0]}&select=*&order=created_at.desc&limit=1",
            false,
            $ctx
          ),
          true
        )[0] ?? null;
      }
    }
  }

  if (!$quotation) {
    ob_end_clean();
    http_response_code(404);
    die("Quotation not found for this inquiry. Please create a quote first by going to the inquiry and clicking 'Create Quote'.");
  }

  // Always regenerate PDF from quotation data to ensure correct values
  if (!function_exists('generateQuotePDF')) {
    require_once '../../includes/quote_pdf.php';
  }

  // Fetch client
  $client = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
      false,
      $ctx
    ),
    true
  )[0] ?? null;

  if (!$client) {
    ob_end_clean();
    die("Client not found");
  }

  if (!isFPDFAvailable()) {
    ob_end_clean();
    die("PDF generation not available. Please install FPDF library.");
  }

  // Get quotation data
  $quoteNo = $quotation['quotation_no'] ?? '';
  $quotationSubtotal = floatval($quotation['subtotal'] ?? 0);
  $quotationVat = floatval($quotation['vat'] ?? 0);
  $quotationTotal = floatval($quotation['total'] ?? 0);
  $grandTotal = round($quotationTotal, 2);

  // Validate quotation has valid amounts
  if ($quotationSubtotal <= 0 || $quotationTotal <= 0) {
    ob_end_clean();
    die("Quotation has invalid amounts (Subtotal: $quotationSubtotal, Total: $quotationTotal). Please recreate the quote.");
  }

  // Fetch all inquiries for this client that have status 'quoted'
  // These should be the inquiries that were part of this quote
  $quotedInquiries = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&status=eq.quoted&select=id,course_name&order=created_at.desc",
      false,
      $ctx
    ),
    true
  ) ?: [];

  // Build quote courses - distribute quotation totals across courses
  $quoteCourses = [];
  $numCourses = max(1, count($quotedInquiries));

  // Calculate VAT percentage from quotation
  $vatPercentage = 5; // Default
  if ($quotationSubtotal > 0 && $quotationVat > 0) {
    $vatPercentage = ($quotationVat / $quotationSubtotal) * 100;
  }

  if (count($quotedInquiries) > 0) {
    // Distribute totals evenly across courses
    $subtotalPerCourse = $quotationSubtotal / $numCourses;
    $vatAmountPerCourse = $quotationVat / $numCourses;
    $totalPerCourse = $quotationTotal / $numCourses;

    foreach ($quotedInquiries as $inq) {
      $quoteCourses[] = [
        'course_name' => $inq['course_name'],
        'candidates' => 1, // Default - we don't store this per inquiry
        'amount_per_candidate' => round($subtotalPerCourse, 2),
        'amount' => round($subtotalPerCourse, 2), // Subtotal before VAT (shows in "Amount" column)
        'vat' => round($vatPercentage, 2),
        'vat_amount' => round($vatAmountPerCourse, 2),
        'total' => round($totalPerCourse, 2) // Total including VAT
      ];
    }
  } else {
    // Fallback: single course from current inquiry
    $quoteCourses[] = [
      'course_name' => $inquiry['course_name'],
      'candidates' => 1,
      'amount_per_candidate' => round($quotationSubtotal, 2),
      'amount' => round($quotationSubtotal, 2), // Subtotal before VAT
      'vat' => round($vatPercentage, 2),
      'vat_amount' => round($quotationVat, 2),
      'total' => round($quotationTotal, 2) // Total including VAT
    ];
  }

  // Generate PDF
  try {
    $pdfFileName = generateQuotePDF(
      $quoteNo,
      $client['company_name'] ?? '',
      $client['email'] ?? '',
      $client['address'] ?? '',
      $quoteCourses,
      $grandTotal,
      ''
    );

    if ($pdfFileName) {
      $path = __DIR__ . "/../../uploads/quotes/" . $pdfFileName;
      error_log("PDF generated: $pdfFileName with Grand Total: $grandTotal");
    } else {
      error_log("PDF generation returned null");
    }
  } catch (Exception $e) {
    error_log("Error generating quote PDF: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
  }
} elseif ($file) {
  // Legacy support: file parameter
  $path = __DIR__ . "/../../uploads/quotes/" . basename($file);
}

if (!$path || !file_exists($path)) {
  ob_end_clean();
  die("Quote PDF not found or could not be generated. Please ensure the quote has been created with valid amounts.");
}

ob_end_clean();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="quote_' . basename($path) . '"');
readfile($path);
exit;

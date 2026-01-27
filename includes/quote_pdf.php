<?php
// Note: FPDF class should be available via require_once
// If FPDF is not available, install it: composer require setasign/fpdf
// Or download from https://www.fpdf.org/

// Try to require FPDF - adjust path if needed
if (file_exists(__DIR__ . '/fpdf.php')) {
  require_once __DIR__ . '/fpdf.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
  require_once __DIR__ . '/../vendor/autoload.php';
}

function generateQuotePDF(
  string $quoteNo,
  string $clientName,
  string $clientEmail,
  string $clientAddress,
  array $courses, // [['course_name' => '', 'amount' => 0, 'vat' => 0, 'total' => 0]]
  float $grandTotal,
  string $notes = ''
): string {
  
  if (!file_exists(__DIR__ . '/../uploads/quotes')) {
    mkdir(__DIR__ . '/../uploads/quotes', 0777, true);
  }

  $fileName = "quote_$quoteNo.pdf";
  $filePath = __DIR__ . "/../uploads/quotes/$fileName";

  // Check if FPDF class exists
  if (!class_exists('FPDF')) {
    throw new Exception('FPDF class not found. Please install FPDF library.');
  }
  
  $pdf = new FPDF('P', 'mm', 'A4');
  $pdf->AddPage();

  /* Header */
  $pdf->SetFont('Arial', 'B', 20);
  $pdf->Cell(0, 15, 'TRAINING QUOTATION', 0, 1, 'C');
  $pdf->Ln(5);

  /* Quote Number and Date */
  $pdf->SetFont('Arial', '', 12);
  $pdf->Cell(0, 8, "Quote No: $quoteNo", 0, 1, 'L');
  $pdf->Cell(0, 8, "Date: " . date('d M Y'), 0, 1, 'L');
  $pdf->Ln(5);

  /* Client Details */
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(0, 8, 'To:', 0, 1, 'L');
  $pdf->SetFont('Arial', '', 11);
  $pdf->Cell(0, 6, $clientName, 0, 1, 'L');
  if ($clientEmail) {
    $pdf->Cell(0, 6, "Email: $clientEmail", 0, 1, 'L');
  }
  if ($clientAddress) {
    $pdf->Cell(0, 6, "Address: $clientAddress", 0, 1, 'L');
  }
  $pdf->Ln(10);

  /* Courses Table */
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->Cell(100, 8, 'Course Name', 1, 0, 'L');
  $pdf->Cell(30, 8, 'Amount', 1, 0, 'R');
  $pdf->Cell(30, 8, 'VAT', 1, 0, 'R');
  $pdf->Cell(30, 8, 'Total', 1, 1, 'R');

  $pdf->SetFont('Arial', '', 10);
  foreach ($courses as $course) {
    $pdf->Cell(100, 8, $course['course_name'], 1, 0, 'L');
    $pdf->Cell(30, 8, number_format($course['amount'], 2), 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($course['vat'], 2) . '%', 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($course['total'], 2), 1, 1, 'R');
  }

  /* Grand Total */
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(160, 8, 'Grand Total:', 1, 0, 'R');
  $pdf->Cell(30, 8, number_format($grandTotal, 2), 1, 1, 'R');
  $pdf->Ln(10);

  /* Notes */
  if ($notes) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Notes:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, $notes, 0, 'L');
    $pdf->Ln(5);
  }

  /* Footer */
  $pdf->SetY(-30);
  $pdf->SetFont('Arial', 'I', 9);
  $pdf->Cell(0, 6, 'Thank you for your inquiry. We look forward to serving you.', 0, 1, 'C');
  $pdf->Cell(0, 6, APP_NAME, 0, 1, 'C');

  $pdf->Output('F', $filePath);
  return $fileName;
}

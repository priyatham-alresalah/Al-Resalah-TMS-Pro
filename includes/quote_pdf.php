<?php
/**
 * Quote PDF Generator
 * Generates PDF quotations for clients
 */

// Load PDF library
require_once __DIR__ . '/pdf_library.php';

if (!function_exists('generateQuotePDF')) {
function generateQuotePDF(
  string $quoteNo,
  string $clientName,
  string $clientEmail,
  string $clientAddress,
  array $courses, // [['course_name' => '', 'amount' => 0, 'vat' => 0, 'total' => 0]]
  float $grandTotal,
  string $notes = ''
): ?string {
  
  // Check if FPDF class exists first
  if (!class_exists('FPDF')) {
    error_log("FPDF class not found. PDF generation skipped.");
    return null;
  }
  
  if (!file_exists(__DIR__ . '/../uploads/quotes')) {
    mkdir(__DIR__ . '/../uploads/quotes', 0777, true);
  }

  $fileName = "quote_$quoteNo.pdf";
  $filePath = __DIR__ . "/../uploads/quotes/$fileName";
  
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
  $pdf->Cell(70, 8, 'Course Name', 1, 0, 'L');
  $pdf->Cell(25, 8, 'Candidates', 1, 0, 'C');
  $pdf->Cell(30, 8, 'Amount', 1, 0, 'R');
  $pdf->Cell(25, 8, 'VAT', 1, 0, 'R');
  $pdf->Cell(30, 8, 'Total', 1, 1, 'R');

  $pdf->SetFont('Arial', '', 10);
  foreach ($courses as $course) {
    $candidates = $course['candidates'] ?? 1;
    $amountPerCandidate = $course['amount_per_candidate'] ?? $course['amount'];
    $amount = $course['amount'] ?? 0;
    
    $pdf->Cell(70, 8, substr($course['course_name'], 0, 35), 1, 0, 'L');
    $pdf->Cell(25, 8, $candidates, 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($amount, 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($course['vat'], 2) . '%', 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($course['total'], 2), 1, 1, 'R');
  }

  /* Grand Total */
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(150, 8, 'Grand Total:', 1, 0, 'R');
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
} // End function_exists check

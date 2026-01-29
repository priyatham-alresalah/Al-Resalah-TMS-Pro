<?php
/**
 * Invoice PDF Generator
 * Generates PDF invoices for clients
 */

// Load PDF library
require_once __DIR__ . '/pdf_library.php';

function generateInvoicePDF(
  string $invoiceNo,
  string $clientName,
  string $clientEmail,
  string $clientAddress,
  float $amount,
  float $vat,
  float $total,
  string $issuedDate,
  ?string $dueDate = null
): ?string {
  
  if (!file_exists(__DIR__ . '/../uploads/invoices')) {
    mkdir(__DIR__ . '/../uploads/invoices', 0777, true);
  }

  $fileName = "invoice_$invoiceNo.pdf";
  $filePath = __DIR__ . "/../uploads/invoices/$fileName";

  // Check if FPDF is available
  if (!isFPDFAvailable()) {
    error_log("FPDF library not found. Invoice PDF generation skipped.");
    return null;
  }
  
  $pdf = new FPDF('P', 'mm', 'A4');
  $pdf->AddPage();

  /* Header */
  $pdf->SetFont('Arial', 'B', 20);
  $pdf->Cell(0, 15, 'INVOICE', 0, 1, 'C');
  $pdf->Ln(5);

  /* Invoice Number and Date */
  $pdf->SetFont('Arial', '', 12);
  $pdf->Cell(0, 8, "Invoice No: $invoiceNo", 0, 1, 'L');
  $pdf->Cell(0, 8, "Date: " . date('d M Y', strtotime($issuedDate)), 0, 1, 'L');
  if ($dueDate) {
    $pdf->Cell(0, 8, "Due Date: " . date('d M Y', strtotime($dueDate)), 0, 1, 'L');
  }
  $pdf->Ln(5);

  /* Client Details */
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(0, 8, 'Bill To:', 0, 1, 'L');
  $pdf->SetFont('Arial', '', 11);
  $pdf->Cell(0, 6, $clientName, 0, 1, 'L');
  if ($clientEmail) {
    $pdf->Cell(0, 6, "Email: $clientEmail", 0, 1, 'L');
  }
  if ($clientAddress) {
    $pdf->Cell(0, 6, "Address: $clientAddress", 0, 1, 'L');
  }
  $pdf->Ln(10);

  /* Amount Table */
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->Cell(100, 8, 'Description', 1, 0, 'L');
  $pdf->Cell(45, 8, 'Amount', 1, 0, 'R');
  $pdf->Cell(45, 8, 'VAT', 1, 1, 'R');
  
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(100, 8, 'Training Services', 1, 0, 'L');
  $pdf->Cell(45, 8, number_format($amount, 2), 1, 0, 'R');
  $pdf->Cell(45, 8, number_format($vat, 2), 1, 1, 'R');
  
  $pdf->Ln(5);
  
  /* Total */
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(145, 8, 'Total:', 0, 0, 'R');
  $pdf->Cell(45, 8, number_format($total, 2), 1, 1, 'R');

  $pdf->Ln(20);

  /* Footer */
  $pdf->SetFont('Arial', 'I', 10);
  $pdf->Cell(0, 8, 'Thank you for your business!', 0, 1, 'C');

  $pdf->Output('F', $filePath);
  
  return $fileName;
}

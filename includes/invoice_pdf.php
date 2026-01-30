<?php
/**
 * Invoice PDF Generator - Upgraded Professional Version
 * Generates professional PDF invoices with watermark background
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
  ?string $dueDate = null,
  ?array $lineItems = null,
  ?string $clientTRN = null,
  ?string $paymentTerms = 'Net 30'
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

  // Company Information (from config or defaults)
  $companyName = "Al Resalah Consultancies & Training - Abu Dhabi Branch HSE";
  $companyAddress = "PO Box 132950 Al Fahim Building, 10th Street, Mussafah 4, Abu Dhabi UAE AE";
  $companyPhone = "+971544480417";
  $companyEmail = "info@aresalah.com";
  $companyTRN = "TRN 100231717800003";
  
  // Bank Account Details
  $bankAccountName = "Al Resalah Consultancies and Training Sole Proprietorship LLC";
  $bankAccount = "729897244001";
  $bankIBAN = "AE490030000729897244001";
  $bankSwift = "ADCBAEAA";
  $bankName = "Abu Dhabi Commercial Bank-Abu Dhabi Branch";

  // Logo path (if exists)
  $logoPath = __DIR__ . '/../assets/images/logo.png';
  $hasLogo = file_exists($logoPath);

  // ============================================
  // BACKGROUND WATERMARK (Very Light & Transparent)
  // ============================================
  if ($hasLogo) {
    // Save current position
    $currentX = $pdf->GetX();
    $currentY = $pdf->GetY();
    
    // Calculate center position for watermark
    $watermarkX = 105; // Center of A4 (210mm / 2)
    $watermarkY = 148.5; // Center of A4 (297mm / 2)
    $watermarkSize = 100; // Large size for watermark effect
    
    // Place logo as watermark (centered, large, very transparent)
    // Note: For true transparency, use a pre-processed PNG with alpha channel
    // FPDF will render it, but for better effect, the logo image itself should be semi-transparent
    try {
      // Place watermark at center, slightly rotated for professional look
      // Using larger size but the image itself should be semi-transparent PNG
      $pdf->Image($logoPath, $watermarkX - ($watermarkSize/2), $watermarkY - ($watermarkSize/2), $watermarkSize, $watermarkSize);
      
      // Alternative: Draw a very light rectangle behind logo for extra transparency effect
      $pdf->SetFillColor(250, 250, 250); // Very light gray
      $pdf->Rect($watermarkX - ($watermarkSize/2) - 5, $watermarkY - ($watermarkSize/2) - 5, $watermarkSize + 10, $watermarkSize + 10, 'F');
      
      // Redraw logo on top
      $pdf->Image($logoPath, $watermarkX - ($watermarkSize/2), $watermarkY - ($watermarkSize/2), $watermarkSize, $watermarkSize);
    } catch (Exception $e) {
      // If image fails, continue without watermark
      error_log("Watermark image error: " . $e->getMessage());
    }
    
    // Reset colors
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    
    // Restore position
    $pdf->SetXY($currentX, $currentY);
  }

  // ============================================
  // TOP SECTION: Company Info (Left) & Logo (Right)
  // ============================================
  $pdf->SetY(15);
  
  // Company Details (Left Side)
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->SetX(10);
  $pdf->Cell(100, 7, $companyName, 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(10);
  $pdf->Cell(100, 5, $companyAddress, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(100, 5, $companyPhone . ' | ' . $companyEmail, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(100, 5, $companyTRN, 0, 1, 'L');
  
  // Logo (Right Side) - Top Right
  if ($hasLogo) {
    try {
      $pdf->Image($logoPath, 150, 15, 50, 0); // Auto height, 50mm width
    } catch (Exception $e) {
      error_log("Logo image error: " . $e->getMessage());
    }
  }
  
  $pdf->Ln(8);

  // ============================================
  // INVOICE TITLE & DETAILS
  // ============================================
  $pdf->SetY(50);
  
  // "Tax Invoice" Title (Left)
  $pdf->SetFont('Arial', 'B', 18);
  $pdf->SetTextColor(139, 69, 19); // Brownish color
  $pdf->SetX(10);
  $pdf->Cell(100, 10, 'Tax Invoice', 0, 1, 'L');
  $pdf->SetTextColor(0, 0, 0); // Reset to black
  
  // Invoice Details (Right)
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetX(120);
  $pdf->Cell(40, 6, 'INVOICE', 0, 0, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(40, 6, $invoiceNo, 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(40, 6, 'DATE', 0, 0, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(40, 6, date('m/d/Y', strtotime($issuedDate)), 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(40, 6, 'TERMS', 0, 0, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(40, 6, $paymentTerms, 0, 1, 'R');
  
  if ($dueDate) {
    $pdf->SetX(120);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'DUE DATE', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 6, date('m/d/Y', strtotime($dueDate)), 0, 1, 'R');
  }
  
  $pdf->Ln(5);

  // ============================================
  // BILL TO SECTION
  // ============================================
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->SetX(10);
  $pdf->Cell(100, 7, 'BILL TO', 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 10);
  $pdf->SetX(10);
  $pdf->Cell(100, 5, $clientName, 0, 1, 'L');
  
  if ($clientAddress) {
    $pdf->SetX(10);
    $pdf->MultiCell(100, 5, $clientAddress, 0, 'L');
  }
  
  if ($clientEmail) {
    $pdf->SetX(10);
    $pdf->Cell(100, 5, 'Email: ' . $clientEmail, 0, 1, 'L');
  }
  
  if ($clientTRN) {
    $pdf->SetX(10);
    $pdf->Cell(100, 5, 'TRN: ' . $clientTRN, 0, 1, 'L');
  }
  
  $pdf->Ln(8);

  // ============================================
  // LINE ITEMS TABLE
  // ============================================
  $tableY = $pdf->GetY();
  
  // Table Header (Light Pink/Purple Background)
  $pdf->SetFillColor(230, 200, 220); // Light pink/purple
  $pdf->SetTextColor(255, 255, 255); // White text
  $pdf->SetFont('Arial', 'B', 10);
  
  $pdf->SetX(10);
  $pdf->Cell(100, 8, 'DESCRIPTION', 1, 0, 'L', true);
  $pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'RATE', 1, 0, 'R', true);
  $pdf->Cell(30, 8, 'AMOUNT', 1, 0, 'R', true);
  $pdf->Cell(20, 8, 'VAT', 1, 1, 'R', true);
  
  // Reset colors
  $pdf->SetTextColor(0, 0, 0);
  $pdf->SetFont('Arial', '', 10);
  
  // Line Items
  if ($lineItems && count($lineItems) > 0) {
    foreach ($lineItems as $item) {
      $desc = $item['description'] ?? 'Training Services';
      $qty = $item['quantity'] ?? 1;
      $rate = $item['rate'] ?? $amount;
      $itemAmount = $item['amount'] ?? $amount;
      $itemVAT = $item['vat'] ?? $vat;
      
      $pdf->SetX(10);
      $pdf->Cell(100, 8, substr($desc, 0, 50), 1, 0, 'L');
      $pdf->Cell(20, 8, $qty, 1, 0, 'C');
      $pdf->Cell(30, 8, number_format($rate, 2), 1, 0, 'R');
      $pdf->Cell(30, 8, number_format($itemAmount, 2), 1, 0, 'R');
      $pdf->Cell(20, 8, number_format($itemVAT, 2), 1, 1, 'R');
      
      // Dotted line separator
      $pdf->SetX(10);
      $pdf->SetDash(1, 1); // Dotted line
      $pdf->Line(10, $pdf->GetY(), 210, $pdf->GetY());
      $pdf->SetDash(); // Reset
      $pdf->Ln(1);
    }
  } else {
    // Default single line item
    $pdf->SetX(10);
    $pdf->Cell(100, 8, 'Training Services', 1, 0, 'L');
    $pdf->Cell(20, 8, '1', 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($amount, 2), 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($amount, 2), 1, 0, 'R');
    $pdf->Cell(20, 8, number_format($vat, 2), 1, 1, 'R');
  }
  
  $pdf->Ln(5);

  // ============================================
  // FINANCIAL SUMMARY (Right Side)
  // ============================================
  $summaryY = $pdf->GetY();
  $pdf->SetX(120);
  
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(50, 6, 'SUBTOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($amount, 2), 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->Cell(50, 6, 'VAT TOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($vat, 2), 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(50, 6, 'TOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($total, 2), 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(50, 8, 'BALANCE DUE', 0, 0, 'L');
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->Cell(30, 8, 'AED ' . number_format($total, 2), 0, 1, 'R');
  
  $pdf->Ln(10);

  // ============================================
  // VAT SUMMARY TABLE (Bottom Left)
  // ============================================
  $vatTableY = $pdf->GetY();
  
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetX(10);
  $pdf->Cell(0, 6, 'VAT SUMMARY', 0, 1, 'L');
  
  // VAT Table Header
  $pdf->SetFillColor(245, 245, 245);
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->SetX(10);
  $pdf->Cell(40, 6, 'RATE', 1, 0, 'L', true);
  $pdf->Cell(40, 6, 'VAT', 1, 0, 'R', true);
  $pdf->Cell(40, 6, 'NET', 1, 1, 'R', true);
  
  // VAT Row
  $vatRate = $amount > 0 ? ($vat / $amount) * 100 : 0;
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(10);
  $pdf->Cell(40, 6, 'VAT @ ' . number_format($vatRate, 1) . '%', 1, 0, 'L');
  $pdf->Cell(40, 6, number_format($vat, 2), 1, 0, 'R');
  $pdf->Cell(40, 6, number_format($amount, 2), 1, 1, 'R');
  
  $pdf->Ln(8);

  // ============================================
  // BANK ACCOUNT DETAILS (Bottom Left)
  // ============================================
  $bankY = $pdf->GetY();
  
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetX(10);
  $pdf->Cell(0, 6, 'Bank Account Details', 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(10);
  $pdf->Cell(0, 5, 'Account Name: ' . $bankAccountName, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(0, 5, 'Bank Account: ' . $bankAccount, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(0, 5, 'IBAN: ' . $bankIBAN, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(0, 5, 'Swift Code: ' . $bankSwift, 0, 1, 'L');
  $pdf->SetX(10);
  $pdf->Cell(0, 5, 'Bank: ' . $bankName, 0, 1, 'L');

  // ============================================
  // COMPANY STAMP (Bottom Right - Watermark Style)
  // ============================================
  // Place a circular stamp-like watermark at bottom right
  $stampY = 250; // Near bottom
  $stampX = 150; // Right side
  
  // Draw circular border for stamp
  $pdf->SetLineWidth(0.5);
  $pdf->SetDrawColor(0, 0, 150); // Dark blue
  $pdf->Circle($stampX, $stampY, 20); // 20mm radius
  
  // Stamp text (very light, transparent)
  $pdf->SetTextColor(0, 0, 150);
  $pdf->SetFont('Arial', 'B', 7);
  $pdf->SetXY($stampX - 18, $stampY - 8);
  $pdf->Cell(36, 4, 'ALRESALAH CONSULTANCIES', 0, 0, 'C');
  $pdf->SetXY($stampX - 18, $stampY - 4);
  $pdf->Cell(36, 4, '& TRAINING L.L.C', 0, 0, 'C');
  $pdf->SetXY($stampX - 18, $stampY);
  $pdf->SetFont('Arial', '', 6);
  $pdf->Cell(36, 4, 'SOLE PROPRIETORSHIP', 0, 0, 'C');
  $pdf->SetXY($stampX - 18, $stampY + 4);
  $pdf->Cell(36, 4, 'P.O.Box: 132950', 0, 0, 'C');
  $pdf->SetXY($stampX - 18, $stampY + 8);
  $pdf->Cell(36, 4, 'Abu Dhabi, U.A.E', 0, 0, 'C');
  
  // Reset colors
  $pdf->SetTextColor(0, 0, 0);
  $pdf->SetDrawColor(0, 0, 0);
  $pdf->SetLineWidth(0.2);

  // ============================================
  // FOOTER
  // ============================================
  $pdf->SetY(-20);
  $pdf->SetFont('Arial', 'I', 9);
  $pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
  $pdf->Cell(0, 5, $companyName, 0, 1, 'C');

  $pdf->Output('F', $filePath);
  
  return $fileName;
}

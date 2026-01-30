<?php
/**
 * Quote PDF Generator - Professional Invoice-Style Design
 * Generates PDF quotations for clients with header/footer on all pages
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
  
  // Company Information
  $companyName = defined('APP_NAME') ? APP_NAME : "Al Resalah Consultancies & Training";
  $companyAddress = "PO Box 132950 Al Fahim Building, 10th Street, Mussafah 4, Abu Dhabi UAE";
  $companyPhone = "+971544480417";
  $companyEmail = "info@aresalah.com";
  
  // Logo path
  $logoPath = __DIR__ . '/../assets/images/logo.png';
  $hasLogo = file_exists($logoPath);
  
  // Create custom PDF class with header/footer
  class QuotePDF extends FPDF {
    private $companyName;
    private $logoPath;
    private $hasLogo;
    
    public function __construct($companyName, $logoPath, $hasLogo) {
      parent::__construct('P', 'mm', 'A4');
      $this->companyName = $companyName;
      $this->logoPath = $logoPath;
      $this->hasLogo = $hasLogo;
    }
    
    // Page header
    function Header() {
      // Header background color (light blue/gray) - set draw color to match fill to avoid border line
      $this->SetFillColor(240, 245, 250);
      $this->SetDrawColor(240, 245, 250); // Match fill color to prevent visible border
      $this->Rect(0, 0, 210, 40, 'FD'); // FD = Fill and Draw with same color (no visible border)
      
      // Logo (Left side) - improved placement and sizing to match website
      if ($this->hasLogo) {
        try {
          // Position logo: 10mm from left, 5mm from top, 40mm width (larger, more prominent)
          $this->Image($this->logoPath, 10, 5, 40, 0); // Auto height, 40mm width
        } catch (Exception $e) {
          error_log("Logo image error: " . $e->getMessage());
        }
      }
      
      // Company Name (Right side of logo area) - improved positioning
      $this->SetXY(55, 8);
      $this->SetFont('Arial', 'B', 16);
      $this->SetTextColor(0, 0, 0);
      $this->Cell(0, 7, $this->companyName, 0, 1, 'L');
      
      // Company Address (below name)
      $this->SetXY(55, 17);
      $this->SetFont('Arial', '', 9);
      $this->SetTextColor(100, 100, 100);
      $this->Cell(0, 5, 'PO Box 132950 Al Fahim Building, 10th Street, Mussafah 4, Abu Dhabi UAE', 0, 1, 'L');
      
      // Contact info
      $this->SetXY(55, 24);
      $this->SetFont('Arial', '', 9);
      $this->Cell(0, 5, 'Phone: +971544480417 | Email: info@aresalah.com', 0, 1, 'L');
      
      // Header line separator (below header content, not at top)
      $this->SetLineWidth(0.5);
      $this->SetDrawColor(200, 200, 200);
      $this->Line(10, 35, 200, 35);
      
      // Reset colors
      $this->SetTextColor(0, 0, 0);
      $this->SetDrawColor(0, 0, 0);
      $this->SetLineWidth(0.2);
      
      // Set top margin for content
      $this->SetY(45);
    }
    
    // Page footer
    function Footer() {
      // Footer line separator
      $this->SetLineWidth(0.3);
      $this->SetDrawColor(200, 200, 200);
      $this->Line(10, 280, 200, 280);
      
      // Position at 1.5 cm from bottom
      $this->SetY(-15);
      $this->SetFont('Arial', 'I', 9);
      $this->SetTextColor(100, 100, 100);
      
      // Footer text
      $this->Cell(0, 5, 'Thank you for your inquiry. We look forward to serving you.', 0, 0, 'C');
      $this->Ln(5);
      $this->SetFont('Arial', 'B', 10);
      $this->SetTextColor(0, 0, 0);
      $this->Cell(0, 5, $this->companyName, 0, 0, 'C');
      
      // Page number
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(150, 150, 150);
      $this->SetXY(10, -10);
      $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'L');
      
      // Reset colors
      $this->SetTextColor(0, 0, 0);
    }
  }
  
  $pdf = new QuotePDF($companyName, $logoPath, $hasLogo);
  $pdf->AliasNbPages(); // For page numbering
  $pdf->AddPage();

  // ============================================
  // QUOTATION TITLE & DETAILS
  // ============================================
  $pdf->SetY(50);
  
  // "Training Quotation" Title (Left)
  $pdf->SetFont('Arial', 'B', 18);
  $pdf->SetTextColor(139, 69, 19); // Brownish color (matching invoice style)
  $pdf->SetX(10);
  $pdf->Cell(100, 10, 'Training Quotation', 0, 1, 'L');
  $pdf->SetTextColor(0, 0, 0); // Reset to black
  
  // Quote Details (Right)
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetX(120);
  $pdf->Cell(40, 6, 'QUOTE NO', 0, 0, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(40, 6, $quoteNo, 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(40, 6, 'DATE', 0, 0, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(40, 6, date('d M Y'), 0, 1, 'R');
  
  $pdf->Ln(8);

  // ============================================
  // CLIENT DETAILS SECTION
  // ============================================
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->SetX(10);
  $pdf->Cell(100, 7, 'QUOTE TO', 0, 1, 'L');
  
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
  
  $pdf->Ln(10);

  // ============================================
  // COURSES TABLE
  // ============================================
  
  // Table Header (Light Blue/Purple Background - matching invoice style)
  $pdf->SetFillColor(230, 200, 220); // Light pink/purple
  $pdf->SetTextColor(255, 255, 255); // White text
  $pdf->SetFont('Arial', 'B', 10);
  
  $pdf->SetX(10);
  $pdf->Cell(80, 8, 'COURSE NAME', 1, 0, 'L', true);
  $pdf->Cell(25, 8, 'CANDIDATES', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'AMOUNT', 1, 0, 'R', true);
  $pdf->Cell(25, 8, 'VAT %', 1, 0, 'R', true);
  $pdf->Cell(30, 8, 'TOTAL', 1, 1, 'R', true);
  
  // Reset colors
  $pdf->SetTextColor(0, 0, 0);
  $pdf->SetFont('Arial', '', 10);
  
  // Course rows
  foreach ($courses as $course) {
    $candidates = $course['candidates'] ?? 1;
    $amountPerCandidate = $course['amount_per_candidate'] ?? $course['amount'];
    $amount = $course['amount'] ?? 0;
    $vatPercent = $course['vat'] ?? 5;
    $total = $course['total'] ?? 0;
    
    $pdf->SetX(10);
    $pdf->Cell(80, 8, substr($course['course_name'], 0, 40), 1, 0, 'L');
    $pdf->Cell(25, 8, $candidates, 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($amount, 2) . ' AED', 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($vatPercent, 1) . '%', 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($total, 2) . ' AED', 1, 1, 'R');
    
    // Check if we need a new page
    if ($pdf->GetY() > 250) {
      $pdf->AddPage();
    }
  }
  
  $pdf->Ln(5);

  // ============================================
  // GRAND TOTAL (Right Side)
  // ============================================
  $summaryY = $pdf->GetY();
  
  // Calculate subtotal and VAT total
  $subtotal = 0;
  $vatTotal = 0;
  foreach ($courses as $course) {
    $amount = $course['amount'] ?? 0;
    $vatAmount = ($course['total'] ?? 0) - $amount;
    $subtotal += $amount;
    $vatTotal += $vatAmount;
  }
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(50, 6, 'SUBTOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($subtotal, 2) . ' AED', 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->Cell(50, 6, 'VAT TOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($vatTotal, 2) . ' AED', 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(50, 6, 'TOTAL', 0, 0, 'L');
  $pdf->Cell(30, 6, number_format($grandTotal, 2) . ' AED', 0, 1, 'R');
  
  $pdf->SetX(120);
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->SetTextColor(139, 69, 19); // Brownish color
  $pdf->Cell(50, 8, 'GRAND TOTAL', 0, 0, 'L');
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->Cell(30, 8, 'AED ' . number_format($grandTotal, 2), 0, 1, 'R');
  $pdf->SetTextColor(0, 0, 0); // Reset to black
  
  $pdf->Ln(10);

  // ============================================
  // NOTES SECTION
  // ============================================
  if ($notes) {
    // Check if we need a new page
    if ($pdf->GetY() > 240) {
      $pdf->AddPage();
    }
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetX(10);
    $pdf->Cell(0, 8, 'Notes:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX(10);
    $pdf->MultiCell(0, 6, $notes, 0, 'L');
    $pdf->Ln(5);
  }

  // ============================================
  // TERMS & CONDITIONS (if needed)
  // ============================================
  // Check if we need a new page for terms
  if ($pdf->GetY() > 240) {
    $pdf->AddPage();
  }
  
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetX(10);
  $pdf->Cell(0, 6, 'Terms & Conditions:', 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(10);
  $terms = "• This quotation is valid for 30 days from the date of issue.\n";
  $terms .= "• Payment terms: Net 30 days from invoice date.\n";
  $terms .= "• Training schedule will be confirmed upon acceptance of this quotation.\n";
  $terms .= "• All prices are in AED and inclusive of VAT where applicable.";
  
  $pdf->MultiCell(0, 5, $terms, 0, 'L');
  
  $pdf->Output('F', $filePath);
  return $fileName;
}
} // End function_exists check

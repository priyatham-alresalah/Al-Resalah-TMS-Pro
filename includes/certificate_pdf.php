<?php
/**
 * Certificate PDF Generator - TCPDF with Unicode + RTL + Arabic Support
 * Generates professional PDF certificates with proper Arabic rendering
 * 
 * MIGRATED FROM FPDF TO TCPDF for Unicode/RTL/Arabic support
 * All coordinates, colors, sizes, and text content remain FROZEN
 */

// Load TCPDF library (replaces FPDF)
// Ensure composer autoload is loaded first for TCPDF
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
  require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/tcpdf_loader.php';
// TCPDF barcode classes for QR code generation
if (file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php')) {
  require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
}
require_once __DIR__ . '/qr_generator.php';

function generateCertificatePDF(
  string $candidateName,
  string $certNo,
  string $trainingTitle,
  string $clientName,
  string $qrPath = '',
  ?string $trainingDate = null,
  ?string $issueDate = null,
  ?string $validityDate = null,
  ?string $employeeNo = null,
  ?string $location = 'Abu Dhabi, United Arab Emirates',
  bool $autoGenerateQR = true
): ?string {
  
  try {
    // Check if TCPDF is available
    if (!isTCPDFAvailable()) {
      $errorMsg = "TCPDF library not found. Certificate PDF generation requires TCPDF.\n";
      $errorMsg .= "Installation options:\n";
      $errorMsg .= "1. Composer: composer require tecnickcom/tcpdf\n";
      $errorMsg .= "2. Manual: Download from https://github.com/tecnickcom/TCPDF and extract to includes/tcpdf/\n";
      error_log($errorMsg);
      throw new Exception("TCPDF library not installed. Please install TCPDF to generate certificates. See logs for details.");
    }

  $fileName = "certificate_$certNo.pdf";
  $filePath = __DIR__ . "/../uploads/certificates/$fileName";

  // Ensure directory exists
  if (!file_exists(dirname($filePath))) {
    mkdir(dirname($filePath), 0777, true);
  }

  // Create TCPDF instance - Landscape A4: 297mm x 210mm
  // TCPDF constructor: (orientation, unit, format, unicode, encoding, diskcache, pdfa)
  try {
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
  } catch (Exception $e) {
    error_log("TCPDF instantiation failed: " . $e->getMessage());
    throw new Exception("Failed to initialize TCPDF: " . $e->getMessage());
  }
  
  $pdf->SetCreator('Training Management System');
  $pdf->SetAuthor('Al Resalah Consultancies & Training');
  $pdf->SetTitle('Certificate');
  $pdf->SetSubject('Training Certificate');
  
  // Remove default header/footer completely
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  
  // Set margins to zero for absolute positioning
  $pdf->SetMargins(0, 0, 0);
  $pdf->SetAutoPageBreak(false, 0);
  
  // Disable TCPDF default footer text completely
  $pdf->setFooterData(array(0, 0, 0), array(0, 0, 0));
  $pdf->setFooterFont(array('helvetica', '', 0));
  $pdf->setFooterMargin(0);
  
  // Add page
  $pdf->AddPage();

  // Company Information (FROZEN - no changes)
  $companyName = "AL RESALAH CONSULTANCIES & TRAINING";
  $companyArabic = "الرسالة للإستشارات والتدريب";
  $companyPhone = "+971 2 559 1123";
  $companyPhoneDubai = "+971 4 397 0004";
  $companyEmail = "info@aresalah.com";
  $companyPOBox = "132950";
  
  // Logo: only use when GD or Imagick is available (safe for cPanel/production)
  // TCPDF requires GD or Imagick for PNG/alpha; without it, Image() throws and breaks PDF output
  $hasImageSupport = extension_loaded('gd') || extension_loaded('imagick');
  $logoPath = __DIR__ . '/../assets/images/logo.png';
  $hasLogo = $hasImageSupport && file_exists($logoPath);

  // White background (landscape 297 x 210 mm) - FROZEN coordinates
  $pdf->SetFillColor(255, 255, 255);
  $pdf->Rect(0, 0, 297, 210, 'F');

  // ============================================
  // BORDER — Exact design from reference
  // Multi-lined black border + white lines + black corner ornaments
  // ============================================
  $margin = 5;
  $inner = 8;

  // 1. Thick outer black line
  $pdf->SetLineWidth(1.2);
  $pdf->SetDrawColor(0, 0, 0);
  $pdf->Rect($margin, $margin, 297 - 2*$margin, 210 - 2*$margin, 'D');

  // 2. Thinner white line
  $pdf->SetLineWidth(0.4);
  $pdf->SetDrawColor(255, 255, 255);
  $pdf->Rect($margin + 1.5, $margin + 1.5, 297 - 2*$margin - 3, 210 - 2*$margin - 3, 'D');

  // 3. Thin black line
  $pdf->SetLineWidth(0.35);
  $pdf->SetDrawColor(0, 0, 0);
  $pdf->Rect($margin + 2.2, $margin + 2.2, 297 - 2*$margin - 4.4, 210 - 2*$margin - 4.4, 'D');

  // 4. Thinnest white line (innermost)
  $pdf->SetLineWidth(0.25);
  $pdf->SetDrawColor(255, 255, 255);
  $pdf->Rect($margin + 2.7, $margin + 2.7, 297 - 2*$margin - 5.4, 210 - 2*$margin - 5.4, 'D');

  // Corner ornaments removed — clean border only

  $pdf->SetDrawColor(0, 0, 0);
  $pdf->SetFillColor(255, 255, 255);
  $pdf->SetLineWidth(0.2);

  // ============================================
  // HEADER — Professional Layout with Proper Spacing
  // ============================================
  $headerY = $margin + 15; // 35mm from top
  
  // Logo only when safe (GD/Imagick available) — production-safe: no Image() call otherwise
  if ($hasLogo) {
    try {
      $logoX = 297 - $margin - 50;
      $logoY = $headerY;
      $logoW = 40;
      $pdf->Image($logoPath, $logoX, $logoY, $logoW, 0, '', '', '', false, 300, '', false, false, 0);
      $logoLeftX = $margin + 12;
      $logoLeftY = $headerY - 2;
      $logoLeftW = 28;
      $pdf->Image($logoPath, $logoLeftX, $logoLeftY, $logoLeftW, 0, '', '', '', false, 300, '', false, false, 0);
    } catch (Throwable $e) {
      error_log("Certificate logo error: " . $e->getMessage());
    }
  }

  // Arabic company name - dark blue matching reference EXACTLY
  $pdf->SetTextColor(0, 47, 95); // Dark blue (RGB from reference)
  
  $arabicFont = 'dejavusans';
  $arabicFontPath = __DIR__ . '/fonts/Amiri-Regular.ttf';
  if (file_exists($arabicFontPath)) {
    try {
      $pdf->AddFont('Amiri', '', $arabicFontPath, true, false);
      $arabicFont = 'Amiri';
    } catch (Exception $e) {
      error_log("Failed to add Arabic font: " . $e->getMessage());
    }
  }
  
  $pdf->SetRTL(true);
  $pdf->SetFont($arabicFont, 'B', 20); // Large bold matching reference
  $pdf->SetXY(0, $headerY);
  $pdf->Cell(297, 10, $companyArabic, 0, 1, 'C', false, '', 0, false, 'T', 'M');
  $pdf->SetRTL(false);
  
  // English company name - brown/gold matching reference EXACTLY
  $pdf->SetFont('helvetica', 'B', 15); // Large bold serif-like matching reference
  $pdf->SetTextColor(170, 107, 48); // Rich brown/gold (RGB from reference)
  $pdf->SetXY(0, $headerY + 10); // Exact spacing matching reference
  $pdf->Cell(297, 8, $companyName, 0, 1, 'C', false, '', 0, false, 'T', 'M');

  // ============================================
  // CERTIFICATION TITLE — Exact Placement
  // ============================================
  $titleY = 42; // Exact Y position matching reference
  $pdf->SetY($titleY);
  $pdf->SetFont('helvetica', 'B', 34); // Larger size matching reference
  $pdf->SetTextColor(198, 163, 67); // Gold/yellowish matching reference (not bright yellow)
  $pdf->Cell(297, 14, 'Certification', 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  // "This is to certify that" - exact spacing
  $pdf->SetFont('helvetica', '', 12);
  $pdf->SetTextColor(0, 0, 0);
  $pdf->Cell(297, 6, 'This is to certify that', 0, 1, 'C', false, '', 0, false, 'T', 'M');

  // ============================================
  // CANDIDATE NAME — Exact Placement Matching Reference
  // ============================================
  $nameY = $pdf->GetY() + 5; // Exact spacing matching reference
  $pdf->SetY($nameY);
  $pdf->SetFont('helvetica', 'B', 22); // Large bold serif matching reference
  $pdf->SetTextColor(0, 0, 0);
  $pdf->Cell(297, 10, strtoupper($candidateName), 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  // Underline below name - dark blue matching reference EXACTLY
  $nameLineY = $pdf->GetY();
  $pdf->SetDrawColor(36, 91, 149); // Dark blue (RGB from reference)
  $pdf->SetLineWidth(0.6); // Thin line matching reference
  $nameLineStart = 78; // Exact X position matching reference
  $nameLineEnd = 219; // Exact X position matching reference
  $pdf->Line($nameLineStart, $nameLineY, $nameLineEnd, $nameLineY);
  $pdf->SetY($nameLineY + 2); // Exact spacing matching reference

  // ============================================
  // TRAINING DETAILS — Exact Placement
  // ============================================
  $pdf->SetFont('helvetica', '', 11);
  $pdf->Cell(297, 5, 'has successfully completed the Safety Training of', 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  $pdf->SetFont('helvetica', 'B', 16);
  $pdf->Cell(297, 8, strtoupper($trainingTitle), 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  $pdf->SetFont('helvetica', '', 11);
  if ($trainingDate && strtotime($trainingDate) !== false) {
    $trainingDateFormatted = date('jS F Y', strtotime($trainingDate));
  } else {
    $trainingDateFormatted = date('jS F Y');
  }
  $pdf->Cell(297, 5, 'on ' . $trainingDateFormatted, 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  $pdf->SetFont('helvetica', 'B', 11);
  $pdf->Cell(297, 5, 'in ' . $location, 0, 1, 'C', false, '', 0, false, 'T', 'M');
  
  $pdf->SetY($pdf->GetY() + 5); // Exact spacing matching reference

  // ============================================
  // CERTIFICATE SPECIFICS — Exact Two-Column Layout Matching Reference
  // ============================================
  $detailsY = $pdf->GetY();
  $leftColX = 25;  // Exact X position matching reference (left-aligned)
  $rightColX = 152; // Exact X position matching reference (right-aligned in section)
  $lineHeight = 5;
  
  $pdf->SetFont('helvetica', '', 9); // Small regular serif matching reference
  $pdf->SetTextColor(0, 0, 0);
  
  // Left column - Company details (3 lines) - left-aligned matching reference
  $pdf->SetXY($leftColX, $detailsY);
  $pdf->Cell(120, $lineHeight, 'Company : ' . strtoupper($clientName), 0, 0, 'L', false, '', 0, false, 'T', 'M');
  
  $pdf->SetXY($leftColX, $detailsY + $lineHeight);
  $pdf->Cell(120, $lineHeight, 'Certificate No. : ' . $certNo, 0, 0, 'L', false, '', 0, false, 'T', 'M');
  
  $empNoText = $employeeNo ? $employeeNo : 'N/A';
  $pdf->SetXY($leftColX, $detailsY + 2*$lineHeight);
  $pdf->Cell(120, $lineHeight, 'Emp No. : ' . $empNoText, 0, 0, 'L', false, '', 0, false, 'T', 'M');

  // Right column - Issue and Validity dates - right-aligned matching reference
  if ($issueDate && strtotime($issueDate) !== false) {
    $issueDateFormatted = date('jS F Y', strtotime($issueDate));
  } else {
    $issueDateFormatted = date('jS F Y');
  }
  if ($validityDate && strtotime($validityDate) !== false) {
    $validityDateFormatted = date('jS F Y', strtotime($validityDate));
  } else {
    $validityDateFormatted = date('jS F Y', strtotime('+2 years'));
  }
  
  $pdf->SetXY($rightColX, $detailsY);
  $pdf->Cell(120, $lineHeight, 'Certificate Issue Date : ' . $issueDateFormatted, 0, 0, 'L', false, '', 0, false, 'T', 'M');
  
  $pdf->SetXY($rightColX, $detailsY + $lineHeight);
  $pdf->Cell(120, $lineHeight, 'Certificate Validity : ' . $validityDateFormatted, 0, 0, 'L', false, '', 0, false, 'T', 'M');
  
  // Exact spacing before QR/seal position matching reference
  $pdf->SetY($detailsY + 3*$lineHeight + 3);

  // ============================================
  // QR CODE & SIGNATURES — Exact Placement Matching Reference
  // ============================================
  // In reference: QR code and signatures are on same horizontal line
  $signatureY = $pdf->GetY(); // Same Y as certificate details end
  $qrSize = 25; // Exact size matching reference
  $qrX = 148.5 - ($qrSize / 2); // Centered
  
  // Build verification URL
  $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $basePath = defined('BASE_PATH') ? BASE_PATH : '';
  $verifyUrl = "$baseUrl://$host$basePath/api/certificates/verify.php?certificate_no=" . urlencode($certNo);
  
  // Generate QR code data
  $issueDateForQR = $issueDate ?: date('Y-m-d');
  $validityForQR = $validityDate;
  $qrData = [
    'verify_url' => $verifyUrl,
    'certificate_no' => $certNo,
    'candidate_name' => $candidateName,
    'training_title' => $trainingTitle,
    'company' => $clientName,
    'issue_date' => $issueDateForQR,
    'validity_date' => $validityForQR ?? '',
    'issuer' => 'Al Resalah Consultancies & Training',
    'location' => 'Abu Dhabi, United Arab Emirates'
  ];
  $qrDataJson = json_encode($qrData, JSON_UNESCAPED_SLASHES);
  $qrPayload = $verifyUrl . "\n" . $qrDataJson;
  
  try {
    // QR code positioned at same Y as signature lines (center position)
    $qrY = $signatureY - 2; // Slightly above signature lines
    $pdf->write2DBarcode($qrPayload, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, [
      'border' => false,
      'padding' => 0,
      'fgcolor' => [0, 0, 0],
      'bgcolor' => [255, 255, 255]
    ], 'N', false);
    
    // "Scan to Verify" text below QR
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetTextColor(0, 0, 139);
    $scanTextY = $qrY + $qrSize + 1;
    $pdf->SetXY($qrX, $scanTextY);
    $pdf->Cell($qrSize, 2.5, 'Scan to Verify', 0, 0, 'C', false, '', 0, false, 'T', 'M');
    $pdf->SetTextColor(0, 0, 0);
  } catch (Exception $e) {
    error_log("QR code generation error: " . $e->getMessage());
    $qrY = $signatureY - 2;
    $scanTextY = $qrY + $qrSize + 1;
  }
  
  // ============================================
  // SIGNATURES — Exact Placement Matching Reference
  // ============================================
  // Signature lines on same horizontal line as QR code
  $sigLineW = 80; // Exact width matching reference
  $sigLeftX = 40; // Exact X position matching reference
  $sigRightX = 177; // Exact X position matching reference
  
  $pdf->SetDrawColor(128, 128, 128); // Grey signature lines matching reference
  $pdf->SetLineWidth(0.4); // Thin grey line matching reference
  $pdf->Line($sigLeftX, $signatureY, $sigLeftX + $sigLineW, $signatureY);
  $pdf->Line($sigRightX, $signatureY, $sigRightX + $sigLineW, $signatureY);
  
  // Signature labels - black slightly italicized serif-like matching reference
  $pdf->SetFont('helvetica', 'I', 9);
  $pdf->SetTextColor(0, 0, 0);
  $labelSpacing = 2; // Exact spacing matching reference (labels directly below lines)
  $pdf->SetXY($sigLeftX, $signatureY + $labelSpacing);
  $pdf->Cell($sigLineW, 5, 'Instructor', 0, 0, 'C', false, '', 0, false, 'T', 'M');
  $pdf->SetXY($sigRightX, $signatureY + $labelSpacing);
  $pdf->Cell($sigLineW, 5, 'Chief Executive Officer', 0, 0, 'C', false, '', 0, false, 'T', 'M');
  
  $pdf->SetDrawColor(0, 0, 0);
  
  // ============================================
  // FOOTER — Positioned 4mm Above Bottom Border
  // ============================================
  // Bottom border position: 210 - $inner - 1 = 201mm
  // Footer should end 4mm above border = 197mm
  $bottomBorderY = 210 - $inner - 1; // Bottom decorative line position
  $footerEndY = $bottomBorderY - 4; // 4mm above bottom border = 197mm
  
  // Calculate footer content height
  $accreditationLineHeight = 2.8;
  $accreditationSpacing = 0.5;
  $contactLineHeight = 3;
  $contactSpacing = 1;
  $numAccreditations = 6;
  $footerContentHeight = ($numAccreditations * $accreditationLineHeight) + 
                         (($numAccreditations - 1) * $accreditationSpacing) + 
                         $contactSpacing + 
                         $contactLineHeight;
  
  // Start footer so it ends exactly at footerEndY
  $footerStartY = $footerEndY - $footerContentHeight;
  
  // Calculate signature section bottom - use the lowest element
  // Signature labels bottom: $signatureY + $labelSpacing + 5 (cell height)
  // "Scan to Verify" bottom: $scanTextY + 2.5 (text height) - if exists
  $signatureLabelsBottom = $signatureY + $labelSpacing + 5;
  $scanTextBottom = isset($scanTextY) ? $scanTextY + 2.5 : $signatureLabelsBottom;
  $signatureSectionBottom = max($signatureLabelsBottom, $scanTextBottom);
  
  // Reduce gap - only 2mm spacing between signature section and footer (not too much)
  $minGapBetweenSections = 2; // Minimal gap
  if ($footerStartY < $signatureSectionBottom + $minGapBetweenSections) {
    // Position footer with minimal gap
    $footerStartY = $signatureSectionBottom + $minGapBetweenSections;
    // Recalculate footer end position
    $footerEndY = $footerStartY + $footerContentHeight;
    
    // Ensure footer still ends 4mm above bottom border
    $maxFooterEndY = $bottomBorderY - 4;
    if ($footerEndY > $maxFooterEndY) {
      // If footer exceeds boundary, reduce spacing between accreditations
      $accreditationSpacing = 0.3;
      // Recalculate footer content height with reduced spacing
      $footerContentHeight = ($numAccreditations * $accreditationLineHeight) + 
                             (($numAccreditations - 1) * $accreditationSpacing) + 
                             $contactSpacing + 
                             $contactLineHeight;
      $footerEndY = $footerStartY + $footerContentHeight;
      // If still too high, adjust footer start
      if ($footerEndY > $maxFooterEndY) {
        $footerEndY = $maxFooterEndY;
        $footerStartY = $footerEndY - $footerContentHeight;
      }
    }
  }
  
  $pdf->SetY($footerStartY);
  
  $pdf->SetFont('helvetica', '', 6); // Small regular serif matching reference
  $pdf->SetTextColor(0, 0, 0);
  $accreditations = [
    "Al Resalah Consultancies and Training - Sole Proprietorship LLC is Licensed by the Abu Dhabi Center for Technical & Vocational Education & Training (ACTVET) - 0512/2014",
    "Accredited Center by Knowledge and Human Development Authority - 631040",
    "Accredited Center by Department of Health for Continuing Medical Education (CME) - MECMF-2022-000041",
    "Approved by Abu Dhabi Occupational Safety and Health Center (OSHAD) - 1000202",
    "Approved by Abu Dhabi National Oil Company (ADNOC) - 0010006807",
    "Registered with Abu Dhabi Distribution Co. (ADDC) - 9931527"
  ];
  
  // Center-aligned accreditation text - exact width matching reference
  $accreditationWidth = 255;
  $accreditationX = (297 - $accreditationWidth) / 2; // Centered
  
  foreach ($accreditations as $acc) {
    $currentY = $pdf->GetY();
    // Ensure we don't exceed footerEndY
    if ($currentY + $accreditationLineHeight > $footerEndY - $contactLineHeight - $contactSpacing) {
      break;
    }
    $pdf->MultiCell($accreditationWidth, $accreditationLineHeight, $acc, 0, 'C', false, 1, $accreditationX, $currentY, true);
    $pdf->SetY($pdf->GetY() + $accreditationSpacing);
  }
  
  // Contact info - positioned to end exactly 2mm above bottom border
  $contactY = $footerEndY - $contactLineHeight;
  $pdf->SetFont('helvetica', '', 6); // Small regular serif matching reference
  $contactInfo = "$companyPhone ; $companyPhoneDubai | Abu Dhabi & Dubai, U.A.E. | $companyEmail";
  $pdf->SetXY($accreditationX, $contactY);
  $pdf->Cell($accreditationWidth, $contactLineHeight, $contactInfo, 0, 0, 'C', false, '', 0, false, 'T', 'M');

  // ============================================
  // SAVE FILE
  // ============================================
  try {
    $pdf->Output($filePath, 'F');
  } catch (Exception $e) {
    error_log("TCPDF Output failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    throw new Exception("Failed to save PDF file: " . $e->getMessage());
  }

  return $fileName;
  
  } catch (Throwable $e) {
    error_log("Certificate PDF generation error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    throw $e; // Re-throw to be caught by caller
  }
}

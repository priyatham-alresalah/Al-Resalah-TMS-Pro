<?php
require_once __DIR__ . '/fpdf.php';

function generateCertificatePDF(
  string $candidateName,
  string $certNo,
  string $trainingTitle,
  string $clientName,
  string $qrPath
): string {

  $fileName = "certificate_$certNo.pdf";
  $filePath = __DIR__ . "/../uploads/certificates/$fileName";

  $pdf = new FPDF('P', 'mm', 'A4');
  $pdf->AddPage();

  /* ===============================
     BORDER
  =============================== */
  $pdf->Rect(10, 10, 190, 277);

  /* ===============================
     HEADER
  =============================== */
  $pdf->SetFont('Arial', 'B', 22);
  $pdf->Ln(15);
  $pdf->Cell(0, 15, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

  $pdf->Ln(10);

  /* ===============================
     BODY
  =============================== */
  $pdf->SetFont('Arial', '', 14);
  $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');

  $pdf->Ln(5);

  $pdf->SetFont('Arial', 'B', 20);
  $pdf->Cell(0, 14, strtoupper($candidateName), 0, 1, 'C');

  $pdf->Ln(6);

  $pdf->SetFont('Arial', '', 14);
  $pdf->MultiCell(
    0,
    10,
    "has successfully completed the training program\n\n" .
    strtoupper($trainingTitle) . "\n\n" .
    "conducted for\n\n" .
    strtoupper($clientName),
    0,
    'C'
  );

  $pdf->Ln(10);

  /* ===============================
     FOOTER TEXT
  =============================== */
  $pdf->SetFont('Arial', '', 12);
  $pdf->Cell(0, 8, "Certificate No: $certNo", 0, 1, 'C');
  $pdf->Cell(0, 8, "Issued On: " . date('d M Y'), 0, 1, 'C');

  $pdf->Ln(20);

  $pdf->SetFont('Arial', 'I', 11);
  $pdf->Cell(0, 8, 'Authorized Signatory', 0, 1, 'C');

  /* ===============================
     QR CODE
  =============================== */
  if (file_exists($qrPath)) {
    $pdf->Image($qrPath, 160, 235, 30);
  }

  /* ===============================
     VERIFY URL
  =============================== */
  $verifyUrl = "https://yourdomain.com/verify.php?cert=$certNo";
  $pdf->SetY(-30);
  $pdf->SetFont('Arial', '', 9);
  $pdf->Cell(0, 6, "Verify this certificate at: $verifyUrl", 0, 1, 'C');

  /* ===============================
     SAVE FILE
  =============================== */
  $pdf->Output('F', $filePath);

  return $fileName;
}

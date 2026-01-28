<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/fpdf.php';

$training_id = $_GET['training_id'];
$cert_no = $_GET['cert_no'];

$file_name = "certificate_$cert_no.pdf";
$file_path = "../uploads/certificates/$file_name";

/* ======================
   CREATE PDF
====================== */
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',20);
$pdf->Cell(0,20,'CERTIFICATE OF COMPLETION',0,1,'C');

$pdf->Ln(10);
$pdf->SetFont('Arial','',14);
$pdf->Cell(0,10,'This certifies that',0,1,'C');

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Participant Name',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Ln(5);
$pdf->Cell(0,10,'has successfully completed the training',0,1,'C');

$pdf->Ln(5);
$pdf->Cell(0,10,'Certificate No: '.$cert_no,0,1,'C');

$pdf->Ln(10);
$pdf->Cell(0,10,'Issued Date: '.date('d M Y'),0,1,'C');

$pdf->Output('F', $file_path);

/* ======================
   SAVE TO SUPABASE
====================== */
$data = json_encode([
  "training_id" => $training_id,
  "certificate_no" => $cert_no,
  "issued_date" => date('Y-m-d'),
  "file_path" => $file_name
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE."\r\n".
      "Content-Type: application/json\r\n".
      "Prefer: return=minimal",
    'content' => $data
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates",
  false,
  $ctx
);

header("Location: ../pages/certificates.php");
exit;

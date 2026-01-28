<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_GET['id'] ?? null;
if (!$id) die("Invoice ID missing");

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$invoice = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?id=eq.$id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$invoice) {
  die("Invoice not found");
}

// Check if PDF exists
$pdfPath = __DIR__ . "/../../uploads/invoices/invoice_" . $invoice['invoice_no'] . ".pdf";

if (file_exists($pdfPath)) {
  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="invoice_' . $invoice['invoice_no'] . '.pdf"');
  readfile($pdfPath);
  exit;
}

// If PDF doesn't exist, generate it
require '../../includes/invoice_pdf.php';

try {
  $client = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.{$invoice['client_id']}&limit=1",
      false,
      $ctx
    ),
    true
  )[0] ?? null;

  if (!$client) {
    die("Client not found");
  }

  $pdfFileName = generateInvoicePDF(
    $invoice['invoice_no'],
    $client['company_name'] ?? '',
    $client['email'] ?? '',
    $client['address'] ?? '',
    $invoice['amount'],
    $invoice['vat'],
    $invoice['total'],
    $invoice['issued_date'] ?? date('Y-m-d'),
    $invoice['due_date'] ?? null
  );

  $pdfPath = __DIR__ . "/../../uploads/invoices/" . $pdfFileName;
  
  if (file_exists($pdfPath)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdfFileName . '"');
    readfile($pdfPath);
  } else {
    die("Failed to generate PDF");
  }
} catch (Exception $e) {
  die("Error generating PDF: " . $e->getMessage());
}
exit;

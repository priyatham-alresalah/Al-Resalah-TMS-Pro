<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

require '../../includes/PHPMailer/PHPMailer.php';
require '../../includes/PHPMailer/SMTP.php';
require '../../includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$invoice_id = $_POST['invoice_id'] ?? null;
if (!$invoice_id) die('Invoice ID missing');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH INVOICE */
$invoice = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?id=eq.$invoice_id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$invoice) {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Invoice not found'));
  exit;
}

/* FETCH CLIENT */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$invoice['client_id']}&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$client || empty($client['email'])) {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Client email missing'));
  exit;
}

/* GET OR GENERATE PDF */
$pdfPath = __DIR__ . "/../../uploads/invoices/invoice_" . $invoice['invoice_no'] . ".pdf";

if (!file_exists($pdfPath)) {
  require '../../includes/invoice_pdf.php';
  
  try {
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
    if ($pdfFileName) {
      $pdfPath = __DIR__ . "/../../uploads/invoices/" . $pdfFileName;
    }
  } catch (Exception $e) {
    header('Location: ../../pages/invoices.php?error=' . urlencode('Failed to generate PDF: ' . $e->getMessage()));
    exit;
  }
}

if (!file_exists($pdfPath)) {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Invoice PDF not found'));
  exit;
}

/* SEND EMAIL */
$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host       = SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = SMTP_USER;
  $mail->Password   = SMTP_PASS;
  $mail->SMTPSecure = 'tls';
  $mail->Port       = SMTP_PORT;

  $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
  $mail->addAddress($client['email'], $client['company_name']);

  // Attach PDF if it exists
  if (file_exists($pdfPath)) {
    $mail->addAttachment($pdfPath, 'invoice_' . $invoice['invoice_no'] . '.pdf');
  } else {
    error_log("Invoice PDF not found for attachment: $pdfPath");
  }

  $mail->isHTML(true);
  $mail->Subject = 'Invoice ' . $invoice['invoice_no'];
  $mail->Body = "
    Dear {$client['company_name']},<br><br>
    Please find attached invoice {$invoice['invoice_no']}.<br><br>
    Amount: " . number_format($invoice['amount'], 2) . "<br>
    VAT: " . number_format($invoice['vat'], 2) . "<br>
    Total: <strong>" . number_format($invoice['total'], 2) . "</strong><br><br>
    Regards,<br>
    Training Team
  ";

  $mail->send();
  
  header('Location: ../../pages/invoices.php?success=' . urlencode('Invoice sent successfully'));
} catch (Exception $e) {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Mail error: ' . $mail->ErrorInfo));
}
exit;

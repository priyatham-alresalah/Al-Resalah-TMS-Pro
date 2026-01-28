<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';
require '../../includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$inquiryId = $_POST['inquiry_id'] ?? null;
if (!$inquiryId) die('Inquiry ID missing');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch inquiry */
$inquiry = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$inquiry || empty($inquiry['quote_pdf'])) {
  die('Quote PDF not found');
}

/* Fetch client */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$client || empty($client['email'])) {
  die('Client email not found');
}

$file = __DIR__ . "/../../uploads/quotes/" . $inquiry['quote_pdf'];
if (!file_exists($file)) {
  die('Quote PDF file missing');
}

/* Send email */
$mail = new PHPMailer(true);

try {
  // Note: SMTP settings need to be configured in config.php
  // For now, using basic settings - you'll need to add these constants
  if (defined('SMTP_HOST')) {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = 'tls';
    $mail->Port       = SMTP_PORT ?? 587;
    $mail->setFrom(SMTP_FROM ?? 'noreply@example.com', SMTP_FROM_NAME ?? APP_NAME);
  } else {
    // Fallback - you need to configure SMTP in config.php
    die('SMTP not configured. Please add SMTP settings to config.php');
  }

  $mail->addAddress($client['email'], $client['company_name']);
  $mail->addAttachment($file);

  $mail->isHTML(true);
  $mail->Subject = 'Training Quote - ' . ($inquiry['quote_no'] ?? '');
  $mail->Body = "
    <p>Dear {$client['company_name']},</p>
    <p>Thank you for your training inquiry. Please find attached our quotation.</p>
    <p><strong>Quote No:</strong> {$inquiry['quote_no']}</p>
    <p><strong>Total Amount:</strong> " . number_format($inquiry['quote_total'] ?? 0, 2) . "</p>
    <p>Please review the quote and let us know if you have any questions.</p>
    <p>Best regards,<br>" . APP_NAME . "</p>
  ";

  $mail->send();
  header('Location: ../../pages/inquiries.php?success=' . urlencode('Quote email sent successfully'));
} catch (Exception $e) {
  header('Location: ../../pages/inquiries.php?error=' . urlencode('Failed to send email: ' . $mail->ErrorInfo));
}
exit;

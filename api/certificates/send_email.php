<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

require '../../includes/PHPMailer/PHPMailer.php';
require '../../includes/PHPMailer/SMTP.php';
require '../../includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$certificate_id = $_POST['certificate_id'] ?? null;
if (!$certificate_id) die('Certificate ID missing');

/* ===============================
   SUPABASE CONTEXT
=============================== */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* ===============================
   FETCH CERTIFICATE
=============================== */
$cert = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?id=eq.$certificate_id&limit=1",
    false,
    $ctx
  ),
  true
)[0];

/* FETCH CANDIDATE */
$candidate = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.".$cert['candidate_id']."&limit=1",
    false,
    $ctx
  ),
  true
)[0];

$email = $candidate['email'];
if (!$email) die('Candidate email missing');

/* FILE */
$file = "../../uploads/certificates/" . $cert['file_path'];
if (!file_exists($file)) die('Certificate file missing');

/* ===============================
   SEND EMAIL
=============================== */
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
  $mail->addAddress($email, $candidate['full_name']);

  $mail->addAttachment($file);

  $mail->isHTML(true);
  $mail->Subject = 'Your Training Certificate';
  $mail->Body = "
    Dear {$candidate['full_name']},<br><br>
    Congratulations! Please find attached your training certificate.<br><br>
    Regards,<br>
    Training Team
  ";

  $mail->send();

} catch (Exception $e) {
  die("Mail error: {$mail->ErrorInfo}");
}

header('Location: ../../pages/certificates.php');
exit;

<?php
require '../../includes/config.php';
require '../../includes/csrf.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

/* CSRF Protection */
requireCSRF();

require '../../includes/PHPMailer/PHPMailer.php';
require '../../includes/PHPMailer/SMTP.php';
require '../../includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$newEmail = trim($_POST['new_email'] ?? '');

if (!$type || !$id || !$newEmail) {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
  $redirect = ($type === 'client') ? (BASE_PATH . '/client_portal/profile.php') : (BASE_PATH . '/candidate_portal/profile.php');
  header('Location: ' . $redirect . '?error=' . urlencode('Please enter a valid email'));
  exit;
}

// Authorization + message context
if ($type === 'client') {
  if (!isset($_SESSION['client']) || ($_SESSION['client']['id'] ?? '') !== $id) {
    header('Location: ' . BASE_PATH . '/client_portal/login.php');
    exit;
  }
  $redirect = BASE_PATH . '/client_portal/profile.php';
  $fromEmail = $_SESSION['client']['email'] ?? 'noreply@example.com';
  $fromName = $_SESSION['client']['company_name'] ?? 'Client';
  $who = "Client: " . ($fromName ?: '-') . " (ID: $id)";
} elseif ($type === 'candidate') {
  if (!isset($_SESSION['candidate']) || ($_SESSION['candidate']['id'] ?? '') !== $id) {
    header('Location: ' . BASE_PATH . '/candidate_portal/login.php');
    exit;
  }
  $redirect = BASE_PATH . '/candidate_portal/profile.php';
  $fromEmail = $_SESSION['candidate']['email'] ?? 'noreply@example.com';
  $fromName = $_SESSION['candidate']['full_name'] ?? 'Candidate';
  $who = "Candidate: " . ($fromName ?: '-') . " (ID: $id)";
} else {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

$to = 'cs@aresalah.com';
$subject = "Email change request - $who";
$bodyText = "Hello Al Resalah Consultancies and Training,\n\n".
  "$who has requested to change their email address.\n\n".
  "Current email: $fromEmail\n".
  "Requested new email: $newEmail\n\n".
  "Please update it in the system.\n\n".
  "Thanks,\nTraining Management System";

try {
  // Prefer SMTP if configured, otherwise fallback to PHP mail()
  if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = 'tls';
    $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $mail->setFrom(defined('SMTP_FROM') ? SMTP_FROM : $fromEmail, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME);
    $mail->addReplyTo($fromEmail, $fromName);
    $mail->addAddress($to, 'Customer Support');
    $mail->Subject = $subject;
    $mail->Body = nl2br(htmlspecialchars($bodyText));
    $mail->isHTML(true);
    $mail->send();
  } else {
    // Basic fallback (may depend on server mail setup)
    $headers = "From: $fromName <$fromEmail>\r\n" .
      "Reply-To: $fromEmail\r\n" .
      "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, $subject, $bodyText, $headers);
  }

  header('Location: ' . $redirect . '?success=' . urlencode('Email sent to Al Resalah Consultancies and Training'));
  exit;
} catch (Exception $e) {
  header('Location: ' . $redirect . '?error=' . urlencode('Failed to send email request'));
  exit;
}


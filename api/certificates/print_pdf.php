<?php
// Production-safe: log errors, never display to browser (cPanel/shared hosting)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Check if function already exists (prevent redeclaration)
if (!function_exists('extractHttpCode')) {
  function extractHttpCode($headers) {
    if (empty($headers) || !is_array($headers)) return null;
    foreach ($headers as $header) {
      if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $header, $matches)) {
        return (int)$matches[1];
      }
    }
    return null;
  }
}

try {
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/certificate_pdf.php';

$id = $_GET['id'] ?? null;
if (!$id) die("Certificate ID missing");

$headers =
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

// Fetch certificate with related data
$certCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers,
    'ignore_errors' => true
  ]
]);

$certResponse = file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id&limit=1",
  false,
  $certCtx
);

if ($certResponse === false) {
  $error = error_get_last();
  error_log("Certificate print: Failed to fetch certificate for ID: $id. Error: " . ($error['message'] ?? 'unknown'));
  die("Failed to fetch certificate data. Please check the logs.");
}

$certData = json_decode($certResponse, true);
$cert = $certData[0] ?? null;

if (!$cert) {
  error_log("Certificate print: Certificate not found for ID: $id. Response: " . substr($certResponse, 0, 500));
  die("Certificate not found");
}

// If file_path exists and file exists, serve it
if (!empty($cert['file_path'])) {
  $path = __DIR__ . "/../../uploads/certificates/" . basename($cert['file_path']);
  if (file_exists($path)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($cert['file_path']) . '"');
    readfile($path);
    exit;
  }
}

// Generate PDF on-demand
// Fetch candidate
if (empty($cert['candidate_id'])) {
  error_log("Certificate print: Missing candidate_id for certificate ID: $id");
  die("Certificate data incomplete: candidate ID missing");
}

$candidateId = $cert['candidate_id'];
$candidateUrl = SUPABASE_URL . "/rest/v1/candidates?id=eq.$candidateId&select=full_name,email";

// Recreate context for this request
$candidateCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers,
    'ignore_errors' => true // Don't fail on HTTP errors, we'll check manually
  ]
]);

$candidateResponse = file_get_contents($candidateUrl, false, $candidateCtx);

if ($candidateResponse === false) {
  $error = error_get_last();
  $httpCode = null;
  if (isset($http_response_header) && is_array($http_response_header)) {
    $httpCode = extractHttpCode($http_response_header);
  }
  error_log("Certificate print: file_get_contents failed for candidate ID: $candidateId. URL: $candidateUrl. HTTP Code: " . ($httpCode ?? 'unknown') . ". Error: " . ($error['message'] ?? 'unknown'));
  die("Failed to fetch candidate data. Please check the logs.");
}

// Check HTTP response code
$httpCode = null;
if (isset($http_response_header) && is_array($http_response_header)) {
  $httpCode = extractHttpCode($http_response_header);
  if ($httpCode && $httpCode >= 400) {
    error_log("Certificate print: HTTP $httpCode when fetching candidate ID: $candidateId. Response: " . substr($candidateResponse, 0, 500));
    die("Failed to fetch candidate data (HTTP $httpCode). Please check the logs.");
  }
}

$candidateData = json_decode($candidateResponse, true);
$candidate = $candidateData[0] ?? null;

if (!$candidate) {
  error_log("Certificate print: Candidate not found for ID: {$cert['candidate_id']} (Certificate ID: $id)");
  die("Candidate not found (ID: {$cert['candidate_id']})");
}

// Fetch training
$trainingCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers,
    'ignore_errors' => true
  ]
]);

$trainingResponse = file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings?id=eq.{$cert['training_id']}&select=course_name,training_date,client_id",
  false,
  $trainingCtx
);

if ($trainingResponse === false) {
  $error = error_get_last();
  error_log("Certificate print: Failed to fetch training for ID: {$cert['training_id']}. Error: " . ($error['message'] ?? 'unknown'));
  die("Failed to fetch training data. Please check the logs.");
}

$training = json_decode($trainingResponse, true)[0] ?? null;

if (!$training) {
  error_log("Certificate print: Training not found for ID: {$cert['training_id']}. Response: " . substr($trainingResponse, 0, 500));
  die("Training not found");
}

// Fetch client
$clientCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers,
    'ignore_errors' => true
  ]
]);

$clientResponse = file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?id=eq.{$training['client_id']}&select=company_name",
  false,
  $clientCtx
);

if ($clientResponse === false) {
  $error = error_get_last();
  error_log("Certificate print: Failed to fetch client for ID: {$training['client_id']}. Error: " . ($error['message'] ?? 'unknown'));
  die("Failed to fetch client data. Please check the logs.");
}

$client = json_decode($clientResponse, true)[0] ?? null;

if (!$client) {
  error_log("Certificate print: Client not found for ID: {$training['client_id']}. Response: " . substr($clientResponse, 0, 500));
  die("Client not found");
}

// Ensure uploads/certificates directory exists (same path used by certificate_pdf.php)
$certificatesDir = __DIR__ . '/../../uploads/certificates';
if (!is_dir($certificatesDir)) {
  mkdir($certificatesDir, 0777, true);
}

// Generate PDF
try {
  // Normalize dates - convert empty strings to null
  $trainingDate = !empty($training['training_date']) ? $training['training_date'] : null;
  $issuedDate = !empty($cert['issued_date']) ? $cert['issued_date'] : null;
  
  $fileName = generateCertificatePDF(
    $candidate['full_name'] ?? '',
    $cert['certificate_no'] ?? '',
    $training['course_name'] ?? '',
    $client['company_name'] ?? '',
    '', // QR path (will be auto-generated)
    $trainingDate,
    $issuedDate,
    null, // validity date
    null // employee_no (not available in candidates table)
  );

  if (!$fileName) {
    error_log("Certificate print: generateCertificatePDF returned null for certificate ID: $id");
    error_log("Check if TCPDF is installed: composer require tecnickcom/tcpdf");
    die("Failed to generate certificate PDF. TCPDF library may not be installed. Check error logs for details.");
  }
} catch (Exception $e) {
  error_log("Certificate print: Exception during PDF generation: " . $e->getMessage());
  die("Failed to generate certificate PDF: " . $e->getMessage());
}

$filePath = __DIR__ . "/../../uploads/certificates/" . $fileName;

if (!file_exists($filePath)) {
  die("Generated PDF file not found");
}

// Update certificate record with file_path
$relativePath = "uploads/certificates/" . $fileName;
$updateCtx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' => $headers . "\r\nContent-Type: application/json",
    'content' => json_encode(['file_path' => $relativePath])
  ]
]);

@file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id",
  false,
  $updateCtx
);

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
readfile($filePath);
exit;

} catch (Throwable $e) {
  error_log("Certificate print_pdf.php error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  header('Content-Type: text/html; charset=utf-8');
  http_response_code(200);
  echo '<!DOCTYPE html><html><head><title>Error</title></head><body style="font-family:sans-serif;padding:2rem;max-width:600px;margin:0 auto;">';
  echo '<h1>Certificate could not be generated</h1>';
  echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
  echo '<p><a href="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/pages/certificates.php">Back to Certificates</a></p>';
  echo '</body></html>';
  exit;
}

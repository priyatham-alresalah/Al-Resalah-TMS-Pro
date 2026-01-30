<?php
/**
 * QR Code Generator for Certificates
 * Generates QR codes with certificate verification details
 */

/**
 * Generate QR code image with certificate details (enhanced content)
 *
 * QR content: verification URL first (scanner-friendly), then structured JSON
 * with certificate_no, candidate_name, training_title, company, issue_date,
 * validity_date, issuer, and verify_url for full verification.
 *
 * @param string $certNo Certificate number
 * @param string $candidateName Candidate name
 * @param string $trainingTitle Training course name
 * @param string $issueDate Issue date
 * @param string $clientName Company name
 * @param string|null $validityDate Validity end date (optional)
 * @return string|null Filename of generated QR code image, or null on failure
 */
function generateCertificateQRCode(
  string $certNo,
  string $candidateName,
  string $trainingTitle,
  string $issueDate,
  string $clientName,
  ?string $validityDate = null
): ?string {

  $qrDir = __DIR__ . '/../uploads/qrcodes';
  if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true);
  }

  $safeCertNo = preg_replace('/[^a-zA-Z0-9_-]/', '-', $certNo);
  $fileName = "cert_$safeCertNo.png";
  $filePath = "$qrDir/$fileName";

  $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $basePath = defined('BASE_PATH') ? BASE_PATH : '';
  $verifyUrl = "$baseUrl://$host$basePath/api/certificates/verify.php?certificate_no=" . urlencode($certNo);

  // Enhanced payload: URL first for scanners, then structured data
  $qrData = [
    'verify_url' => $verifyUrl,
    'certificate_no' => $certNo,
    'candidate_name' => $candidateName,
    'training_title' => $trainingTitle,
    'company' => $clientName,
    'issue_date' => $issueDate,
    'validity_date' => $validityDate ?? '',
    'issuer' => 'Al Resalah Consultancies & Training',
    'location' => 'Abu Dhabi, United Arab Emirates'
  ];

  $qrDataJson = json_encode($qrData, JSON_UNESCAPED_SLASHES);

  // Prefer URL-first text so any scanner can open verify page; JSON for apps
  $qrPayload = $verifyUrl . "\n" . $qrDataJson;

  $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrPayload);

  $qrImage = @file_get_contents($qrApiUrl);

  if ($qrImage === false) {
    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrDataJson);
    $qrImage = @file_get_contents($qrApiUrl);
  }

  if ($qrImage === false) {
    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($verifyUrl);
    $qrImage = @file_get_contents($qrApiUrl);
  }

  if ($qrImage !== false && file_put_contents($filePath, $qrImage) !== false) {
    return $fileName;
  }

  $googleQrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qrPayload);
  $qrImage = @file_get_contents($googleQrUrl);

  if ($qrImage !== false && file_put_contents($filePath, $qrImage) !== false) {
    return $fileName;
  }

  error_log("Failed to generate QR code for certificate: $certNo");
  return null;
}

/**
 * Generate QR code with verification URL only (simpler format)
 * 
 * @param string $certNo Certificate number
 * @return string|null Filename of generated QR code image, or null on failure
 */
function generateSimpleQRCode(string $certNo): ?string {
  // Build verification URL
  $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $basePath = defined('BASE_PATH') ? BASE_PATH : '';
  $verifyUrl = "$baseUrl://$host$basePath/api/certificates/verify.php?certificate_no=" . urlencode($certNo);
  
  // Ensure QR codes directory exists
  $qrDir = __DIR__ . '/../uploads/qrcodes';
  if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true);
  }
  
  // Sanitize certificate number for filename (replace slashes and special chars)
  $safeCertNo = preg_replace('/[^a-zA-Z0-9_-]/', '-', $certNo);
  $fileName = "cert_$safeCertNo.png";
  $filePath = "$qrDir/$fileName";
  
  // Check if QR code already exists
  if (file_exists($filePath)) {
    return $fileName; // Return existing QR code
  }
  
  // Use QR Server API
  $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($verifyUrl);
  $qrImage = @file_get_contents($qrApiUrl);
  
  if ($qrImage !== false && file_put_contents($filePath, $qrImage) !== false) {
    return $fileName;
  }
  
  // Fallback: Google Charts API
  $googleQrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($verifyUrl);
  $qrImage = @file_get_contents($googleQrUrl);
  
  if ($qrImage !== false && file_put_contents($filePath, $qrImage) !== false) {
    return $fileName;
  }
  
  error_log("Failed to generate QR code for certificate: $certNo");
  return null;
}

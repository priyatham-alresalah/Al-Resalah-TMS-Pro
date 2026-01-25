<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

$training_id  = $_POST['training_id'] ?? null;
$candidate_id = $_POST['candidate_id'] ?? null;

if (!$training_id || !$candidate_id) {
  die('Missing data');
}

/* ===============================
   SUPABASE CONTEXT
================================ */
$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

/* ===============================
   CHECK DUPLICATE
================================ */
$ctxCheck = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

$existing = json_decode(
  file_get_contents(
    SUPABASE_URL .
    "/rest/v1/certificates?training_id=eq.$training_id&candidate_id=eq.$candidate_id",
    false,
    $ctxCheck
  ),
  true
);

if (!empty($existing)) {
  header("Location: ../../issue_certificates.php?training_id=$training_id");
  exit;
}

/* ===============================
   GENERATE CERT NUMBER
================================ */
$cert_no = 'CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

/* ===============================
   INSERT CERTIFICATE
================================ */
$payload = json_encode([
  'training_id'    => $training_id,
  'candidate_id'   => $candidate_id,
  'certificate_no' => $cert_no,
  'issued_date'    => date('Y-m-d'),
  'issued_by'      => $_SESSION['user']['id']
]);

$ctxInsert = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  => $headers,
    'content' => $payload
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates",
  false,
  $ctxInsert
);

/* ===============================
   REDIRECT BACK
================================ */
header("Location: ../../issue_certificates.php?training_id=$training_id");
exit;

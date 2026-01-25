<?php
require '../../includes/config.php';

$cert_no = $_GET['certificate_no'] ?? null;

if (!$cert_no) {
  http_response_code(400);
  echo json_encode(['error' => 'Certificate number required']);
  exit;
}

$headers =
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

/* FETCH CERTIFICATE */
$cert = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?certificate_no=eq.$cert_no&select=*",
    false,
    $ctx
  ),
  true
);

if (!$cert) {
  echo json_encode(['valid' => false]);
  exit;
}

$cert = $cert[0];

/* FETCH CANDIDATE */
$candidate = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.{$cert['candidate_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* FETCH TRAINING */
$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.{$cert['training_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* FETCH CLIENT */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$training['client_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

echo json_encode([
  'valid' => true,
  'certificate_no' => $cert['certificate_no'],
  'issued_date' => $cert['issued_date'],
  'candidate' => $candidate['full_name'] ?? '',
  'course' => $training['course_name'] ?? '',
  'company' => $client['company_name'] ?? ''
]);

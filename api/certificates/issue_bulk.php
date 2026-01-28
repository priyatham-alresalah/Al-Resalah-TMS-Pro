<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

$training_id = $_POST['training_id'] ?? null;
if (!$training_id) {
  die('Training ID missing');
}

$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

/* ===============================
   FETCH CANDIDATES FOR TRAINING
================================ */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => $headers
  ]
]);

$candidates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
);

if (!$candidates) {
  header("Location: ../../pages/issue_certificates.php?training_id=$training_id&error=" . urlencode('No candidates found'));
  exit;
}

$selectedCandidates = $_POST['candidates'] ?? [];
if (empty($selectedCandidates)) {
  header("Location: ../../pages/issue_certificates.php?training_id=$training_id&error=" . urlencode('Please select at least one candidate'));
  exit;
}

/* ===============================
   FETCH EXISTING CERTIFICATES
================================ */
$existing = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
);

$issuedMap = [];
foreach ($existing as $e) {
  $issuedMap[$e['candidate_id']] = true;
}

/* ===============================
   ISSUE CERTIFICATES
================================ */
foreach ($candidates as $c) {
  $cid = $c['candidate_id'];

  if (isset($issuedMap[$cid])) {
    continue; // already issued
  }

  $cert_no = 'CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

  $payload = json_encode([
    'training_id'    => $training_id,
    'candidate_id'   => $cid,
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

  $result = file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates",
    false,
    $ctxInsert
  );
  
  if ($result !== false) {
    $issuedCount++;
  }
}

/* ===============================
   REDIRECT
================================ */
header("Location: ../../pages/issue_certificates.php?training_id=$training_id&success=" . urlencode("Successfully issued $issuedCount certificate(s)"));
exit;

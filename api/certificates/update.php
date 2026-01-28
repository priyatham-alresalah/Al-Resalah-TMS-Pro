<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../pages/certificates.php?error=' . urlencode('Invalid request'));
  exit;
}

$id = $_POST['id'] ?? '';
$certificate_no = trim($_POST['certificate_no'] ?? '');
$issued_date = $_POST['issued_date'] ?? '';
$status = $_POST['status'] ?? 'active';

if (empty($id) || empty($certificate_no) || empty($issued_date)) {
  header('Location: ../../pages/certificate_edit.php?id=' . $id . '&error=' . urlencode('Please fill all required fields'));
  exit;
}

$data = json_encode([
  'certificate_no' => $certificate_no,
  'issued_date' => $issued_date,
  'status' => $status
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/certificates?id=eq.$id",
  false,
  $ctx
);

header('Location: ../../pages/certificates.php?success=' . urlencode('Certificate updated successfully'));
exit;

<?php
require '../../includes/config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$phone = trim($_POST['phone'] ?? '');

if (!$type || !$id) {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

// Authorization: only allow updating own record
if ($type === 'client') {
  if (!isset($_SESSION['client']) || ($_SESSION['client']['id'] ?? '') !== $id) {
    header('Location: ' . BASE_PATH . '/client_portal/login.php');
    exit;
  }
  $redirect = BASE_PATH . '/client_portal/profile.php';
  $table = 'clients';
} elseif ($type === 'candidate') {
  if (!isset($_SESSION['candidate']) || ($_SESSION['candidate']['id'] ?? '') !== $id) {
    header('Location: ' . BASE_PATH . '/candidate_portal/login.php');
    exit;
  }
  $redirect = BASE_PATH . '/candidate_portal/profile.php';
  $table = 'candidates';
} else {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
  "Prefer: return=minimal";

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' => $headers,
    'content' => json_encode(['phone' => ($phone !== '' ? $phone : null)])
  ]
]);

$resp = @file_get_contents(
  SUPABASE_URL . "/rest/v1/$table?id=eq.$id",
  false,
  $ctx
);

if ($resp === false) {
  header('Location: ' . $redirect . '?error=' . urlencode('Failed to update phone'));
  exit;
}

header('Location: ' . $redirect . '?success=' . urlencode('Phone updated successfully'));
exit;


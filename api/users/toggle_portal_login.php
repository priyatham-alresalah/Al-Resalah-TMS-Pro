<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  die('Access denied');
}

requireCSRF();

$type = $_POST['type'] ?? '';
$id = trim($_POST['id'] ?? '');
$enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;

if (!in_array($type, ['client', 'candidate']) || $id === '') {
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Invalid request'));
  exit;
}

$table = $type === 'client' ? 'clients' : 'candidates';

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode(['login_enabled' => (bool)$enabled])
  ]
]);

$resp = @file_get_contents(
  SUPABASE_URL . "/rest/v1/$table?id=eq." . rawurlencode($id),
  false,
  $ctx
);

$ok = isset($http_response_header[0]) && preg_match('/^HTTP\/\d\.\d\s+2\d\d\b/', $http_response_header[0]);
if ($resp === false || !$ok) {
  header('Location: ' . BASE_PATH . '/pages/users.php?tab=' . $type . 's&error=' . urlencode('Failed to update. Run sql/add_login_enabled.sql in Supabase if needed.'));
  exit;
}

$label = $type === 'client' ? 'Client' : 'Candidate';
$status = $enabled ? 'enabled' : 'disabled';
header('Location: ' . BASE_PATH . '/pages/users.php?tab=' . $type . 's&success=' . urlencode("$label login $status"));
exit;

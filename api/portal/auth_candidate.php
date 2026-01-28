<?php
/**
 * Candidate Portal Authentication
 * Verifies candidate email and password
 */
require '../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid_request');
  exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
  header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid');
  exit;
}

/* Verify candidate exists */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$candidates = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?email=eq." . rawurlencode($email) . "&select=id,full_name,email,client_id,password_hash",
    false,
    $ctx
  ),
  true
);

if (empty($candidates[0])) {
  header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid');
  exit;
}

$candidate = $candidates[0];

/* Check password */
if (!empty($candidate['password_hash'])) {
  /* Password stored in database - verify */
  if (!password_verify($password, $candidate['password_hash'])) {
    header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid');
    exit;
  }
} else {
  /* No password set - try Supabase Auth */
  $payload = json_encode([
    'email' => $email,
    'password' => $password
  ]);

  $authCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_ANON . "\r\n",
      'content' => $payload
    ]
  ]);

  $authResponse = @file_get_contents(
    SUPABASE_URL . '/auth/v1/token?grant_type=password',
    false,
    $authCtx
  );

  if ($authResponse === false) {
    header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid');
    exit;
  }

  $authData = json_decode($authResponse, true);
  if (!isset($authData['access_token'], $authData['user']['id'])) {
    header('Location: ' . BASE_PATH . '/candidate_portal/login.php?error=invalid');
    exit;
  }
}

/* Create session */
session_start();
$_SESSION['candidate'] = [
  'id' => $candidate['id'],
  'full_name' => $candidate['full_name'],
  'email' => $candidate['email'],
  'client_id' => $candidate['client_id']
];

session_regenerate_id(true);

header('Location: ' . BASE_PATH . '/candidate_portal/dashboard.php');
exit;

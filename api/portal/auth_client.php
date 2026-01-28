<?php
/**
 * Client Portal Authentication
 * Verifies client email and password using Supabase Auth
 */
require '../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid_request');
  exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
  header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid');
  exit;
}

/* Verify client exists */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$clients = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?email=eq." . rawurlencode($email) . "&select=id,company_name,email,password_hash",
    false,
    $ctx
  ),
  true
);

if (empty($clients[0])) {
  header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid');
  exit;
}

$client = $clients[0];

/* Check password */
if (!empty($client['password_hash'])) {
  /* Password stored in database - verify */
  if (!password_verify($password, $client['password_hash'])) {
    header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid');
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
    header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid');
    exit;
  }

  $authData = json_decode($authResponse, true);
  if (!isset($authData['access_token'], $authData['user']['id'])) {
    header('Location: ' . BASE_PATH . '/client_portal/login.php?error=invalid');
    exit;
  }
}

/* Create session */
session_start();
$_SESSION['client'] = [
  'id' => $client['id'],
  'company_name' => $client['company_name'],
  'email' => $client['email']
];

session_regenerate_id(true);

header('Location: ' . BASE_PATH . '/client_portal/dashboard.php');
exit;

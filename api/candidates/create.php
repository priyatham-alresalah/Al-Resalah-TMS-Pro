<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

/* Input Validation */
$fullName = trim($_POST['full_name'] ?? '');
$clientId = !empty($_POST['client_id']) ? trim($_POST['client_id']) : null;
$email = !empty($_POST['email']) ? trim($_POST['email']) : null;
$phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;

if (empty($fullName)) {
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Full name is required'));
  exit;
}

/* Validate email if provided */
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid email address'));
  exit;
}

/* Validate client_id if provided */
if (!empty($clientId)) {
  $checkCtx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);
  
  $clientCheck = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.$clientId&select=id",
      false,
      $checkCtx
    ),
    true
  );
  
  if (empty($clientCheck)) {
    header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid client selected'));
    exit;
  }
}

$data = [
  'client_id' => $clientId,
  'full_name' => $fullName,
  'email' => $email,
  'phone' => $phone
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to create candidate: " . print_r($data, true));
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Failed to create candidate. Please try again.'));
  exit;
}

header("Location: " . BASE_PATH . "/pages/candidates.php?success=" . urlencode("Candidate created successfully"));
exit;

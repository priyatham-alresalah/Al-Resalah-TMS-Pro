<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

/* Input Validation */
$companyName = trim($_POST['company_name'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($companyName)) {
  header('Location: ' . BASE_PATH . '/pages/client_create.php?error=' . urlencode('Company name is required'));
  exit;
}

/* Validate email if provided */
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/pages/client_create.php?error=' . urlencode('Invalid email address'));
  exit;
}

$data = [
  'company_name'   => $companyName,
  'contact_person' => $contactPerson,
  'email'          => $email ?: null,
  'phone'          => $phone ?: null,
  'address'        => $address ?: null,
  'created_by'     => $_SESSION['user']['id']
];

/* Check for existing client with same company name */
$checkCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$encodedName = rawurlencode($data['company_name']);
$existing = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?company_name=eq.$encodedName&select=company_name,created_by",
    false,
    $checkCtx
  ),
  true
);

if (!empty($existing)) {
  $creatorId = $existing[0]['created_by'] ?? null;
  $creatorLabel = 'another user';

  if ($creatorId) {
    $profile = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/profiles?id=eq.$creatorId&select=full_name,email",
        false,
        $checkCtx
      ),
      true
    );

    if (!empty($profile[0])) {
      $fullName = trim($profile[0]['full_name'] ?? '');
      $email    = trim($profile[0]['email'] ?? '');
      $creatorLabel = $fullName !== '' ? $fullName : ($email !== '' ? $email : $creatorLabel);
    }
  }

  header('Location: ' . BASE_PATH . '/pages/client_create.php?error=' . urlencode("Client already exists - created by {$creatorLabel}"));
  exit;
}

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
  SUPABASE_URL . "/rest/v1/clients",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to create client: " . print_r($data, true));
  header('Location: ' . BASE_PATH . '/pages/client_create.php?error=' . urlencode('Failed to create client. Please try again.'));
  exit;
}

header("Location: " . BASE_PATH . "/pages/clients.php?success=" . urlencode("Client created successfully"));
exit;

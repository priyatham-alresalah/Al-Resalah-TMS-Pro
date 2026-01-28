<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$data = [
  'company_name'   => trim($_POST['company_name'] ?? ''),
  'contact_person' => trim($_POST['contact_person'] ?? ''),
  'email'          => trim($_POST['email'] ?? ''),
  'phone'          => trim($_POST['phone'] ?? ''),
  'address'        => trim($_POST['address'] ?? ''),
  'created_by'     => $_SESSION['user']['id']
];

if ($data['company_name'] === '') {
  die('Company name is required');
}

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

  die("Client already exists - created by {$creatorLabel}");
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

file_get_contents(
  SUPABASE_URL . "/rest/v1/clients",
  false,
  $ctx
);

header("Location: /training-management-system/pages/clients.php");
exit;

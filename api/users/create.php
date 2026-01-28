<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  die('Access denied');
}

/* CSRF Protection */
requireCSRF();

/* Input Validation */
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$fullName = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');

if (empty($email) || empty($password) || empty($fullName) || empty($role)) {
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('All fields are required'));
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('Invalid email address'));
  exit;
}

$validRoles = ['admin', 'bdm', 'bdo', 'coordinator', 'trainer', 'accounts'];
if (!in_array(strtolower($role), $validRoles)) {
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('Invalid role'));
  exit;
}

if (strlen($password) < 6) {
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('Password must be at least 6 characters'));
  exit;
}

$data = json_encode([
  'email' => $email,
  'password' => $password,
  'user_metadata' => [
    'full_name' => $fullName,
    'role' => strtolower($role)
  ]
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => $data
  ]
]);

$response = @file_get_contents(SUPABASE_URL . "/auth/v1/admin/users", false, $ctx);
if ($response === false) {
  error_log("Failed to create user in Supabase Auth");
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('Failed to create user. Please try again.'));
  exit;
}

$userData = json_decode($response, true);
$userId = $userData['id'] ?? null;

if (!$userId) {
  error_log("User creation failed: " . print_r($userData, true));
  header('Location: ' . BASE_PATH . '/pages/user_create.php?error=' . urlencode('Failed to create user. Please try again.'));
  exit;
}

/* Ensure profile exists - create if it doesn't, update if it does */
$checkCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$existingProfile = json_decode(
  @file_get_contents(SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId&select=id", false, $checkCtx),
  true
);

if (empty($existingProfile)) {
  // Profile doesn't exist, create it
  $profileData = [
    'id' => $userId,
    'full_name' => $fullName,
    'email' => $email,
    'role' => strtolower($role),
    'is_active' => true
  ];
  
  $createProfileCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($profileData)
    ]
  ]);
  
  @file_get_contents(SUPABASE_URL . "/rest/v1/profiles", false, $createProfileCtx);
} else {
  // Profile exists, update email
  $updateCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode(['email' => $email])
    ]
  ]);
  
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId",
    false,
    $updateCtx
  );
}

header('Location: ' . BASE_PATH . '/pages/users.php?success=' . urlencode('User created successfully'));
exit;

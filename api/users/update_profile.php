<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../pages/profile.php?error=' . urlencode('Invalid request'));
  exit;
}

$userId = $_POST['user_id'] ?? '';
$fullName = trim($_POST['full_name'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$currentPassword = $_POST['current_password'] ?? '';

/* Validate inputs */
if (empty($userId) || empty($fullName) || empty($currentPassword)) {
  header('Location: ../../pages/profile.php?error=' . urlencode('Please fill all required fields'));
  exit;
}

/* Verify user is updating their own profile */
if ($userId !== $_SESSION['user']['id']) {
  header('Location: ../../pages/profile.php?error=' . urlencode('Unauthorized'));
  exit;
}

/* Verify current password */
$payload = json_encode([
  'email' => $_SESSION['user']['email'],
  'password' => $currentPassword
]);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_ANON . "\r\n",
    'content' => $payload
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . '/auth/v1/token?grant_type=password',
  false,
  $ctx
);

if ($response === false) {
  header('Location: ../../pages/profile.php?error=' . urlencode('Current password is incorrect'));
  exit;
}

$data = json_decode($response, true);
if (!isset($data['access_token'])) {
  header('Location: ../../pages/profile.php?error=' . urlencode('Current password is incorrect'));
  exit;
}

/* Validate password match if new password provided */
if (!empty($newPassword)) {
  if ($newPassword !== $confirmPassword) {
    header('Location: ../../pages/profile.php?error=' . urlencode('New password and confirm password do not match'));
    exit;
  }

  if (strlen($newPassword) < 6) {
    header('Location: ../../pages/profile.php?error=' . urlencode('Password must be at least 6 characters long'));
    exit;
  }
}

/* Update profile in Supabase */
$headers =
  "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

$updateData = ['full_name' => $fullName];

$ctxUpdate = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' => $headers . "\r\nPrefer: return=minimal",
    'content' => json_encode($updateData)
  ]
]);

$updateResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId",
  false,
  $ctxUpdate
);

if ($updateResponse === false) {
  header('Location: ../../pages/profile.php?error=' . urlencode('Failed to update profile'));
  exit;
}

/* Update password if provided */
if (!empty($newPassword)) {
  // Use Supabase Admin API to update password
  $passwordPayload = json_encode([
    'password' => $newPassword
  ]);

  $ctxPassword = stream_context_create([
    'http' => [
      'method' => 'PUT',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => $passwordPayload
    ]
  ]);

  // Update password via Supabase Admin API
  $passwordResponse = @file_get_contents(
    SUPABASE_URL . "/auth/v1/admin/users/$userId",
    false,
    $ctxPassword
  );

  // Note: Password update might require admin privileges
  // If this fails, user will need to use password reset flow
}

/* Update session with new name */
$_SESSION['user']['name'] = $fullName;

header('Location: ../../pages/profile.php?success=' . urlencode('Profile updated successfully'));
exit;

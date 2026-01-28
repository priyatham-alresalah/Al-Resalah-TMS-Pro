<?php
/**
 * Sync Profiles from Auth Users
 * Creates profile records for users that exist in auth.users but not in profiles
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  die('Access denied');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Get all existing profiles
$profilesResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?select=id",
  false,
  $ctx
);

$existingProfileIds = [];
if ($profilesResponse !== false) {
  $profiles = json_decode($profilesResponse, true) ?: [];
  $existingProfileIds = array_column($profiles, 'id');
}

// Fetch all users from auth.users using Admin API
// Note: Admin API requires special headers
$authUsersUrl = SUPABASE_URL . "/auth/v1/admin/users?per_page=1000";

$authCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Content-Type: application/json"
  ]
]);

$authUsersResponse = @file_get_contents($authUsersUrl, false, $authCtx);

if ($authUsersResponse === false) {
  $error = error_get_last();
  error_log("Failed to fetch auth users: " . ($error['message'] ?? 'Unknown'));
  header('Location: ' . BASE_PATH . '/pages/users.php?error=' . urlencode('Failed to fetch users from Supabase Auth. Check error logs.'));
  exit;
}

$authUsers = json_decode($authUsersResponse, true);

// Handle different response formats
if (isset($authUsers['users'])) {
  $users = $authUsers['users'];
} elseif (is_array($authUsers) && isset($authUsers[0]['id'])) {
  $users = $authUsers;
} else {
  $users = [];
}

if (empty($users)) {
  die('No users found in Supabase Auth');
}

$created = 0;
$updated = 0;
$errors = [];

foreach ($users as $authUser) {
  $userId = $authUser['id'] ?? null;
  if (!$userId) continue;
  
  $email = $authUser['email'] ?? '';
  $userMetadata = $authUser['user_metadata'] ?? [];
  $fullName = $userMetadata['full_name'] ?? $authUser['raw_user_meta_data']['full_name'] ?? '';
  
  // Try to get role from user_metadata
  $role = $userMetadata['role'] ?? $authUser['raw_user_meta_data']['role'] ?? 'user';
  
  // If no full_name, try email prefix or default
  if (empty($fullName)) {
    $fullName = !empty($email) ? explode('@', $email)[0] : 'User';
  }
  
  // Check if profile exists
  if (in_array($userId, $existingProfileIds)) {
    // Profile exists, update email if needed
    $updateCtx = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode([
          'email' => $email,
          'full_name' => $fullName
        ])
      ]
    ]);
    
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId",
      false,
      $updateCtx
    );
    $updated++;
  } else {
    // Profile doesn't exist, create it
    $profileData = [
      'id' => $userId,
      'full_name' => $fullName,
      'email' => $email,
      'role' => $role,
      'is_active' => true
    ];
    
    $createCtx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($profileData)
      ]
    ]);
    
    $createResponse = @file_get_contents(
      SUPABASE_URL . "/rest/v1/profiles",
      false,
      $createCtx
    );
    
    if ($createResponse !== false) {
      $created++;
    } else {
      $errors[] = "Failed to create profile for: $email";
    }
  }
}

$message = "Sync completed. Created: $created, Updated: $updated";
if (!empty($errors)) {
  $message .= ". Errors: " . count($errors);
}

header('Location: ' . BASE_PATH . '/pages/users.php?success=' . urlencode($message));
exit;

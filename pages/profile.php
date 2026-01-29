<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check - Profile accessible to all authenticated users */
// No specific permission required for profile

// Get user ID from session - this is the source of truth
$userId = $_SESSION['user']['id'] ?? null;
$userEmail = $_SESSION['user']['email'] ?? null;
$userName = $_SESSION['user']['name'] ?? null;
$userRole = $_SESSION['user']['role'] ?? null;

if (!$userId) {
  header('Location: dashboard.php?error=' . urlencode('Session expired. Please login again.'));
  exit;
}

// Log for debugging
error_log("Profile page accessed - Session User ID: $userId, Email: $userEmail, Name: $userName");

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

/* Fetch current user profile */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Start with session data (most reliable - set during login)
$profile = [
  'id' => $userId,
  'full_name' => $userName ?: 'User',
  'email' => $userEmail ?: '',
  'role' => $userRole
];

// Try to fetch updated email from database (email might have changed)
// URL encode the user ID to prevent injection
$encodedUserId = rawurlencode($userId);
$profileUrl = SUPABASE_URL . "/rest/v1/profiles?id=eq.$encodedUserId&select=id,full_name,email,role&limit=1";

$profileResponse = @file_get_contents($profileUrl, false, $ctx);

if ($profileResponse !== false) {
  $profiles = json_decode($profileResponse, true);
  
  if (json_last_error() === JSON_ERROR_NONE && is_array($profiles) && !empty($profiles)) {
    $dbProfile = $profiles[0];
    $dbProfileId = $dbProfile['id'] ?? null;
    
    // Only use database data if ID matches exactly
    if ($dbProfileId === $userId) {
      // Update email and full_name from database (in case they were updated)
      if (!empty($dbProfile['email'])) {
        $profile['email'] = $dbProfile['email'];
      }
      if (!empty($dbProfile['full_name'])) {
        $profile['full_name'] = $dbProfile['full_name'];
      }
      if (!empty($dbProfile['role'])) {
        $profile['role'] = $dbProfile['role'];
      }
      error_log("Profile data updated from database for user ID: $userId");
    } else {
      error_log("WARNING: Database profile ID ($dbProfileId) doesn't match session user ID ($userId). Using session data.");
    }
  }
} else {
  error_log("Could not fetch profile from database for user ID: $userId. Using session data.");
}

// Final check - ensure we never use wrong user's data
if (isset($profile['id']) && $profile['id'] !== $userId) {
  error_log("CRITICAL: Profile ID mismatch detected! Resetting to session data.");
  $profile = [
    'id' => $userId,
    'full_name' => $userName ?: 'User',
    'email' => $userEmail ?: '',
    'role' => $userRole
  ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>My Profile</h2>
      <p class="muted">Update your name and password</p>
    </div>
    <div class="actions">
      <a href="dashboard.php" class="btn btn-sm btn-secondary">Back to Dashboard</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php 
  // Debug info (remove in production)
  if (defined('BASE_PATH') && BASE_PATH === '/training-management-system' && isset($_GET['debug'])): 
  ?>
    <div style="background: #fef3c7; color: #92400e; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-family: monospace; font-size: 12px;">
      <strong>Debug Info:</strong><br>
      Session User ID: <?= htmlspecialchars($userId) ?><br>
      Session Email: <?= htmlspecialchars($userEmail ?? 'N/A') ?><br>
      Session Name: <?= htmlspecialchars($userName ?? 'N/A') ?><br>
      Profile ID: <?= htmlspecialchars($profile['id'] ?? 'N/A') ?><br>
      Profile Email: <?= htmlspecialchars($profile['email'] ?? 'N/A') ?><br>
      Profile Name: <?= htmlspecialchars($profile['full_name'] ?? 'N/A') ?><br>
      Match: <?= ($profile['id'] ?? null) === $userId ? '✓ YES' : '✗ NO - MISMATCH!' ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" action="../api/users/update_profile.php" id="profileForm">
      <?= csrfField() ?>
      <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">

      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($profile['email'] ?? $userEmail ?? '') ?>" disabled style="background: #f3f4f6; cursor: not-allowed;">
        <small style="color: #6b7280; display: block; margin-top: 4px;">Email cannot be changed</small>
        <?php if (isset($profile['id']) && $profile['id'] !== $userId): ?>
          <small style="color: #dc2626; display: block; margin-top: 4px;">⚠️ Warning: Profile ID mismatch detected!</small>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? $userName ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" id="new_password" placeholder="Leave blank to keep current password">
        <small style="color: #6b7280; display: block; margin-top: 4px;">Leave blank if you don't want to change password</small>
      </div>

      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password">
      </div>

      <div class="form-group">
        <label>Current Password *</label>
        <input type="password" name="current_password" required placeholder="Enter current password to confirm changes">
        <small style="color: #6b7280; display: block; margin-top: 4px;">Required to verify your identity</small>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn">Update Profile</button>
        <a href="dashboard.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
  const newPassword = document.getElementById('new_password').value;
  const confirmPassword = document.getElementById('confirm_password').value;

  if (newPassword && newPassword !== confirmPassword) {
    e.preventDefault();
    alert('New password and confirm password do not match');
    return false;
  }

  if (newPassword && newPassword.length < 6) {
    e.preventDefault();
    alert('Password must be at least 6 characters long');
    return false;
  }
});
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>

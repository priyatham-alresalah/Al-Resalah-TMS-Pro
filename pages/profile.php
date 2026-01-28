<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$userId = $_SESSION['user']['id'];
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

$profile = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId&select=id,full_name,email",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$profile) {
  header('Location: dashboard.php?error=' . urlencode('Profile not found'));
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/training-management-system/favicon.ico">
  <link rel="stylesheet" href="../assets/css/style.css">
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

  <div class="card">
    <form method="post" action="../api/users/update_profile.php" id="profileForm">
      <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">

      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($profile['email']) ?>" disabled style="background: #f3f4f6; cursor: not-allowed;">
        <small style="color: #6b7280; display: block; margin-top: 4px;">Email cannot be changed</small>
      </div>

      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" required>
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

<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('users', 'view');

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

/* Supabase context */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Fetch all users from profiles table
// Note: Using service role key bypasses RLS, so we should get all records
$usersUrl = SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,is_active,created_at&order=created_at.desc";

$usersResponse = @file_get_contents($usersUrl, false, $ctx);

if ($usersResponse === false) {
  $error = error_get_last();
  error_log("Failed to fetch users from Supabase. URL: " . $usersUrl . " Error: " . ($error['message'] ?? 'Unknown'));
  $users = [];
} else {
  $users = json_decode($usersResponse, true);
  
  // Handle JSON decode errors
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error in users.php: " . json_last_error_msg() . " Response: " . substr($usersResponse, 0, 500));
    $users = [];
  } else {
    $users = $users ?: [];
  }
  
  // Debug logging
  error_log("Users fetched: " . count($users) . " users found");
  
  // Additional debug: log user IDs
  if (!empty($users)) {
    $userIds = array_column($users, 'id');
    error_log("User IDs: " . implode(', ', array_slice($userIds, 0, 10)));
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Users</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Users</h2>
      <p class="muted">Manage system users and access</p>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="../api/users/sync_profiles.php" class="btn" style="background:#6366f1;" onclick="return confirm('This will sync all users from Supabase Auth to profiles table. Continue?')">🔄 Sync Users</a>
      <a href="user_create.php" class="btn">+ Create User</a>
    </div>
  </div>

  <?php if (isset($_GET['success'])): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <?php 
  // Filter valid users (those with IDs) and re-index array
  $validUsers = array_values(array_filter($users, function($u) {
    return !empty($u['id']) && isset($u['id']);
  }));
  $validCount = count($validUsers);
  
  // Debug logging
  error_log("Users page - Total fetched: " . count($users) . ", Valid: " . $validCount);
  if ($validCount > 0) {
    error_log("Valid users: " . implode(', ', array_column($validUsers, 'id')));
  }
  ?>
  
  <?php if ($validCount > 0): ?>
    <div style="background: #f3f4f6; color: #374151; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;">
      Showing <strong><?= $validCount ?></strong> user(s) from profiles table.
      <?php if (count($users) > $validCount): ?>
        <span style="color: #dc2626;">⚠️ <?= count($users) - $validCount ?> user(s) skipped due to missing data.</span>
      <?php endif; ?>
      <?php if ($validCount < 10): ?>
        <span style="color: #dc2626;">⚠️ Some users may be missing. Click "Sync Users" to sync from Supabase Auth.</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th class="col-actions">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($validUsers)): 
      foreach ($validUsers as $u): 
        $fullName = trim($u['full_name'] ?? '') ?: '-';
        $email = trim($u['email'] ?? '') ?: '-';
        $role = trim($u['role'] ?? '') ?: 'user';
        $isActive = isset($u['is_active']) ? (bool)$u['is_active'] : true;
        $createdAt = $u['created_at'] ?? null;
    ?>
      <tr>
        <td><?= htmlspecialchars($fullName) ?></td>
        <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($email) ?></td>
        <td><?= ucfirst($role) ?></td>
        <td>
          <?= $isActive
            ? '<span class="badge-success">Active</span>'
            : '<span class="badge-danger">Inactive</span>' ?>
        </td>
        <td><?= $createdAt ? date('d M Y', strtotime($createdAt)) : '-' ?></td>
        <td class="col-actions">
          <div class="action-menu-wrapper">
            <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
              &#8942;
            </button>
            <div class="action-menu">
              <a href="user_edit.php?id=<?= htmlspecialchars($u['id']) ?>">Edit</a>

              <form action="../api/users/toggle_status.php" method="post" style="margin: 0;">
                <?php require '../includes/csrf.php'; echo csrfField(); ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #dc2626;">
                  <?= $isActive ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>

              <form action="../api/users/reset_password.php" method="post" style="margin: 0;">
                <?php require '../includes/csrf.php'; echo csrfField(); ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #dc2626;">
                  Reset Password
                </button>
              </form>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr>
        <td colspan="6">
          <div style="padding: 20px; text-align: center;">
            <p>No users found</p>
            <?php if (count($users) > 0): ?>
              <p style="color: #dc2626; font-size: 12px;">
                Note: <?= count($users) ?> user(s) were fetched but filtered out (missing IDs or invalid data).
                <br>Check PHP error logs for details.
              </p>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endif; ?>
    
    <?php 
    // Debug: Show raw data if in development
    if (defined('BASE_PATH') && BASE_PATH === '/training-management-system' && isset($_GET['debug'])): 
    ?>
      <tr>
        <td colspan="6" style="background:#fef3c7;padding:20px;">
          <strong>Debug Info:</strong><br>
          Total users fetched: <?= count($users) ?><br>
          Valid users: <?= $validCount ?><br>
          <pre><?= htmlspecialchars(print_r($users, true)) ?></pre>
        </td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<script>
  document.addEventListener('click', function (event) {
    const isToggle = event.target.closest('.action-menu-toggle');
    const wrappers = document.querySelectorAll('.action-menu-wrapper');

    wrappers.forEach(function (wrapper) {
      const menu = wrapper.querySelector('.action-menu');
      if (!menu) return;

      if (isToggle && wrapper.contains(isToggle)) {
        const isOpen = menu.classList.contains('open');
        // close all first
        document.querySelectorAll('.action-menu.open').forEach(function (openMenu) {
          openMenu.classList.remove('open');
        });
        // toggle current
        if (!isOpen) {
          menu.classList.add('open');
        }
      } else {
        menu.classList.remove('open');
      }
    });
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




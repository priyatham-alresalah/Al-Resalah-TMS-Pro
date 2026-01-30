<?php
require '../includes/config.php';
require '../includes/auth_check.php';

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

// Fetch all users - add limit to ensure we get all
$usersUrl = SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,is_active,created_at&order=created_at.desc&limit=1000";

$usersResponse = @file_get_contents($usersUrl, false, $ctx);

if ($usersResponse === false) {
  $error = error_get_last();
  error_log("Failed to fetch users: " . ($error['message'] ?? 'Unknown'));
  $users = [];
} else {
  $users = json_decode($usersResponse, true);
  
  // Handle JSON decode errors
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    $users = [];
  } else {
    // Ensure $users is always an array
    if (!is_array($users)) {
      $users = [];
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Users</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
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
      <a href="<?= BASE_PATH ?>/api/users/sync_profiles.php" class="btn" style="background:#6366f1;" onclick="return confirm('This will sync all users from Supabase Auth to profiles table. Continue?')">🔄 Sync Users</a>
      <a href="user_create.php" class="btn">+ Create User</a>
    </div>
  </div>

  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <?php 
  // Debug: Show count
  $userCount = count($users);
  if ($userCount > 0): 
  ?>
    <div style="background: #f3f4f6; color: #374151; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;">
      Showing <strong><?= $userCount ?></strong> user(s) from profiles table.
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

    <?php 
    if (!empty($users) && is_array($users)): 
      foreach ($users as $u): 
        // Skip if user doesn't have required fields
        if (empty($u['id'])) {
          continue;
        }
        
        $fullName = $u['full_name'] ?? '-';
        $email = $u['email'] ?? '-';
        $role = $u['role'] ?? 'user';
        $isActive = isset($u['is_active']) ? (bool)$u['is_active'] : true;
        $createdAt = $u['created_at'] ?? null;
        $userId = $u['id'];
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
              <a href="user_edit.php?id=<?= htmlspecialchars($userId) ?>">Edit</a>

              <form action="<?= BASE_PATH ?>/api/users/toggle_status.php" method="post">
                <?php require '../includes/csrf.php'; echo csrfField(); ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                <button type="submit" class="danger">
                  <?= $isActive ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>

              <form action="<?= BASE_PATH ?>/api/users/reset_password.php" method="post">
                <?php require '../includes/csrf.php'; echo csrfField(); ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                <button type="submit" class="danger">
                  Reset Password
                </button>
              </form>
            </div>
          </div>
        </td>
      </tr>
    <?php 
      endforeach; 
    else: 
    ?>
      <tr>
        <td colspan="6">No users found</td>
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

<?php
require 'includes/config.php';
require 'includes/auth_check.php';

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

$users = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,is_active,created_at&order=created_at.desc",
    false,
    $ctx
  ),
  true
);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Users</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Users</h2>
      <p class="muted">Manage system users and access</p>
    </div>
    <a href="user_create.php" class="btn">+ Create User</a>
  </div>

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

    <?php if (!empty($users)): foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['full_name']) ?></td>
        <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
        <td><?= ucfirst($u['role']) ?></td>
        <td>
          <?= $u['is_active']
            ? '<span class="badge-success">Active</span>'
            : '<span class="badge-danger">Inactive</span>' ?>
        </td>
        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td class="col-actions">
          <div class="action-menu-wrapper">
            <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
              &#8942;
            </button>
            <div class="action-menu">
              <a href="user_edit.php?id=<?= $u['id'] ?>">Edit</a>

              <form action="api/users/toggle_status.php" method="post">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                <button type="submit" class="danger">
                  <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>

              <form action="api/users/reset_password.php" method="post">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="danger">
                  Reset Password
                </button>
              </form>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
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

<?php include 'layout/footer.php'; ?>
</body>
</html>

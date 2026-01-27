<?php
require 'includes/config.php';
require 'includes/auth_check.php';

/* ONLY ADMIN CAN ACCESS */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

/* SUPABASE CONTEXT */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/*
  Fetch profiles
  Email comes from auth.users view if you exposed it
  If not, we show email from session mapping or metadata
*/
$users = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,role,is_active,created_at&order=created_at.desc",
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
        <th>User ID</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th width="260">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($users)): ?>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
          <td style="font-size:12px;color:#6b7280;">
            <?= htmlspecialchars($u['id']) ?>
          </td>
          <td><?= ucfirst(htmlspecialchars($u['role'])) ?></td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge-success">Active</span>
            <?php else: ?>
              <span class="badge-danger">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <a href="user_edit.php?id=<?= $u['id'] ?>">Edit</a>
            &nbsp;|&nbsp;

            <form action="api/users/toggle_status.php"
                  method="post"
                  style="display:inline;"
                  onsubmit="return confirm('Are you sure?');">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
              <button type="submit" class="link-btn">
                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>

            &nbsp;|&nbsp;

            <form action="api/users/reset_password.php"
                  method="post"
                  style="display:inline;"
                  onsubmit="return confirm('Send password reset email to this user?');">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="link-btn">
                Reset Password
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="6">No users found</td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>

</body>
</html>

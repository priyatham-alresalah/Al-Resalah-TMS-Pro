<?php
require 'includes/config.php';
require 'includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
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
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Users</h2>
  <p class="muted">Manage system users</p>

  <a href="user_create.php">
    <button>Add User</button>
  </a>

  <table class="table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>

    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['full_name']) ?></td>
        <td><?= strtoupper($u['role']) ?></td>
        <td>
          <?php if ($u['is_active']): ?>
            <span class="badge-success">Active</span>
          <?php else: ?>
            <span class="badge-danger">Inactive</span>
          <?php endif; ?>
        </td>
        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td>
          <a href="user_edit.php?id=<?= $u['id'] ?>">Edit</a> |
          <a href="user_toggle.php?id=<?= $u['id'] ?>">
            <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
          </a>
        </td>
      </tr>
    <?php endforeach; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

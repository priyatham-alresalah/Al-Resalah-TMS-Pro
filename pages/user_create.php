<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('users', 'create');

if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create User</title>
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
      <h2>Create User</h2>
      <p class="muted">Add a new user and assign a role</p>
    </div>
    <div class="actions">
      <a href="users.php" class="btn btn-sm btn-secondary">Back to Users</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <div class="form-card">
    <form action="../api/users/create.php" method="post">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" required autocomplete="name">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required autocomplete="email">
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </div>

      <div class="form-group">
        <label>Role</label>
        <select name="role" required>
          <option value="admin">Admin</option>
          <option value="bdm">BDM</option>
          <option value="bdo">BDO</option>
          <option value="coordinator">Coordinator</option>
          <option value="trainer">Trainer</option>
          <option value="accounts">Accounts</option>
        </select>
      </div>

      <div class="form-actions">
        <button class="btn">Create User</button>
        <a href="users.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




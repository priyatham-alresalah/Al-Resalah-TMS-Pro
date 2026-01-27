<?php
require 'includes/config.php';
require 'includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create User</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Create User</h2>
  <p class="muted">Add a new system user</p>

  <div class="form-card">

    <form method="post" action="api/users/create.php">

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <div class="form-group">
        <label>Role</label>
        <select name="role" required>
          <option value="admin">Admin</option>
          <option value="accounts">Accounts</option>
          <option value="bdm">BDM</option>
          <option value="bdo">BDO</option>
          <option value="coordinator">Coordinator</option>
          <option value="trainer">Trainer</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit">Create User</button>
        <a href="users.php">Cancel</a>
      </div>

    </form>

  </div>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

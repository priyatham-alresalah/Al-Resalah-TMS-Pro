<?php
require '../includes/config.php';
require '../includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$id = $_GET['id'] ?? null;
if (!$id) die('Invalid user');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$userResponse = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$id&select=id,full_name,role",
    false,
    $ctx
  ),
  true
);

if (!$userResponse || !isset($userResponse[0])) {
  die('User not found');
}

$user = $userResponse[0];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit User</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Edit User</h2>
      <p class="muted">Update user details and access</p>
    </div>
    <div class="actions">
      <a href="users.php" class="btn btn-sm btn-secondary">Back to Users</a>
    </div>
  </div>

  <div class="form-card">

    <form action="../api/users/update.php" method="post">
      <input type="hidden" name="id" value="<?= $user['id'] ?>">

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
      </div>

      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
          <option value="bdm" <?= $user['role']=='bdm'?'selected':'' ?>>BDM</option>
          <option value="bdo" <?= $user['role']=='bdo'?'selected':'' ?>>BDO</option>
          <option value="coordinator" <?= $user['role']=='coordinator'?'selected':'' ?>>Coordinator</option>
          <option value="trainer" <?= $user['role']=='trainer'?'selected':'' ?>>Trainer</option>
          <option value="accounts" <?= $user['role']=='accounts'?'selected':'' ?>>Accounts</option>
        </select>
      </div>

      <div class="form-actions">
        <button class="btn">Update User</button>
        <a href="users.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




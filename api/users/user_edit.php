<?php
require 'includes/config.php';
require 'includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
    die('Access denied');
}

$id = $_GET['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$user = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$id",
    false,
    $ctx
  ),
  true
)[0];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit User</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Edit User</h2>

  <form method="POST" action="api/users/update.php" class="form-card">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-group">
      <label>Full Name</label>
      <input name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
    </div>

    <div class="form-group">
      <label>Role</label>
      <select name="role">
        <?php foreach (['admin','accounts','bdm','bdo','coordinator','trainer'] as $r): ?>
          <option value="<?= $r ?>" <?= $user['role']===$r?'selected':'' ?>>
            <?= strtoupper($r) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit">Update</button>
  </form>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

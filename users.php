<?php
require 'includes/config.php';
require 'includes/auth_check.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

/* ---------- FETCH USERS ---------- */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,role,is_active",
  false,
  $ctx
);

$users = json_decode($response, true);

/* Allowed roles */
$roles = ['admin', 'bdm', 'bdo', 'coordinator', 'trainer', 'accounts'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Users | Training Management System</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">

  <h2>Users Management</h2>

  <!-- ================= CREATE USER ================= -->
  <section style="margin-bottom:30px;">
    <h3>Create User (Invite)</h3>

    <form method="post" action="api/users/create.php" class="form-inline">
      <input type="text" name="full_name" placeholder="Full Name" required>

      <input type="email" name="email" placeholder="Email" required>

      <select name="role" required>
        <option value="">Select Role</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r ?>"><?= strtoupper($r) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Create & Send Invite</button>
    </form>
  </section>

  <!-- ================= USERS LIST ================= -->
  <section>
    <h3>Existing Users</h3>

    <table class="table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>

      <?php if (!empty($users)): ?>
        <?php foreach ($users as $u): ?>
          <tr>
            <form method="post" action="api/users/update.php">

              <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>

              <td>
                <select name="role" required>
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>>
                      <?= strtoupper($r) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>

              <td>
                <select name="is_active" required>
                  <option value="1" <?= $u['is_active'] ? 'selected' : '' ?>>Active</option>
                  <option value="0" <?= !$u['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
              </td>

              <td>
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit">Save</button>
              </td>

            </form>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4">No users found</td>
        </tr>
      <?php endif; ?>

      </tbody>
    </table>
  </section>

</main>

<?php include 'layout/footer.php'; ?>

</body>
</html>

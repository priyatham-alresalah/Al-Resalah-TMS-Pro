<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('training_master', 'view');

$role = $_SESSION['user']['role'];

/* Only admin & accounts */
if (!in_array($role, ['admin','accounts'])) {
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

$courses = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_master?order=course_name.asc",
    false,
    $ctx
  ),
  true
);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Training Master</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Training Master</h2>
  <p style="margin-bottom:15px;color:#6b7280;">
    Manage training courses used across the system
  </p>

  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <!-- ADD COURSE -->
  <form method="post"
        action="<?= BASE_PATH ?>/api/training_master/create.php"
        class="form-inline"
        style="margin-bottom:20px;">
    <?= csrfField() ?>
    <input name="course_name" placeholder="Course Name *" required>
    <input name="duration" placeholder="Duration (e.g. 1 Day)">
    <button type="submit">Add Course</button>
  </form>

  <!-- LIST -->
  <table class="table">
    <thead>
      <tr>
        <th>Course Name</th>
        <th>Duration</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($courses)): ?>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['course_name']) ?></td>
          <td><?= htmlspecialchars($c['duration'] ?? '-') ?></td>
          <td><?= $c['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td>
            <a href="training_master.php?edit=<?= $c['id'] ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="4">No courses found</td></tr>
    <?php endif; ?>

    </tbody>
  </table>

  <!-- EDIT -->
  <?php if (isset($_GET['edit'])):
    $edit = array_values(array_filter(
      $courses,
      fn($x) => $x['id'] === $_GET['edit']
    ))[0] ?? null;

    if ($edit):
  ?>
    <hr style="margin:30px 0;">

    <h3>Edit Course</h3>

    <form method="post"
          action="../api/training_master/update.php"
          class="form-inline">
      <?= csrfField() ?>

      <input type="hidden" name="id" value="<?= $edit['id'] ?>">

      <input name="course_name"
             value="<?= htmlspecialchars($edit['course_name']) ?>"
             required>

      <input name="duration"
             value="<?= htmlspecialchars($edit['duration'] ?? '') ?>">

      <select name="is_active">
        <option value="true"  <?= $edit['is_active']?'selected':'' ?>>Active</option>
        <option value="false" <?= !$edit['is_active']?'selected':'' ?>>Inactive</option>
      </select>

      <button type="submit">Update</button>
      <a href="training_master.php" class="btn-cancel" style="margin-left:10px;">Cancel</a>
    </form>
  <?php endif; endif; ?>

</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




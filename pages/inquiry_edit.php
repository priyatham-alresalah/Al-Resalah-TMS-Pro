<?php
require '../includes/config.php';
require '../includes/auth_check.php';

if (!in_array($_SESSION['user']['role'], ['admin','accounts'])) {
  die('Access denied');
}

$id = $_GET['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: ".SUPABASE_SERVICE."\r\n".
      "Authorization: Bearer ".SUPABASE_SERVICE
  ]
]);

$inq = json_decode(
  file_get_contents(SUPABASE_URL."/rest/v1/inquiries?id=eq.$id", false, $ctx),
  true
)[0];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Inquiry</title>
  <link rel="stylesheet" href="../assets/css/layout.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="card">
    <div class="card-header">
      <h2>Edit Inquiry</h2>
    </div>

    <form method="post" action="../api/inquiries/update.php">
      <input type="hidden" name="id" value="<?= $inq['id'] ?>">

      <div class="form-grid">
        <div class="form-group">
          <label>Course Name</label>
          <input name="course_name" value="<?= htmlspecialchars($inq['course_name']) ?>">
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="new" <?= $inq['status']=='new'?'selected':'' ?>>New</option>
            <option value="quoted" <?= $inq['status']=='quoted'?'selected':'' ?>>Quoted</option>
            <option value="closed" <?= $inq['status']=='closed'?'selected':'' ?>>Closed</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button>Update Inquiry</button>
        <a href="inquiries.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




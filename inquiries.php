<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$role = $_SESSION['user']['role'];

/* ---------------------------
   FETCH CLIENTS (for map + dropdown)
---------------------------- */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$clients = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/clients?select=id,company_name", false, $ctx),
  true
);

$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c['company_name'];
}

/* ---------------------------
   FETCH INQUIRIES
---------------------------- */
$inquiries = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/inquiries?order=created_at.desc", false, $ctx),
  true
);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Inquiries</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Inquiries</h2>
  <p class="muted">Client training inquiries and conversion</p>

  <!-- ADD INQUIRY -->
  <?php if (in_array($role, ['admin','bdm','bdo','accounts'])): ?>
    <form method="post" action="api/inquiries/create.php" class="form-inline" style="margin-bottom:20px;">
      <select name="client_id" required>
        <option value="">Select Client *</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>">
            <?= htmlspecialchars($c['company_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input name="course_name" placeholder="Course Name *" required>
      <button type="submit">Add Inquiry</button>
    </form>
  <?php endif; ?>

  <!-- INQUIRIES LIST -->
  <table class="table">
    <thead>
      <tr>
        <th>Client</th>
        <th>Course</th>
        <th>Status</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>

    <?php if ($inquiries): foreach ($inquiries as $i): ?>
      <tr>
        <td>
          <?= htmlspecialchars($clientMap[$i['client_id']] ?? 'Unknown Client') ?>
        </td>
        <td><?= htmlspecialchars($i['course_name']) ?></td>
        <td>
          <span class="<?= $i['status']==='CLOSED'?'badge-success':'badge-warning' ?>">
            <?= strtoupper($i['status']) ?>
          </span>
        </td>
        <td><?= date('d M Y', strtotime($i['created_at'])) ?></td>
        <td>
          <?php if ($i['status'] === 'CLOSED'): ?>
            <a href="convert_to_training.php?id=<?= $i['id'] ?>">Convert</a>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="5">No inquiries found</td></tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

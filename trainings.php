<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH TRAININGS */
$trainings = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?order=training_date.desc",
    false,
    $ctx
  ),
  true
) ?: [];

/* FETCH CLIENTS */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients",
    false,
    $ctx
  ),
  true
) ?: [];

$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Trainings</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">

<h2>Trainings</h2>
<p class="muted">Manage training lifecycle</p>

<table class="table">
  <thead>
    <tr>
      <th>Client</th>
      <th>Course</th>
      <th>Date</th>
      <th>Status</th>
      <th width="340">Actions</th>
    </tr>
  </thead>
  <tbody>

<?php if ($trainings): foreach ($trainings as $t): ?>
<tr>
  <td><?= htmlspecialchars($clientMap[$t['client_id']]['company_name'] ?? '-') ?></td>
  <td><?= htmlspecialchars($t['course_name']) ?></td>
  <td><?= date('d M Y', strtotime($t['training_date'])) ?></td>
  <td>
    <span class="badge badge-<?= $t['status'] ?>">
      <?= strtoupper($t['status']) ?>
    </span>
  </td>
  <td>

    <!-- Candidates -->
    <a class="btn small"
       href="training_candidates.php?training_id=<?= $t['id'] ?>">
       Candidates
    </a>

    <!-- Status transitions -->
    <?php if ($t['status'] === 'scheduled'): ?>
      <form method="post" action="api/trainings/update_status.php" style="display:inline">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <input type="hidden" name="status" value="ongoing">
        <button class="btn small">Start</button>
      </form>
    <?php endif; ?>

    <?php if ($t['status'] === 'ongoing'): ?>
      <form method="post" action="api/trainings/update_status.php" style="display:inline">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <input type="hidden" name="status" value="completed">
        <button class="btn small btn-success">Complete</button>
      </form>
    <?php endif; ?>

    <!-- Certificates -->
    <?php if ($t['status'] === 'completed'): ?>
      <a class="btn small btn-warning"
         href="issue_certificates.php?training_id=<?= $t['id'] ?>">
         Issue Certificates
      </a>
    <?php endif; ?>

  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5">No trainings found</td></tr>
<?php endif; ?>

  </tbody>
</table>

</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

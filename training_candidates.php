<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$training_id = $_GET['training_id'] ?? null;
if (!$training_id) {
  die('Training ID missing');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* ===============================
   FETCH TRAINING
================================ */
$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$training_id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$training) {
  die('Training not found');
}

/* ===============================
   FETCH CLIENT
================================ */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$training['client_id']}&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* ===============================
   FETCH INQUIRY (OPTIONAL)
================================ */
$courseName = '-';
if (!empty($training['course_name'])) {
  $courseName = $training['course_name'];
} elseif (!empty($training['inquiry_id'])) {
  $inq = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$training['inquiry_id']}&limit=1",
      false,
      $ctx
    ),
    true
  )[0] ?? null;

  if ($inq) {
    $courseName = $inq['course_name'];
  }
}

/* ===============================
   FETCH LINKED CANDIDATES
================================ */
$links = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id",
    false,
    $ctx
  ),
  true
) ?? [];

$linkedIds = array_column($links, 'candidate_id');

/* ===============================
   FETCH ALL CANDIDATES (CLIENT)
================================ */
$candidates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?client_id=eq.{$training['client_id']}",
    false,
    $ctx
  ),
  true
) ?? [];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Training Candidates</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">

<h2>Training Candidates</h2>
<p class="muted">
  <strong>Client:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?> |
  <strong>Course:</strong> <?= htmlspecialchars($courseName) ?> |
  <strong>Date:</strong> <?= date('d M Y', strtotime($training['training_date'])) ?>
</p>

<!-- ===============================
     ADD CANDIDATE
================================ -->
<form method="post" action="api/training_candidates/add.php" class="form-inline" style="margin-bottom:20px;">
  <input type="hidden" name="training_id" value="<?= $training_id ?>">

  <select name="candidate_id" required>
    <option value="">Select candidate</option>
    <?php foreach ($candidates as $c): ?>
      <?php if (!in_array($c['id'], $linkedIds)): ?>
        <option value="<?= $c['id'] ?>">
          <?= htmlspecialchars($c['full_name']) ?>
        </option>
      <?php endif; ?>
    <?php endforeach; ?>
  </select>

  <button type="submit">Add Candidate</button>
</form>

<!-- ===============================
     LINKED CANDIDATES TABLE
================================ -->
<table class="table">
  <thead>
    <tr>
      <th>Name</th>
      <th>Email</th>
      <th>Phone</th>
      <th width="100">Action</th>
    </tr>
  </thead>
  <tbody>

<?php if (!empty($links)): foreach ($links as $l): ?>

<?php
$candidate = null;
foreach ($candidates as $c) {
  if ($c['id'] === $l['candidate_id']) {
    $candidate = $c;
    break;
  }
}
?>

<tr>
  <td><?= htmlspecialchars($candidate['full_name'] ?? '-') ?></td>
  <td><?= htmlspecialchars($candidate['email'] ?? '-') ?></td>
  <td><?= htmlspecialchars($candidate['phone'] ?? '-') ?></td>
  <td>
    <form method="post" action="api/training_candidates/remove.php"
          onsubmit="return confirm('Remove this candidate?');">
      <input type="hidden" name="training_id" value="<?= $training_id ?>">
      <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
      <button class="btn small danger">Remove</button>
    </form>
  </td>
</tr>

<?php endforeach; else: ?>
<tr>
  <td colspan="4">No candidates added</td>
</tr>
<?php endif; ?>

  </tbody>
</table>

<a href="trainings.php" class="btn secondary">← Back to Trainings</a>

</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('trainings', 'view');

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

/* FETCH TRAINING */
$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$training_id&select=id,training_date,status,course_name,client_id",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$training) {
  die('Training not found');
}

/* FETCH CLIENT */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$training['client_id']}",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* FETCH ALL CANDIDATES FOR CLIENT */
$candidates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?client_id=eq.{$training['client_id']}&order=created_at.desc",
    false,
    $ctx
  ),
  true
);

/* FETCH ASSIGNED CANDIDATES */
$assigned = json_decode(
  file_get_contents(
    SUPABASE_URL .
    "/rest/v1/training_candidates?training_id=eq.$training_id&select=id,candidate_id,attended",
    false,
    $ctx
  ),
  true
);

$assignedMap = [];
foreach ($assigned as $a) {
  $assignedMap[$a['candidate_id']] = $a;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Training Candidates</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">

  <h2>Training Candidates</h2>
  <p class="muted">
    <?= htmlspecialchars($client['company_name'] ?? '-') ?> |
    <?= htmlspecialchars($training['course_name']) ?> |
    <?= date('d M Y', strtotime($training['training_date'])) ?>
  </p>

  <!-- ADD CANDIDATE -->
  <?php if ($training['status'] !== 'completed'): ?>
    <form method="post" action="../api/training_candidates/add.php" class="form-inline" style="margin-bottom:20px;">
      <input type="hidden" name="training_id" value="<?= $training_id ?>">
      <select name="candidate_id" required>
        <option value="">Select Candidate</option>
        <?php foreach ($candidates as $c): ?>
          <?php if (!isset($assignedMap[$c['id']])): ?>
            <option value="<?= $c['id'] ?>">
              <?= htmlspecialchars($c['full_name']) ?>
            </option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <button type="submit">Add to Training</button>
    </form>
  <?php endif; ?>

  <!-- LIST -->
  <table class="table">
    <thead>
      <tr>
        <th>Candidate</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Attended</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($assigned)): ?>
      <?php foreach ($assigned as $a): ?>
        <?php
          $cand = array_values(
            array_filter($candidates, fn($c) => $c['id'] === $a['candidate_id'])
          )[0] ?? null;
        ?>
        <tr>
          <td><?= htmlspecialchars($cand['full_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($cand['email'] ?? '-') ?></td>
          <td><?= htmlspecialchars($cand['phone'] ?? '-') ?></td>
          <td>
            <?= $a['attended'] ? 'Yes' : 'No' ?>
          </td>
          <td>
            <?php if ($training['status'] !== 'completed'): ?>
              <a href="../api/training_candidates/remove.php?id=<?= $a['id'] ?>"
                 onclick="return confirm('Remove candidate?')">
                Remove
              </a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="5">No candidates assigned</td></tr>
    <?php endif; ?>

    </tbody>
  </table>

  <br>
  <a href="trainings.php">← Back to Trainings</a>

</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




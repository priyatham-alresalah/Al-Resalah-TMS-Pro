<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/csrf.php';
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

/* FETCH ATTENDANCE CHECKPOINT */
$attendanceCheckpoint = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_checkpoints?training_id=eq.$training_id&checkpoint=eq.attendance_verified&select=id,completed",
    false,
    $ctx
  ),
  true
);

$isAttendanceVerified = !empty($attendanceCheckpoint) && ($attendanceCheckpoint[0]['completed'] ?? false);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Training Candidates</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">

  <h2>Training Candidates</h2>
  <p class="muted">
    <?= htmlspecialchars($client['company_name'] ?? '-') ?> |
    <?= htmlspecialchars($training['course_name']) ?> |
    <?php
      $trainingDate = $training['training_date'] ?? null;
      if (!empty($trainingDate)) {
        $ts = strtotime($trainingDate);
        echo $ts !== false ? date('d M Y', $ts) : '-';
      } else {
        echo '-';
      }
    ?>
  </p>
  <?php if ($training['status'] !== 'completed' && !empty($training['client_id'])): ?>
    <p style="margin-bottom: 16px;">
      <a href="<?= BASE_PATH ?>/pages/training_assign_candidates.php?id=<?= htmlspecialchars($training_id) ?>" class="btn btn-secondary">Bulk assign candidates</a>
      <span class="muted" style="margin-left: 8px; font-size: 13px;">(check/uncheck by client)</span>
    </p>
  <?php endif; ?>

  <!-- ADD CANDIDATE -->
  <?php if ($training['status'] !== 'completed'): ?>
    <form method="post" action="../api/training_candidates/add.php" class="form-inline" style="margin-bottom:20px;">
      <?= csrfField() ?>
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
      <button type="submit" class="btn btn-primary">Add to Training</button>
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
              <form method="post" action="<?= BASE_PATH ?>/api/training_candidates/remove.php" style="display:inline;" onsubmit="return confirm('Remove candidate?')">
                <?= csrfField() ?>
                <input type="hidden" name="training_id" value="<?= $training_id ?>">
                <input type="hidden" name="candidate_id" value="<?= $a['candidate_id'] ?>">
                <button type="submit" style="background:none;border:none;color:#dc3545;cursor:pointer;text-decoration:underline;padding:0;" aria-label="Remove candidate">Remove</button>
              </form>
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

  <?php if ($training['status'] !== 'completed' && !empty($assigned)): ?>
    <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 6px; border-left: 4px solid <?= $isAttendanceVerified ? '#28a745' : '#ffc107' ?>;">
      <?php if ($isAttendanceVerified): ?>
        <p style="margin: 0; color: #28a745; font-weight: 500;">
          ✓ Attendance Verified - You can now complete this training
        </p>
      <?php else: ?>
        <p style="margin: 0 0 10px 0; color: #856404; font-weight: 500;">
          ⚠ Attendance Not Verified
        </p>
        <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">
          Mark all candidates as attended, then verify attendance to complete this training.
        </p>
        <form method="post" action="<?= BASE_PATH ?>/api/trainings/verify_attendance.php" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="training_id" value="<?= $training_id ?>">
          <button type="submit" class="btn btn-success" onclick="return confirm('Verify attendance for all marked candidates? This will allow you to complete the training.')">
            Verify Attendance
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <br>
  <a href="<?= BASE_PATH ?>/pages/trainings.php" class="btn btn-secondary">← Back to Trainings</a>

</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




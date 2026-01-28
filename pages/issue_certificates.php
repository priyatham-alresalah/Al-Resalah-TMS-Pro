<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('certificates', 'create');

$training_id = $_GET['training_id'] ?? null;
if (!$training_id) {
  header('Location: trainings.php?error=' . urlencode('Training ID missing'));
  exit;
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
  header('Location: trainings.php?error=' . urlencode('Training not found'));
  exit;
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

/* FETCH ASSIGNED CANDIDATES */
$assigned = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
) ?: [];

if (empty($assigned)) {
  header('Location: trainings.php?error=' . urlencode('No candidates assigned to this training'));
  exit;
}

$candidateIds = array_column($assigned, 'candidate_id');

/* FETCH CANDIDATE DETAILS */
$candidates = [];
if (!empty($candidateIds)) {
  $candidateIdsStr = implode(',', $candidateIds);
  $candidates = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/candidates?id=in.($candidateIdsStr)&select=id,full_name,email",
      false,
      $ctx
    ),
    true
  ) ?: [];
}

/* FETCH EXISTING CERTIFICATES */
$existing = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
) ?: [];

$issuedMap = [];
foreach ($existing as $e) {
  $issuedMap[$e['candidate_id']] = true;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Issue Certificates</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Issue Certificates</h2>
      <p class="muted">
        <?= htmlspecialchars($client['company_name'] ?? '-') ?> |
        <?= htmlspecialchars($training['course_name']) ?> |
        <?= date('d M Y', strtotime($training['training_date'])) ?>
      </p>
    </div>
    <div class="actions">
      <a href="trainings.php" class="btn btn-sm btn-secondary">Back to Trainings</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($candidates)): ?>
    <div class="card">
      <p>No candidates found for this training.</p>
    </div>
  <?php else: ?>
    <div class="card">
      <form method="post" action="../api/certificates/issue_bulk.php">
        <?= csrfField() ?>
        <input type="hidden" name="training_id" value="<?= $training_id ?>">
        
        <table class="table">
          <thead>
            <tr>
              <th style="width: 40px;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
              </th>
              <th>Candidate Name</th>
              <th>Email</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($candidates as $c): 
              $isIssued = isset($issuedMap[$c['id']]);
            ?>
              <tr>
                <td>
                  <?php if (!$isIssued): ?>
                    <input type="checkbox" name="candidates[]" value="<?= $c['id'] ?>" class="candidate-checkbox">
                  <?php else: ?>
                    <span style="color: #16a34a;">✓</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['full_name']) ?></td>
                <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                <td>
                  <?php if ($isIssued): ?>
                    <span class="badge badge-success">Certificate Issued</span>
                  <?php else: ?>
                    <span class="badge badge-warning">Pending</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="form-actions" style="margin-top: 20px;">
          <button class="btn" type="submit">Issue Certificates for Selected</button>
          <a href="trainings.php" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</main>

<script>
  function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.candidate-checkbox');
    checkboxes.forEach(function(cb) {
      cb.checked = checkbox.checked;
    });
  }
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>

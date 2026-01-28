<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('certificates', 'update');

$id = $_GET['id'] ?? '';
if (!$id) {
  header('Location: certificates.php?error=' . urlencode('Certificate ID missing'));
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

/* FETCH CERTIFICATE */
$cert = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?id=eq.$id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$cert) {
  header('Location: certificates.php?error=' . urlencode('Certificate not found'));
  exit;
}

/* FETCH CANDIDATE */
$candidate = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.{$cert['candidate_id']}&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* FETCH TRAINING */
$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.{$cert['training_id']}&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Certificate</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Edit Certificate</h2>
      <p class="muted">Update certificate details</p>
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

  <div class="card">
    <form method="post" action="../api/certificates/update.php">
      <?= csrfField() ?>
      <input type="hidden" name="id" value="<?= $cert['id'] ?>">
      
      <div class="form-group">
        <label>Certificate Number</label>
        <input type="text" name="certificate_no" value="<?= htmlspecialchars($cert['certificate_no']) ?>" required>
      </div>

      <div class="form-group">
        <label>Candidate</label>
        <input type="text" value="<?= htmlspecialchars($candidate['full_name'] ?? '-') ?>" disabled>
        <small style="color: #6b7280;">Cannot be changed</small>
      </div>

      <div class="form-group">
        <label>Training Course</label>
        <input type="text" value="<?= htmlspecialchars($training['course_name'] ?? '-') ?>" disabled>
        <small style="color: #6b7280;">Cannot be changed</small>
      </div>

      <div class="form-group">
        <label>Issued Date</label>
        <input type="date" name="issued_date" value="<?= htmlspecialchars($cert['issued_date']) ?>" required>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="active" <?= ($cert['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="revoked" <?= ($cert['status'] ?? 'active') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
        </select>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Update Certificate</button>
        <a href="certificates.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




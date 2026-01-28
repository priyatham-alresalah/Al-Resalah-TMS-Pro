<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['candidate'])) {
  header('Location: login.php');
  exit;
}

$candidate = $_SESSION['candidate'];
$candidateId = $candidate['id'];

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$candidateRow = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.$candidateId&select=id,full_name,email,phone,client_id",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$candidateRow) {
  header('Location: dashboard.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php $portalNavActive = 'profile'; include '../layout/portal_header.php'; ?>

<main class="content" style="margin-left: 0; margin-top: 0; padding: 25px;">
  <div class="page-header">
    <div>
      <h2>My Profile</h2>
      <p class="muted">Update phone number or request email change</p>
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

  <div class="form-card">
    <form method="post" action="../api/portal/update_phone.php">
      <input type="hidden" name="type" value="candidate">
      <input type="hidden" name="id" value="<?= htmlspecialchars($candidateId) ?>">

      <div class="form-group">
        <label>Full Name</label>
        <input value="<?= htmlspecialchars($candidateRow['full_name'] ?? '') ?>" disabled style="background:#f3f4f6;">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input value="<?= htmlspecialchars($candidateRow['email'] ?? '') ?>" disabled style="background:#f3f4f6;">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input name="phone" value="<?= htmlspecialchars($candidateRow['phone'] ?? '') ?>" placeholder="+971..." autocomplete="tel">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Update Phone</button>
      </div>
    </form>
  </div>

  <div class="form-card" style="margin-top: 18px;">
    <form method="post" action="../api/portal/request_email_change.php">
      <input type="hidden" name="type" value="candidate">
      <input type="hidden" name="id" value="<?= htmlspecialchars($candidateId) ?>">

      <div class="form-group">
        <label>Request Email ID Change</label>
        <input type="email" name="new_email" placeholder="New email address" required autocomplete="email">
        <small style="color:#6b7280; display:block; margin-top:4px;">
          This will send a request email to <strong>cs@aresalah.com</strong>.
        </small>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Request Change Email ID</button>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>


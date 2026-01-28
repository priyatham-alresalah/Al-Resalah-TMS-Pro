<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('candidates', 'create');

/* SUPABASE CONTEXT */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch clients for dropdown */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?order=company_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Candidate</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Create Candidate</h2>
      <p class="muted">Add a new training participant</p>
    </div>
    <div class="actions">
      <a href="candidates.php" class="btn btn-sm btn-secondary">Back to Candidates</a>
    </div>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <form action="../api/candidates/create.php" method="post">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" required autocomplete="name">
      </div>

      <div class="form-group">
        <label>Company</label>
        <select name="client_id">
          <option value="">No Company (Individual)</option>
          <?php foreach ($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>">
              <?= htmlspecialchars($cl['company_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" autocomplete="email">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" autocomplete="tel">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Create Candidate</button>
        <a href="candidates.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>

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
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
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

  <div style="background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px; max-width: 700px;">
    <form action="<?= BASE_PATH ?>/api/candidates/create.php" method="post" id="candidateForm">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      
      <div style="margin-bottom: 24px;">
        <label for="full_name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Full Name *</label>
        <input type="text" id="full_name" name="full_name" required autocomplete="name" placeholder="Enter full name" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="client_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Company</label>
        <select id="client_id" name="client_id" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
          <option value="">No Company (Individual)</option>
          <?php foreach ($clients as $cl): ?>
            <option value="<?= htmlspecialchars($cl['id']) ?>">
              <?= htmlspecialchars($cl['company_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom: 24px;">
        <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" placeholder="Enter email address" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="phone" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Phone</label>
        <input type="text" id="phone" name="phone" autocomplete="tel" placeholder="Enter phone number" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-top: 32px; display: flex; gap: 12px; align-items: center;">
        <button type="submit" style="padding: 12px 24px; font-size: 14px; font-weight: 600; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s;">Create Candidate</button>
        <a href="candidates.php" style="padding: 12px 24px; text-decoration: none; color: #dc2626; border: 1px solid #dc2626; border-radius: 6px; display: inline-block; font-size: 14px; font-weight: 500; transition: all 0.2s;">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>

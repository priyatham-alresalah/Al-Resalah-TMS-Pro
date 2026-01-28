<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (!$id) die('Missing candidate ID');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$candidate = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.$id&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$candidate) die('Candidate not found');

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
  <title>Edit Candidate</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Edit Candidate</h2>
      <p class="muted">Update candidate details and company</p>
    </div>
    <div class="actions">
      <a href="candidates.php" class="btn btn-sm btn-secondary">Back to Candidates</a>
    </div>
  </div>

  <div class="form-card">
    <form method="post" action="../api/candidates/update.php">
      <input type="hidden" name="id" value="<?= $candidate['id'] ?>">

      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" required value="<?= htmlspecialchars($candidate['full_name']) ?>">
      </div>

      <div class="form-group">
        <label>Company</label>
        <select name="client_id">
          <option value="">No Company</option>
          <?php foreach ($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= ($candidate['client_id'] ?? null) === $cl['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cl['company_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Update Candidate</button>
        <a href="candidates.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




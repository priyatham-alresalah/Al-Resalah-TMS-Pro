<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('trainings', 'update');

$training_id = $_GET['id'] ?? '';
if (!$training_id) die('Missing training ID');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Fetch training (to get client_id)
$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$training_id&select=*",
    false,
    $ctx
  ),
  true
)[0];

// Fetch candidates of same client
$candidates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?client_id=eq.".$training['client_id']."&order=full_name.asc",
    false,
    $ctx
  ),
  true
);

// Already assigned candidates
$assigned = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_candidates?training_id=eq.$training_id&select=candidate_id",
    false,
    $ctx
  ),
  true
);

$assigned_ids = array_column($assigned, 'candidate_id');
?>

<!DOCTYPE html>
<html>
<head>
  <title>Assign Candidates</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Assign Candidates</h2>
  <p class="muted">Select candidates attending this training</p>

  <form method="post" action="../api/trainings/assign_candidates.php">
    <?= csrfField() ?>
    <input type="hidden" name="training_id" value="<?= $training_id ?>">

    <table class="table">
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($candidates as $c): ?>
        <tr>
          <td>
            <input type="checkbox"
                   name="candidates[]"
                   value="<?= $c['id'] ?>"
                   <?= in_array($c['id'], $assigned_ids) ? 'checked' : '' ?>>
          </td>
          <td><?= htmlspecialchars($c['full_name']) ?></td>
          <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>

    <br>
    <button>Save Assignments</button>
    <a href="trainings.php" class="link">Back</a>
  </form>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




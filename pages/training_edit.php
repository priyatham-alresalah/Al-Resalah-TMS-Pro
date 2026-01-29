<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('trainings', 'update');

$id = $_GET['id'] ?? '';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$training = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$id&select=*,clients(company_name),profiles(full_name)",
    false,
    $ctx
  ),
  true
)[0] ?? null;

$trainers = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?role=eq.trainer&is_active=eq.true&select=id,full_name",
    false,
    $ctx
  ),
  true
);

if (!$training) {
  die("Invalid training");
}

$locked = in_array($training['status'], ['completed','cancelled']);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Training</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Edit Training</h2>

  <div class="card" style="max-width:700px;">
    <form method="post" action="../api/trainings/update.php">
      <?= csrfField() ?>

      <input type="hidden" name="id" value="<?= $training['id'] ?>">

      <label>Client</label>
      <input value="<?= htmlspecialchars($training['clients']['company_name']) ?>" disabled>

      <label>Trainer</label>
      <select name="trainer_id" <?= $locked ? 'disabled' : '' ?>>
        <?php foreach ($trainers as $t): ?>
          <option value="<?= $t['id'] ?>"
            <?= $training['trainer_id']===$t['id']?'selected':'' ?>>
            <?= htmlspecialchars($t['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Training Date</label>
      <input type="date" name="training_date"
             value="<?= $training['training_date'] ?>"
             <?= $locked ? 'disabled' : '' ?>>

      <label>Status</label>
      <select name="status">
        <?php
          $statuses = ['scheduled','rescheduled','completed','cancelled'];
          foreach ($statuses as $s):
        ?>
          <option value="<?= $s ?>"
            <?= $training['status']===$s?'selected':'' ?>>
            <?= ucfirst($s) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <br><br>

      <?php if (!$locked): ?>
        <button type="submit">Update Training</button>
      <?php else: ?>
        <p style="color:#6b7280;">This training is locked.</p>
      <?php endif; ?>

      <a href="trainings.php" style="margin-left:10px;">Back</a>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




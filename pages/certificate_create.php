<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('certificates', 'create');

$training_id = $_GET['training_id'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Generate Certificate</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Generate Certificate</h2>

  <div class="form-card">
    <form method="post" action="../api/certificates/create.php">
      <?= csrfField() ?>
      <input type="hidden" name="training_id" value="<?= $training_id ?>">

      <div class="form-group">
        <label>Participant Name *</label>
        <input name="participant_name" required>
      </div>

      <div class="form-actions">
        <button type="submit">Generate Certificate</button>
        <a href="trainings.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$training_id = $_GET['training_id'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Generate Certificate</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Generate Certificate</h2>

  <div class="form-card">
    <form method="post" action="api/certificates/create.php">
      <input type="hidden" name="training_id" value="<?= $training_id ?>">

      <div class="form-group">
        <label>Participant Name *</label>
        <input name="participant_name" required>
      </div>

      <div class="form-actions">
        <button type="submit">Generate Certificate</button>
        <a href="trainings.php">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

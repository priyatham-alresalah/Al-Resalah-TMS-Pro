<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$training_id = $_GET['training_id'] ?? null;
if (!$training_id) die("Training ID missing");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $year = date('Y');

  // Generate certificate number
  $cert_no = "ARC-$year-" . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);

  // Call PDF generator
  header("Location: generate_pdf.php?training_id=$training_id&cert_no=$cert_no");
  exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Issue Certificate</title>
  <link rel="stylesheet" href="../assets/css/layout.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Issue Certificate</h2>
  <p class="muted">Generate training completion certificate</p>

  <div class="card" style="max-width:400px;">
    <form method="post">
      <button type="submit">Generate Certificate</button>
      <a class="btn-cancel" href="../trainings.php">Cancel</a>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>

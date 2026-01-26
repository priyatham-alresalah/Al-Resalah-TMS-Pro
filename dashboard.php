<?php
require 'includes/config.php';
require 'includes/auth_check.php';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard | Training Management System</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Dashboard</h2>
  <p>Welcome, <?= htmlspecialchars($_SESSION['user']['email']) ?></p>
</main>

<?php include 'layout/footer.php'; ?>

</body>
</html>

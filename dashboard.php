<?php
require 'includes/config.php';

/* =========================
   AUTH GUARD
========================= */
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_PATH . '/index.php');
  exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/training-management-system/favicon.ico">


  <!-- GLOBAL CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<!-- ================= CONTENT ================= -->
<main class="content">

  <h2>Dashboard</h2>
  <p class="muted">
    Welcome back, <strong><?= htmlspecialchars($user['name']) ?></strong>
    (<?= htmlspecialchars($user['role']) ?>)
  </p>

</main>

<?php include 'layout/footer.php'; ?>

</body>
</html>

<?php
/**
 * 404 Not Found - Custom error page
 */
require __DIR__ . '/includes/config.php';

http_response_code(404);

$loggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found | Al Resalah Consultancies & Training</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
</head>
<body>

<?php if ($loggedIn): ?>
  <?php include __DIR__ . '/layout/header.php'; ?>
  <?php include __DIR__ . '/layout/sidebar.php'; ?>
  <main class="content">
    <div class="page-header">
      <h2>404 Not Found</h2>
    </div>
    <div class="card" style="max-width: 560px;">
      <p style="font-size: 18px; color: #6b7280; margin-bottom: 16px;">The page you're looking for doesn't exist or has been moved.</p>
      <p style="margin-bottom: 20px;">Check the address or use the links below to get back on track.</p>
      <a href="<?= BASE_PATH ?>/pages/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
      <a href="<?= BASE_PATH ?>/pages/trainings.php" class="btn btn-secondary" style="margin-left: 10px;">Trainings</a>
    </div>
  </main>
  <?php include __DIR__ . '/layout/footer.php'; ?>
<?php else: ?>
  <header class="auth-header">
    <div class="header-inner">
      <img src="<?= BASE_PATH ?>/assets/images/logo.png" alt="Logo" style="height: 45px;">
      <h1 style="color: #ffffff; font-size: 20px;">Al Resalah Consultancies & Training</h1>
    </div>
  </header>
  <main class="content" style="max-width: 560px; margin: 60px auto; padding: 20px;">
    <div class="card">
      <h2 style="margin-bottom: 12px;">404 Not Found</h2>
      <p style="color: #6b7280; margin-bottom: 20px;">The page you're looking for doesn't exist or has been moved.</p>
      <a href="<?= BASE_PATH ?>/" class="btn btn-primary">Go to Login</a>
    </div>
  </main>
  <footer class="main-footer" style="text-align: center; padding: 20px;">
    © <?= date('Y') ?> Al Resalah Consultancies & Training
  </footer>
<?php endif; ?>

</body>
</html>

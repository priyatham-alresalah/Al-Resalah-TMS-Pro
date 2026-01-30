<?php
/**
 * 403 Forbidden - Custom error page
 */
require __DIR__ . '/includes/config.php';

http_response_code(403);

$loggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>403 Forbidden | Al Resalah Consultancies & Training</title>
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
      <h2>403 Forbidden</h2>
    </div>
    <div class="card" style="max-width: 560px;">
      <p style="font-size: 18px; color: #6b7280; margin-bottom: 16px;">You don't have permission to access this page.</p>
      <p style="margin-bottom: 20px;">If you believe this is an error, please contact your administrator.</p>
      <a href="<?= BASE_PATH ?>/pages/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
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
      <h2 style="margin-bottom: 12px;">403 Forbidden</h2>
      <p style="color: #6b7280; margin-bottom: 20px;">You don't have permission to access this resource.</p>
      <a href="<?= BASE_PATH ?>/" class="btn btn-primary">Go to Login</a>
    </div>
  </main>
  <footer class="main-footer" style="text-align: center; padding: 20px;">
    © <?= date('Y') ?> Al Resalah Consultancies & Training
  </footer>
<?php endif; ?>

</body>
</html>

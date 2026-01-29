<?php
require 'includes/config.php';

/* If already logged in, go to dashboard */
if (isset($_SESSION['user'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | AI Resalah Consultancies & Training</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

  <!-- SINGLE GLOBAL CSS -->
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="auth-header">
  <div class="header-inner">
    <img src="<?= BASE_PATH ?>/assets/images/logo.png" alt="AI Resalah">
    <h1>AI Resalah Consultancies & Training</h1>
  </div>
</header>

<!-- ================= LOGIN ================= -->
<div class="auth-container">
  <div class="auth-card">

    <h2>Login</h2>

    <?php if ($error === 'invalid'): ?>
      <p style="color:#dc2626;font-size:14px;margin-bottom:10px;">
        Invalid email or password
      </p>
    <?php endif; ?>

    <form method="post" action="<?= BASE_PATH ?>/api/auth/login.php">
      <input
        type="email"
        name="email"
        placeholder="Email"
        required
      >

      <input
        type="password"
        name="password"
        placeholder="Password"
        required
      >

      <button type="submit">Login</button>
    </form>

    <a href="#" onclick="showReset(); return false;">Forgot password?</a>

    <!-- RESET PASSWORD -->
    <div id="resetBox" style="display:none; margin-top:20px;">
      <form method="post" action="<?= BASE_PATH ?>/api/auth/reset.php">
        <input
          type="email"
          name="email"
          placeholder="Registered Email"
          required
        >
        <button type="submit">Send Reset Link</button>
      </form>
      <a href="#" onclick="hideReset(); return false;">Back to login</a>
    </div>

  </div>
</div>

<!-- ================= FOOTER ================= -->
<footer class="auth-footer">
  © <?= date('Y') ?> AI Resalah Consultancies & Training
</footer>

<script>
function showReset() {
  document.getElementById('resetBox').style.display = 'block';
}
function hideReset() {
  document.getElementById('resetBox').style.display = 'none';
}
</script>

</body>
</html>

<?php
require 'includes/config.php';

/* If already logged in, go to dashboard */
if (isset($_SESSION['user'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}

$error = $_GET['error'] ?? '';
$reset_sent = !empty($_GET['reset_sent']);
$reset_error = !empty($_GET['reset_error']);
if (!isset($_SESSION['reset_csrf'])) {
  $_SESSION['reset_csrf'] = bin2hex(random_bytes(16));
}
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
      <p class="alert alert-error">Invalid email or password</p>
    <?php endif; ?>
    <?php if ($error === 'inactive'): ?>
      <p class="alert alert-error">This account is disabled. Contact an administrator.</p>
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
    <div id="resetBox" class="reset-box" style="display:none; margin-top:20px;">
      <form method="post" action="<?= BASE_PATH ?>/api/auth/reset.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['reset_csrf']) ?>">
        <input
          type="email"
          name="email"
          placeholder="Registered Email"
          required
          autocomplete="email"
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
(function() {
  var hash = window.location.hash || '';
  if (hash.indexOf('type=recovery') !== -1 && hash.indexOf('access_token=') !== -1) {
    window.location.replace('<?= BASE_PATH ?>/set_password.php' + hash);
    return;
  }
})();
function showReset() {
  document.getElementById('resetBox').style.display = 'block';
}
function hideReset() {
  document.getElementById('resetBox').style.display = 'none';
}
</script>

</body>
</html>

<?php
/**
 * Set new password after clicking the reset link from Supabase email.
 * Supabase redirects here with #access_token=...&type=recovery in the URL (read by JS).
 * Add this page's full URL to Supabase Dashboard → Authentication → URL Configuration → Redirect URLs.
 */
require 'includes/config.php';

/* If already logged in, redirect to dashboard */
if (isset($_SESSION['user'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}

$password_updated = !empty($_GET['password_updated']);
$set_password_error = !empty($_GET['set_password_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set new password | <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
</head>
<body>

<header class="auth-header">
  <div class="header-inner">
    <img src="<?= BASE_PATH ?>/assets/images/logo.png" alt="AI Resalah">
    <h1><?= htmlspecialchars(APP_NAME) ?></h1>
  </div>
</header>

<div class="auth-container">
  <div class="auth-card">

    <h2>Set new password</h2>

    <?php if ($password_updated): ?>
      <p class="alert alert-success">Your password has been updated. You can now <a href="<?= BASE_PATH ?>/index.php">log in</a>.</p>
    <?php endif; ?>

    <?php if ($set_password_error): ?>
      <p class="alert alert-error">This link is invalid or has expired. Please <a href="<?= BASE_PATH ?>/index.php">request a new reset link</a>.</p>
    <?php endif; ?>

    <div id="invalidLink" class="alert alert-error" style="display:none;">
      This link is invalid or has expired. Please <a href="<?= BASE_PATH ?>/index.php">request a new reset link</a>.
    </div>

    <div id="setPasswordForm" style="display:none;">
      <form id="formSetPassword" method="post" action="<?= BASE_PATH ?>/api/auth/set_password.php">
        <input type="hidden" name="access_token" id="accessToken" value="">
        <input type="password" name="password" id="password" placeholder="New password" required minlength="6" autocomplete="new-password">
        <input type="password" name="password_confirm" id="passwordConfirm" placeholder="Confirm new password" required minlength="6" autocomplete="new-password">
        <p id="passwordMismatch" class="alert alert-error" style="display:none;">Passwords do not match.</p>
        <button type="submit">Update password</button>
      </form>
    </div>

    <p><a href="<?= BASE_PATH ?>/index.php">Back to login</a></p>
  </div>
</div>

<footer class="auth-footer">
  © <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>
</footer>

<script>
(function() {
  var hash = window.location.hash || '';
  var params = {};
  if (hash.indexOf('#') === 0) {
    hash.slice(1).split('&').forEach(function(pair) {
      var i = pair.indexOf('=');
      if (i !== -1) {
        params[decodeURIComponent(pair.slice(0, i))] = decodeURIComponent(pair.slice(i + 1).replace(/\+/g, ' '));
      }
    });
  }
  var accessToken = params.access_token || '';
  var type = params.type || '';

  if (type === 'recovery' && accessToken) {
    document.getElementById('accessToken').value = accessToken;
    document.getElementById('setPasswordForm').style.display = 'block';
  } else if (!window.location.search.match(/password_updated|set_password_error/)) {
    document.getElementById('invalidLink').style.display = 'block';
  }

  document.getElementById('formSetPassword').addEventListener('submit', function(e) {
    var pwd = document.getElementById('password').value;
    var conf = document.getElementById('passwordConfirm').value;
    var mismatch = document.getElementById('passwordMismatch');
    if (pwd !== conf) {
      e.preventDefault();
      mismatch.style.display = 'block';
      return false;
    }
    mismatch.style.display = 'none';
  });
})();
</script>
</body>
</html>

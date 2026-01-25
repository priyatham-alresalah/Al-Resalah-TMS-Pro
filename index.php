<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | Training Management System</title>

  <!-- CSS -->
  <link rel="stylesheet" href="/training-management-system/assets/css/auth.css">
</head>
<body>

<!-- HEADER -->
<header class="auth-header">
  <div class="header-inner">
    <img src="/training-management-system/assets/images/logo.png" alt="Logo">
    <h1>Al Resalah Consultancies & Training</h1>
  </div>
</header>

<!-- MAIN CONTENT -->
<main class="auth-container">

  <!-- LOGIN FORM -->
  <form method="post" action="/training-management-system/api/auth/login.php"
        class="auth-card" id="loginBox">

    <h2>Login</h2>

    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Login</button>

    <a href="#" onclick="showReset(); return false;">Forgot password?</a>
  </form>

  <!-- RESET PASSWORD FORM -->
  <form method="post" action="/training-management-system/api/auth/reset.php"
        class="auth-card hidden" id="resetBox">

    <h2>Reset Password</h2>

    <input type="email" name="email" placeholder="Registered Email" required>

    <button type="submit">Send Reset Link</button>

    <a href="#" onclick="showLogin(); return false;">Back to login</a>
  </form>

</main>

<!-- FOOTER -->
<footer class="auth-footer">
  © <?php echo date('Y'); ?> Al Resalah Consultancies & Training
</footer>

<!-- JS -->
<script>
function showReset() {
  document.getElementById('loginBox').classList.add('hidden');
  document.getElementById('resetBox').classList.remove('hidden');
}

function showLogin() {
  document.getElementById('resetBox').classList.add('hidden');
  document.getElementById('loginBox').classList.remove('hidden');
}
</script>

</body>
</html>

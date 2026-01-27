<header class="main-header">
  <div class="header-left">
    <img src="/training-management-system/assets/images/logo.png" alt="Logo">
    <span>Al Resalah Consultancies & Training</span>
  </div>

  <link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

  <div class="header-right">
    <span><?= htmlspecialchars($_SESSION['user']['email']) ?></span>
    <a href="/training-management-system/logout.php">Logout</a>
  </div>
</header>

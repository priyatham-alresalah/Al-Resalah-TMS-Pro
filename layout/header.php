<header class="main-header">
  <div class="header-left">
    <img src="/training-management-system/assets/images/logo.png" alt="Logo">
    <span>Al Resalah Consultancies & Training</span>
  </div>

  <div class="header-right">
    <span><?= htmlspecialchars($_SESSION['user']['email']) ?></span>
    <a href="/training-management-system/logout.php">Logout</a>
  </div>
</header>

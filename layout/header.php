<header class="main-header">
  <div class="header-left">
    <img src="<?= BASE_PATH ?>/assets/images/logo.png" alt="Logo">
    <span>Al Resalah Consultancies & Training</span>
  </div>

  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico" type="image/x-icon">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_PATH ?>/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_PATH ?>/favicon-16x16.png">

  <div class="header-right">
    <?php
    // Always use absolute app path so it's clickable from anywhere
    $profilePath = BASE_PATH . '/pages/profile.php';
    $displayName = trim((string)($_SESSION['user']['name'] ?? ''));
    if ($displayName === '') {
      $displayName = 'My Profile';
    }
    ?>
    <a href="<?= $profilePath ?>" style="color: #ffffff; text-decoration: none; cursor: pointer;">
      <?= htmlspecialchars($displayName) ?>
    </a>
    <a href="<?= BASE_PATH ?>/logout.php">Logout</a>
  </div>
</header>

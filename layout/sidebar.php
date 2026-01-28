<?php
$role = $_SESSION['user']['role'] ?? '';
$current = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];
$isInPages = strpos($currentPath, '/pages/') !== false;
$isInApi = strpos($currentPath, '/api/') !== false;

// Determine base path for links
if ($isInPages) {
  $basePath = ''; // Relative to pages folder
} elseif ($isInApi) {
  $basePath = '../../pages/'; // From api folder to pages
} else {
  $basePath = 'pages/'; // From root to pages
}
?>

<aside class="sidebar">
  <ul class="menu">

    <!-- DASHBOARD -->
    <li>
      <a href="<?= $basePath ?>dashboard.php" class="<?= ($current=='dashboard.php' && $isInPages)?'active':'' ?>">
        <span class="icon">🏠</span> Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Masters</li>

      <li>
        <a href="<?= $basePath ?>users.php" class="<?= ($current=='users.php' && $isInPages)?'active':'' ?>">
          <span class="icon">👤</span> Users
        </a>
      </li>

      <li>
        <a href="<?= $basePath ?>clients.php" class="<?= ($current=='clients.php' && $isInPages)?'active':'' ?>">
          <span class="icon">🏢</span> Clients
        </a>
      </li>

      <li>
        <a href="<?= $basePath ?>candidates.php" class="<?= ($current=='candidates.php' && $isInPages)?'active':'' ?>">
          <span class="icon">🧑‍🎓</span> Candidates
        </a>
      </li>
    <?php endif; ?>

    <!-- OPERATIONS -->
    <?php if (in_array($role, ['admin','accounts','bdm','bdo'])): ?>
      <li class="menu-title">Operations</li>

      <li>
        <a href="<?= $basePath ?>inquiries.php" class="<?= ($current=='inquiries.php' && $isInPages)?'active':'' ?>">
          <span class="icon">📩</span> Inquiries
        </a>
      </li>

      <li>
        <a href="<?= $basePath ?>trainings.php" class="<?= ($current=='trainings.php' && $isInPages)?'active':'' ?>">
          <span class="icon">🎓</span> Trainings
        </a>
      </li>
    <?php endif; ?>

    <!-- CERTIFICATES -->
    <?php if (in_array($role, ['admin','accounts','trainer','client'])): ?>
      <li class="menu-title">Certificates</li>

      <li>
        <a href="<?= $basePath ?>certificates.php" class="<?= ($current=='certificates.php' && $isInPages)?'active':'' ?>">
          <span class="icon">📜</span> Certificates
        </a>
      </li>
    <?php endif; ?>

    <!-- FINANCE -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Finance</li>

      <li>
        <a href="<?= $basePath ?>invoices.php" class="<?= ($current=='invoices.php' && $isInPages)?'active':'' ?>">
          <span class="icon">💰</span> Invoices
        </a>
      </li>
    <?php endif; ?>

    <!-- REPORTS -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Reports</li>

      <li>
        <a href="<?= $basePath ?>reports.php" class="<?= ($current=='reports.php' && $isInPages)?'active':'' ?>">
          <span class="icon">📊</span> Reports
        </a>
      </li>
    <?php endif; ?>

  </ul>
</aside>

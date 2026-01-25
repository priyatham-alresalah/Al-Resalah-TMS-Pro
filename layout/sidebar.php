<?php
$role = $_SESSION['user']['role'] ?? '';
$current = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
  <ul class="menu">

    <!-- DASHBOARD -->
    <li>
      <a href="dashboard.php" class="<?= $current=='dashboard.php'?'active':'' ?>">
        <span class="icon">🏠</span> Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Masters</li>

      <li>
        <a href="users.php" class="<?= $current=='users.php'?'active':'' ?>">
          <span class="icon">👤</span> Users
        </a>
      </li>

      <li>
        <a href="clients.php" class="<?= $current=='clients.php'?'active':'' ?>">
          <span class="icon">🏢</span> Clients
        </a>
      </li>

      <li>
        <a href="candidates.php" class="<?= $current=='candidates.php'?'active':'' ?>">
          <span class="icon">🧑‍🎓</span> Candidates
        </a>
      </li>
    <?php endif; ?>

    <!-- OPERATIONS -->
    <?php if (in_array($role, ['admin','accounts','bdm','bdo'])): ?>
      <li class="menu-title">Operations</li>

      <li>
        <a href="inquiries.php" class="<?= $current=='inquiries.php'?'active':'' ?>">
          <span class="icon">📩</span> Inquiries
        </a>
      </li>

      <li>
        <a href="trainings.php" class="<?= $current=='trainings.php'?'active':'' ?>">
          <span class="icon">🎓</span> Trainings
        </a>
      </li>
    <?php endif; ?>

    <!-- CERTIFICATES -->
    <?php if (in_array($role, ['admin','accounts','trainer','client'])): ?>
      <li class="menu-title">Certificates</li>

      <li>
        <a href="certificates.php" class="<?= $current=='certificates.php'?'active':'' ?>">
          <span class="icon">📜</span> Certificates
        </a>
      </li>
    <?php endif; ?>

    <!-- FINANCE -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Finance</li>

      <li>
        <a href="invoices.php" class="<?= $current=='invoices.php'?'active':'' ?>">
          <span class="icon">💰</span> Invoices
        </a>
      </li>
    <?php endif; ?>

    <!-- REPORTS -->
    <?php if (in_array($role, ['admin','accounts'])): ?>
      <li class="menu-title">Reports</li>

      <li>
        <a href="reports.php" class="<?= $current=='reports.php'?'active':'' ?>">
          <span class="icon">📊</span> Reports
        </a>
      </li>
    <?php endif; ?>

  </ul>
</aside>

<?php
/**
 * Shared header for client/candidate portals.
 * Uses the same CSS classes as admin header (`.main-header`).
 *
 * Expected session:
 * - client_portal: $_SESSION['client'] with company_name, email
 * - candidate_portal: $_SESSION['candidate'] with full_name, email
 *
 * Optional:
 * - $portalNavActive: 'dashboard' | 'inquiry' | 'quotes' | 'profile'
 */
$portalNavActive = $portalNavActive ?? '';

$isClientPortal = isset($_SESSION['client']);
$isCandidatePortal = isset($_SESSION['candidate']);

$displayName = 'Portal';
$displayEmail = '';
$profileUrl = 'profile.php';
$logoutUrl = 'logout.php';

$nav = [];
if ($isClientPortal) {
  $displayName = $_SESSION['client']['company_name'] ?? 'Client Portal';
  $displayEmail = $_SESSION['client']['email'] ?? '';
  $nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'inquiry', 'label' => 'New Inquiry', 'href' => 'inquiry.php'],
    ['key' => 'quotes', 'label' => 'Quotes', 'href' => 'quotes.php'],
  ];
} elseif ($isCandidatePortal) {
  $displayName = $_SESSION['candidate']['full_name'] ?? 'Candidate Portal';
  $displayEmail = $_SESSION['candidate']['email'] ?? '';
  $nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'inquiry', 'label' => 'New Inquiry', 'href' => 'inquiry.php'],
  ];
}
?>

<header class="main-header">
  <div class="header-left">
    <img src="/training-management-system/assets/images/logo.png" alt="Logo">
    <span><?= htmlspecialchars(APP_NAME) ?></span>
  </div>

  <div class="header-right">
    <?php foreach ($nav as $item): ?>
      <a
        href="<?= htmlspecialchars($item['href']) ?>"
        style="<?= $portalNavActive === $item['key'] ? 'text-decoration: underline; font-weight: 700;' : '' ?>"
      >
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>

    <a
      href="<?= htmlspecialchars($profileUrl) ?>"
      style="<?= $portalNavActive === 'profile' ? 'text-decoration: underline; font-weight: 700;' : '' ?>"
      title="Profile"
    >
      <?= htmlspecialchars($displayName) ?>
    </a>

    <a href="<?= htmlspecialchars($logoutUrl) ?>">Logout</a>
  </div>
</header>

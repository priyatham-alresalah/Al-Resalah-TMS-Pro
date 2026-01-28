<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'];

/* Build Supabase URL safely */
$baseUrl = SUPABASE_URL . "/rest/v1/clients?select=*";

/* Ownership rule */
if ($role !== 'admin') {
  $baseUrl .= "&created_by=eq.$userId";
}

/* Order */
$baseUrl .= "&order=created_at.desc";

/* Context */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = @file_get_contents($baseUrl, false, $ctx);

/* Safety check */
if ($response === false) {
  error_log("Failed to fetch clients from Supabase");
  $clients = [];
} else {
  $clients = json_decode($response, true) ?: [];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Clients</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Clients</h2>
      <p class="muted">Manage your clients</p>
    </div>
    <a href="client_create.php" class="btn">+ Create Client</a>
  </div>

  <?php if (isset($_GET['success'])): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th>Company</th>
        <th>Contact</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Created</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($clients)): ?>
      <?php foreach ($clients as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['company_name']) ?></td>
          <td><?= htmlspecialchars($c['contact_person'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
          <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
                &#8942;
              </button>
              <div class="action-menu">
                <a href="client_edit.php?id=<?= $c['id'] ?>">Edit</a>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="6">No clients found</td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>

<script>
  document.addEventListener('click', function (event) {
    const isToggle = event.target.closest('.action-menu-toggle');
    const wrappers = document.querySelectorAll('.action-menu-wrapper');

    wrappers.forEach(function (wrapper) {
      const menu = wrapper.querySelector('.action-menu');
      if (!menu) return;

      if (isToggle && wrapper.contains(isToggle)) {
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.action-menu.open').forEach(function (openMenu) {
          openMenu.classList.remove('open');
        });
        if (!isOpen) {
          menu.classList.add('open');
        }
      } else {
        menu.classList.remove('open');
      }
    });
  });
</script>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




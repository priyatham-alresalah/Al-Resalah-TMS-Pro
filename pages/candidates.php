<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('candidates', 'view');

$role = $_SESSION['user']['role'];

/* SUPABASE CONTEXT */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* =========================
   FETCH CLIENTS
========================= */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?order=company_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];

$clientMap = [];
foreach ($clients as $cl) {
  $clientMap[$cl['id']] = $cl;
}

/* =========================
   FETCH CANDIDATES
========================= */
$candidatesResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates?order=created_at.desc",
  false,
  $ctx
);

if ($candidatesResponse === false) {
  error_log("Failed to fetch candidates from Supabase");
  $candidates = [];
} else {
  $candidates = json_decode($candidatesResponse, true) ?: [];
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Candidates</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Candidates</h2>
      <p class="muted">Master list of training participants</p>
    </div>
    <a href="candidate_create.php" class="btn">+ Create Candidate</a>
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
        <th>Name</th>
        <th>Company</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Created</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($candidates)): ?>
      <?php foreach ($candidates as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['full_name']) ?></td>
          <td>
            <?= htmlspecialchars(
              $clientMap[$c['client_id']]['company_name'] ?? '-'
            ) ?>
          </td>
          <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
          <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
                &#8942;
              </button>
              <div class="action-menu">
                <a href="candidate_edit.php?id=<?= $c['id'] ?>">Edit</a>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="6">No candidates found</td>
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




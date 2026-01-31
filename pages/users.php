<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/csrf.php';

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Current tab */
$tab = $_GET['tab'] ?? 'staff';
if (!in_array($tab, ['staff', 'clients', 'candidates'])) {
  $tab = 'staff';
}

/* Fetch Staff (profiles) */
$users = [];
$usersResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,is_active,created_at&order=created_at.desc&limit=1000",
  false,
  $ctx
);
if ($usersResponse !== false) {
  $decoded = json_decode($usersResponse, true);
  $users = is_array($decoded) ? $decoded : [];
}

/* Fetch Clients (login_enabled from sql/add_login_enabled.sql) */
$clients = [];
$clientsError = null;
$clientsResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?select=id,company_name,contact_person,email,login_enabled,created_at&order=company_name.asc&limit=1000",
  false,
  $ctx
);
if ($clientsResponse !== false) {
  $decoded = json_decode($clientsResponse, true);
  if (is_array($decoded) && !isset($decoded['message'])) {
    $clients = $decoded;
  } elseif (is_array($decoded) && isset($decoded['message'])) {
    $clientsError = $decoded['message'];
  }
}

/* Fetch Candidates */
$candidates = [];
$candidatesError = null;
$candidatesResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates?select=id,full_name,email,login_enabled,created_at&order=full_name.asc&limit=1000",
  false,
  $ctx
);
if ($candidatesResponse !== false) {
  $decoded = json_decode($candidatesResponse, true);
  if (is_array($decoded) && !isset($decoded['message'])) {
    $candidates = $decoded;
  } elseif (is_array($decoded) && isset($decoded['message'])) {
    $candidatesError = $decoded['message'];
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Users</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <div>
      <h2>Users</h2>
      <p class="muted">Manage staff, client and candidate logins</p>
    </div>
    <div style="display:flex;gap:10px;">
      <?php if ($tab === 'staff'): ?>
        <a href="<?= BASE_PATH ?>/api/users/sync_profiles.php" class="btn" style="background:#6366f1;" onclick="return confirm('Sync all users from Supabase Auth to profiles. Continue?')">&#8635; Sync Users</a>
        <a href="user_create.php" class="btn">+ Create User</a>
      <?php elseif ($tab === 'clients'): ?>
        <a href="clients.php" class="btn">View Clients</a>
      <?php else: ?>
        <a href="candidates.php" class="btn">View Candidates</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <div class="tabs" style="margin-bottom:20px;border-bottom:1px solid #e5e7eb;">
    <a href="?tab=staff" style="padding:10px 16px;text-decoration:none;<?= $tab === 'staff' ? 'color:#111;font-weight:600;border-bottom:2px solid #3b82f6;margin-bottom:-1px;' : 'color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;' ?>">Staff</a>
    <a href="?tab=clients" style="padding:10px 16px;text-decoration:none;<?= $tab === 'clients' ? 'color:#111;font-weight:600;border-bottom:2px solid #3b82f6;margin-bottom:-1px;' : 'color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;' ?>">Clients</a>
    <a href="?tab=candidates" style="padding:10px 16px;text-decoration:none;<?= $tab === 'candidates' ? 'color:#111;font-weight:600;border-bottom:2px solid #3b82f6;margin-bottom:-1px;' : 'color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;' ?>">Candidates</a>
  </div>

  <?php if ($tab === 'staff'): ?>
    <table class="table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($users)): foreach ($users as $u):
        if (empty($u['id'])) continue;
        $fullName = $u['full_name'] ?? '-';
        $email = $u['email'] ?? '-';
        $role = $u['role'] ?? 'user';
        $isActive = isset($u['is_active']) ? (bool)$u['is_active'] : true;
        $createdAt = $u['created_at'] ?? null;
        $userId = $u['id'];
      ?>
        <tr>
          <td><?= htmlspecialchars($fullName) ?></td>
          <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($email) ?></td>
          <td><?= ucfirst($role) ?></td>
          <td><?= $isActive ? '<span class="badge-success">Active</span>' : '<span class="badge-danger">Inactive</span>' ?></td>
          <td><?= $createdAt ? date('d M Y', strtotime($createdAt)) : '-' ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
              <div class="action-menu">
                <a href="user_edit.php?id=<?= htmlspecialchars($userId) ?>">Edit</a>
                <form action="<?= BASE_PATH ?>/api/users/toggle_status.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                  <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                  <button type="submit" class="danger"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form action="<?= BASE_PATH ?>/api/users/reset_password.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                  <button type="submit" class="danger">Reset Password</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6">No staff users found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

  <?php elseif ($tab === 'clients'): ?>
    <p class="muted" style="margin-bottom:15px;">Control which clients can log into the Client Portal. Disabled clients cannot log in.</p>
    <table class="table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Login</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($clients)): foreach ($clients as $c):
        $loginEnabled = !isset($c['login_enabled']) || $c['login_enabled'] === true || $c['login_enabled'] === 't';
        $email = $c['email'] ?? '';
      ?>
        <tr>
          <td><?= htmlspecialchars($c['company_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['contact_person'] ?? '-') ?></td>
          <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($email ?: '-') ?></td>
          <td><?= $loginEnabled ? '<span class="badge-success">Enabled</span>' : '<span class="badge-danger">Disabled</span>' ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
              <div class="action-menu">
                <a href="client_edit.php?id=<?= htmlspecialchars($c['id']) ?>">Edit Client</a>
                <form action="<?= BASE_PATH ?>/api/users/toggle_portal_login.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="type" value="client">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($c['id']) ?>">
                  <input type="hidden" name="enabled" value="<?= $loginEnabled ? 0 : 1 ?>">
                  <button type="submit" class="danger"><?= $loginEnabled ? 'Disable Login' : 'Enable Login' ?></button>
                </form>
                <?php if ($email): ?>
                <form action="<?= BASE_PATH ?>/api/users/send_reset_portal.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="type" value="client">
                  <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                  <button type="submit">Send Reset Link</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5">No clients found. <a href="clients.php">Add clients</a> first.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

  <?php else: ?>
    <?php if ($candidatesError): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($candidatesError) ?> — Run <code>sql/add_login_enabled.sql</code> in Supabase SQL Editor to enable login control.</div>
    <?php endif; ?>
    <p class="muted" style="margin-bottom:15px;">Control which candidates can log into the Candidate Portal. Disabled candidates cannot log in.</p>
    <table class="table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Email</th>
          <th>Login</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($candidates)): foreach ($candidates as $c):
        $loginEnabled = !isset($c['login_enabled']) || $c['login_enabled'] === true || $c['login_enabled'] === 't';
        $email = $c['email'] ?? '';
      ?>
        <tr>
          <td><?= htmlspecialchars($c['full_name'] ?? '-') ?></td>
          <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($email ?: '-') ?></td>
          <td><?= $loginEnabled ? '<span class="badge-success">Enabled</span>' : '<span class="badge-danger">Disabled</span>' ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
              <div class="action-menu">
                <a href="candidate_edit.php?id=<?= htmlspecialchars($c['id']) ?>">Edit Candidate</a>
                <form action="<?= BASE_PATH ?>/api/users/toggle_portal_login.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="type" value="candidate">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($c['id']) ?>">
                  <input type="hidden" name="enabled" value="<?= $loginEnabled ? 0 : 1 ?>">
                  <button type="submit" class="danger"><?= $loginEnabled ? 'Disable Login' : 'Enable Login' ?></button>
                </form>
                <?php if ($email): ?>
                <form action="<?= BASE_PATH ?>/api/users/send_reset_portal.php" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="type" value="candidate">
                  <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                  <button type="submit">Send Reset Link</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="4">No candidates found. <a href="candidates.php">Add candidates</a> first.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

<script>
document.addEventListener('click', function (e) {
  var t = e.target.closest('.action-menu-toggle');
  document.querySelectorAll('.action-menu').forEach(function (m) {
    m.classList.remove('open');
  });
  if (t) {
    var w = t.closest('.action-menu-wrapper');
    if (w) {
      var menu = w.querySelector('.action-menu');
      if (menu) menu.classList.add('open');
    }
  }
});
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>

<?php
require 'includes/config.php';
require 'includes/auth_check.php';

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

$response = file_get_contents($baseUrl, false, $ctx);

/* Safety check */
$clients = $response ? json_decode($response, true) : [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Clients</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Clients</h2>
      <p class="muted">Manage your clients</p>
    </div>
    <a href="client_create.php" class="btn">+ Create Client</a>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Company</th>
        <th>Contact</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Created</th>
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
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="5">No clients found</td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

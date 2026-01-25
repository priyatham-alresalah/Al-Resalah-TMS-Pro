<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$role = $_SESSION['user']['role'];

/* Fetch clients */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?order=created_at.desc",
  false,
  $ctx
);

$clients = json_decode($response, true);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Clients</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Clients</h2>

  <!-- CREATE CLIENT -->
  <?php if (in_array($role, ['admin','bdm','bdo'])): ?>
    <section style="margin-bottom:25px;">
      <h3>Add Client</h3>

      <form method="post" action="api/clients/create.php" class="form-inline">
        <input name="company_name" placeholder="Company Name *" required>
        <input name="contact_person" placeholder="Contact Person">
        <input name="email" placeholder="Email">
        <input name="phone" placeholder="Phone">
        <input name="address" placeholder="Address">
        <button type="submit">Add</button>
      </form>
    </section>
  <?php endif; ?>

  <!-- CLIENT LIST -->
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
        <tr><td colspan="5">No clients found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

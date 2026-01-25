<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH CLIENTS */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?order=company_name.asc",
    false,
    $ctx
  ),
  true
);

$clientMap = [];
foreach ($clients as $cl) {
  $clientMap[$cl['id']] = $cl;
}

/* ADD CANDIDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $payload = json_encode([
    'client_id' => $_POST['client_id'],
    'full_name' => $_POST['full_name'],
    'email'     => $_POST['email'] ?: null,
    'phone'     => $_POST['phone'] ?: null
  ]);

  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates",
    false,
    stream_context_create([
      'http' => [
        'method'  => 'POST',
        'header'  =>
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
          "Content-Type: application/json",
        'content' => $payload
      ]
    ])
  );

  header("Location: candidates.php");
  exit;
}

/* FETCH CANDIDATES */
$candidates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?order=created_at.desc",
    false,
    $ctx
  ),
  true
);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Candidates</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Candidates</h2>
  <p class="muted">Training participants</p>

  <!-- ADD FORM -->
  <form method="post" class="form-inline" style="margin-bottom:20px;">
    <select name="client_id" required>
      <option value="">Select Client *</option>
      <?php foreach ($clients as $cl): ?>
        <option value="<?= $cl['id'] ?>">
          <?= htmlspecialchars($cl['company_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input name="full_name" placeholder="Full Name *" required>
    <input name="email" placeholder="Email">
    <input name="phone" placeholder="Phone">

    <button type="submit">Add Candidate</button>
  </form>

  <!-- LIST -->
  <table class="table">
    <thead>
      <tr>
        <th>Candidate</th>
        <th>Company</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Created</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($candidates)): foreach ($candidates as $c): ?>
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
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="5">No candidates found</td></tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

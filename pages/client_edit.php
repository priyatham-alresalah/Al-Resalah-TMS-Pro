<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$id = $_GET['id'] ?? '';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/clients?id=eq." . $id,
  false,
  $ctx
);

$client = json_decode($response, true)[0] ?? null;

if (!$client) {
  die('Client not found');
}

/* Only admin or creator can access edit page */
$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'];

if ($role !== 'admin' && ($client['created_by'] ?? null) !== $userId) {
  die('Access denied');
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Client</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Edit Client</h2>
  <p class="muted">Update client company and contact details</p>

  <div class="form-card">
    <form method="post" action="../api/clients/update.php">
      <input type="hidden" name="id" value="<?= $client['id'] ?>">

      <div class="form-group">
        <label>Company Name *</label>
        <input name="company_name" required
               value="<?= htmlspecialchars($client['company_name']) ?>">
      </div>

      <div class="form-group">
        <label>Contact Person</label>
        <input name="contact_person"
               value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input name="email"
               value="<?= htmlspecialchars($client['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input name="phone"
               value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Address</label>
        <input name="address"
               value="<?= htmlspecialchars($client['address'] ?? '') ?>">
      </div>

      <div class="form-actions">
        <button type="submit">Update Client</button>
        <a href="clients.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




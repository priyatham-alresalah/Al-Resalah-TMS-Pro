<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (!$id) die('Missing candidate ID');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$candidate = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/candidates?id=eq.$id&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$candidate) die('Candidate not found');
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Candidate</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Edit Candidate</h2>
  <p class="muted">Update candidate details</p>

  <form method="post" action="api/candidates/update.php" class="card-form">
    <input type="hidden" name="id" value="<?= $candidate['id'] ?>">

    <label>Full Name *</label>
    <input name="full_name" required value="<?= htmlspecialchars($candidate['full_name']) ?>">

    <label>Email</label>
    <input name="email" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>">

    <label>Phone</label>
    <input name="phone" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">

    <br><br>
    <button>Update Candidate</button>
    <a href="candidates.php" class="link">Cancel</a>
  </form>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

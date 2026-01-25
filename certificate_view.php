<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$id = $_GET['id'] ?? '';

if (!$id) {
  die('Invalid certificate');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$url = SUPABASE_URL . "/rest/v1/certificates?id=eq.$id&select=*";
$response = file_get_contents($url, false, $ctx);
$data = json_decode($response, true);

if (empty($data)) {
  die('Certificate not found');
}

$c = $data[0];
?>

<!DOCTYPE html>
<html>
<head>
  <title>View Certificate</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Certificate Details</h2>

  <div class="card">
    <p><strong>Certificate No:</strong> <?= htmlspecialchars($c['certificate_no']) ?></p>
    <p><strong>Issued Date:</strong> <?= date('d M Y', strtotime($c['issued_date'])) ?></p>

    <?php if (!empty($c['file_path'])): ?>
      <p>
        <a href="<?= htmlspecialchars($c['file_path']) ?>" target="_blank">
          Download Certificate
        </a>
      </p>
    <?php endif; ?>
  </div>

  <a href="certificates.php">← Back to Certificates</a>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

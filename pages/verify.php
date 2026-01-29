<!DOCTYPE html>
<html>
<head>
  <title>Verify Certificate</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<main class="content" style="max-width:600px;margin:auto;">
  <h2>Certificate Verification</h2>
  <p class="muted">Enter certificate number to verify authenticity</p>

  <form method="get">
    <input type="text" name="certificate_no"
           placeholder="Certificate Number"
           required
           style="width:100%;padding:10px;margin-bottom:10px;">
    <button class="btn">Verify</button>
  </form>

<?php if (!empty($_GET['certificate_no'])): ?>
  <hr>

<?php
$cert_no = urlencode($_GET['certificate_no']);
$response = file_get_contents(
  "api/certificates/verify.php?certificate_no=$cert_no"
);
$data = json_decode($response, true);
?>

<?php if (!$data || !$data['valid']): ?>
  <p class="badge badge-danger">❌ Certificate NOT FOUND</p>
<?php else: ?>
  <div class="card">
    <p><strong>Status:</strong> ✅ VALID</p>
    <p><strong>Certificate No:</strong> <?= $data['certificate_no'] ?></p>
    <p><strong>Candidate:</strong> <?= $data['candidate'] ?></p>
    <p><strong>Course:</strong> <?= $data['course'] ?></p>
    <p><strong>Company:</strong> <?= $data['company'] ?></p>
    <p><strong>Issued Date:</strong> <?= date('d M Y', strtotime($data['issued_date'])) ?></p>
  </div>
<?php endif; ?>
<?php endif; ?>

</main>
</body>
</html>




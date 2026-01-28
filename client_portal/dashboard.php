<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['client'])) {
  header('Location: login.php');
  exit;
}

$client = $_SESSION['client'];
$clientId = $client['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch invoices */
$invoices = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?client_id=eq.$clientId&order=issued_date.desc",
    false,
    $ctx
  ),
  true
) ?: [];

/* Fetch certificates (through trainings) */
$trainings = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?client_id=eq.$clientId&select=id",
    false,
    $ctx
  ),
  true
) ?: [];

$certificates = [];
if (!empty($trainings)) {
  $trainingIds = array_column($trainings, 'id');
  $trainingIdsStr = implode(',', array_map(function($id) { return "eq.$id"; }, $trainingIds));
  
  $certificates = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/certificates?select=*",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  /* Filter certificates by training_id */
  $certificates = array_filter($certificates, function($cert) use ($trainingIds) {
    return in_array($cert['training_id'], $trainingIds);
  });
  
  /* Fetch candidate names */
  $candidateIds = array_unique(array_column($certificates, 'candidate_id'));
  $candidates = [];
  if (!empty($candidateIds)) {
    $candidatesData = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/candidates?select=id,full_name",
        false,
        $ctx
      ),
      true
    ) ?: [];
    foreach ($candidatesData as $c) {
      if (in_array($c['id'], $candidateIds)) {
        $candidates[$c['id']] = $c;
      }
    }
  }
  
  /* Fetch training details */
  $trainingsData = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings?select=id,course_name,training_date",
      false,
      $ctx
    ),
    true
  ) ?: [];
  $trainingsMap = [];
  foreach ($trainingsData as $t) {
    if (in_array($t['id'], $trainingIds)) {
      $trainingsMap[$t['id']] = $t;
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Client Portal - Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <?php $portalNavActive = 'dashboard'; include '../layout/portal_header.php'; ?>

  <main class="content" style="margin-left: 0; margin-top: 0; padding: 25px;">
    <h2>Invoices</h2>
    <p class="muted">View and download your invoices</p>

    <?php if (!empty($invoices)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Amount</th>
            <th>VAT</th>
            <th>Total</th>
            <th>Status</th>
            <th>Issued Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
            <tr>
              <td><?= htmlspecialchars($inv['invoice_no'] ?? '-') ?></td>
              <td><?= number_format($inv['amount'] ?? 0, 2) ?></td>
              <td><?= number_format($inv['vat'] ?? 0, 2) ?></td>
              <td><strong><?= number_format($inv['total'] ?? 0, 2) ?></strong></td>
              <td>
                <span class="badge badge-<?= strtolower($inv['status'] ?? 'unpaid') ?>">
                  <?= strtoupper($inv['status'] ?? 'UNPAID') ?>
                </span>
              </td>
              <td><?= $inv['issued_date'] ? date('d M Y', strtotime($inv['issued_date'])) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No invoices found</p>
    <?php endif; ?>

    <h2 style="margin-top: 40px;">Company Certificates</h2>
    <p class="muted">Certificates issued to your company's candidates</p>

    <?php if (!empty($certificates)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Certificate No</th>
            <th>Candidate</th>
            <th>Course</th>
            <th>Training Date</th>
            <th>Issued Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($certificates as $cert): ?>
            <?php 
              $candidate = $candidates[$cert['candidate_id']] ?? null;
              $training = $trainingsMap[$cert['training_id']] ?? null;
            ?>
            <tr>
              <td><?= htmlspecialchars($cert['certificate_no']) ?></td>
              <td><?= htmlspecialchars($candidate['full_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars($training['course_name'] ?? '-') ?></td>
              <td><?= $training && $training['training_date'] ? date('d M Y', strtotime($training['training_date'])) : '-' ?></td>
              <td><?= date('d M Y', strtotime($cert['issued_date'])) ?></td>
              <td>
                <?php if (($cert['status'] ?? 'active') === 'active'): ?>
                  <span class="badge-success">Active</span>
                <?php else: ?>
                  <span class="badge-danger">Revoked</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No certificates found</p>
    <?php endif; ?>
  </main>
<?php include '../layout/footer.php'; ?>
</body>
</html>

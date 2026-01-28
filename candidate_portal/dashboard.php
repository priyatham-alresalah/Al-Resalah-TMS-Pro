<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['candidate'])) {
  header('Location: login.php');
  exit;
}

$candidate = $_SESSION['candidate'];
$candidateId = $candidate['id'];
$clientId = $candidate['client_id'] ?? null;

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch certificates */
$certificates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?candidate_id=eq.$candidateId&order=issued_date.desc",
    false,
    $ctx
  ),
  true
) ?: [];

/* Fetch trainings for certificate details */
$trainingIds = array_unique(array_column($certificates, 'training_id'));
$trainings = [];
if (!empty($trainingIds)) {
  $trainingIdsStr = implode(',', array_map(function($id) { return "eq.$id"; }, $trainingIds));
  $trainingsData = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings?select=id,course_name,training_date,client_id",
      false,
      $ctx
    ),
    true
  ) ?: [];
  foreach ($trainingsData as $t) {
    if (in_array($t['id'], $trainingIds)) {
      $trainings[$t['id']] = $t;
    }
  }
}

/* Fetch invoices (if candidate has client_id) */
$invoices = [];
if ($clientId) {
  $invoices = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?client_id=eq.$clientId&order=issued_date.desc",
      false,
      $ctx
    ),
    true
  ) ?: [];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Candidate Portal - Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <?php $portalNavActive = 'dashboard'; include '../layout/portal_header.php'; ?>

  <main class="content" style="margin-left: 0; margin-top: 0; padding: 25px;">
    <h2>My Certificates</h2>
    <p class="muted">Download your training certificates</p>

    <?php if (!empty($certificates)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Certificate No</th>
            <th>Course</th>
            <th>Training Date</th>
            <th>Issued Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($certificates as $cert): ?>
            <?php $training = $trainings[$cert['training_id']] ?? null; ?>
            <tr>
              <td><?= htmlspecialchars($cert['certificate_no']) ?></td>
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
              <td>
                <?php if (($cert['status'] ?? 'active') === 'active' && !empty($cert['file_path'])): ?>
                  <a href="../api/certificates/download.php?file=<?= urlencode($cert['file_path']) ?>" class="btn btn-sm">Download</a>
                <?php else: ?>
                  <span style="color: #6b7280;">Not available</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No certificates found</p>
    <?php endif; ?>

    <?php if ($clientId): ?>
      <h2 style="margin-top: 40px;">Company Invoices</h2>
      <p class="muted">Invoices for your company</p>

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
    <?php endif; ?>
  </main>
<?php include '../layout/footer.php'; ?>
</body>
</html>

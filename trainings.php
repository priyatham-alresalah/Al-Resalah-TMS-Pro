<?php
require 'includes/config.php';
require 'includes/auth_check.php';

/* SUPABASE CONTEXT */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH TRAININGS */
$trainings = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?order=training_date.desc",
    false,
    $ctx
  ),
  true
);

/* FETCH INQUIRIES */
$inquiries = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries",
    false,
    $ctx
  ),
  true
);

$inqMap = [];
foreach ($inquiries ?? [] as $i) {
  $inqMap[$i['id']] = $i;
}

/* FETCH CLIENTS */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients",
    false,
    $ctx
  ),
  true
);

$clientMap = [];
foreach ($clients ?? [] as $c) {
  $clientMap[$c['id']] = $c;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Trainings</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Trainings</h2>
  <p class="muted">Manage trainings, candidates, certificates & invoices</p>

  <table class="table">
    <thead>
      <tr>
        <th>Client</th>
        <th>Course</th>
        <th>Date</th>
        <th>Status</th>
        <th width="260">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($trainings)): ?>
      <?php foreach ($trainings as $t): ?>
        <?php
          $inq = $inqMap[$t['inquiry_id']] ?? [];
          $client = $clientMap[$t['client_id']] ?? [];
        ?>
        <tr>
          <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($inq['course_name'] ?? '-') ?></td>
          <td><?= date('d M Y', strtotime($t['training_date'])) ?></td>
          <td>
            <span class="badge badge-<?= strtolower($t['status']) ?>">
              <?= strtoupper($t['status']) ?>
            </span>
          </td>
          <td class="actions">

            <!-- Candidates -->
            <a class="btn small"
               href="training_candidates.php?training_id=<?= $t['id'] ?>">
               Candidates
            </a>

            <!-- Issue Certificates -->
            <?php if ($t['status'] === 'completed'): ?>
              <a class="btn small btn-success"
                 href="issue_certificates.php?training_id=<?= $t['id'] ?>">
                 Issue Certificates
              </a>
            <?php endif; ?>

          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="5">No trainings found</td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

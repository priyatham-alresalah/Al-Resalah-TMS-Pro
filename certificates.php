<?php
require 'includes/config.php';
require 'includes/auth_check.php';

/* ===============================
   SUPABASE CONTEXT
=============================== */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* ===============================
   FETCH DATA
=============================== */
$certificates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?order=issued_date.desc",
    false,
    $ctx
  ),
  true
);

$candidates = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/candidates", false, $ctx),
  true
);

$trainings = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/trainings", false, $ctx),
  true
);

$clients = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/clients", false, $ctx),
  true
);

/* ===============================
   MAPS
=============================== */
$candidateMap = [];
foreach ($candidates as $c) $candidateMap[$c['id']] = $c;

$trainingMap = [];
foreach ($trainings as $t) $trainingMap[$t['id']] = $t;

$clientMap = [];
foreach ($clients as $c) $clientMap[$c['id']] = $c;
?>

<!DOCTYPE html>
<html>
<head>
  <title>Certificates</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Certificates</h2>
  <p class="muted">Issued certificates (candidate-wise)</p>

  <table class="table">
    <thead>
      <tr>
        <th>Certificate No</th>
        <th>Candidate</th>
        <th>Client</th>
        <th>Training Date</th>
        <th>Issued Date</th>
        <th>Status</th>
        <th width="260">Action</th>
      </tr>
    </thead>
    <tbody>

    <?php if ($certificates): foreach ($certificates as $c): ?>
      <?php
        $candidate = $candidateMap[$c['candidate_id']] ?? null;
        $training  = $trainingMap[$c['training_id']] ?? null;
        $client    = $training
                      ? ($clientMap[$training['client_id']] ?? null)
                      : null;
        $status = $c['status'] ?? 'active';
      ?>
      <tr>
        <td><?= htmlspecialchars($c['certificate_no']) ?></td>
        <td><?= htmlspecialchars($candidate['full_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
        <td><?= $training ? date('d M Y', strtotime($training['training_date'])) : '-' ?></td>
        <td><?= date('d M Y', strtotime($c['issued_date'])) ?></td>
        <td>
          <?= $status === 'active'
              ? '<span class="text-success">Active</span>'
              : '<span class="text-danger">Revoked</span>' ?>
        </td>
        <td>

          <?php if ($status === 'active'): ?>
            <a class="btn small"
               href="certificates/download.php?file=<?= urlencode($c['file_path']) ?>">
               Download
            </a>

            <form method="post"
                  action="api/certificates/send_email.php"
                  style="display:inline">
              <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
              <button class="btn small">Email</button>
            </form>

            <form method="post"
                  action="api/certificates/revoke.php"
                  onsubmit="return confirm('Revoke this certificate?')"
                  style="display:inline">
              <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
              <button class="btn small btn-danger">Revoke</button>
            </form>
          <?php else: ?>
            —
          <?php endif; ?>

        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">No certificates found</td></tr>
    <?php endif; ?>

    </tbody>
  </table>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

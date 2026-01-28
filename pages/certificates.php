<?php
require '../includes/config.php';
require '../includes/auth_check.php';

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
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

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
        <th style="width: 60px;">Actions</th>
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
        <td class="col-actions">
          <div class="action-menu-wrapper">
            <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
              &#8942;
            </button>
            <div class="action-menu">
              <a href="certificate_edit.php?id=<?= $c['id'] ?>">Edit</a>
              <a href="../api/certificates/print_pdf.php?id=<?= $c['id'] ?>" target="_blank">Print PDF</a>
              <form method="post" action="../api/certificates/send_email.php" style="margin: 0;">
                <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
                <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #374151;">Send Mail</button>
              </form>
              <a href="../api/certificates/download.php?id=<?= $c['id'] ?>">Download</a>
              <?php if ($status === 'active'): ?>
                <div class="danger">
                  <form method="post" action="../api/certificates/revoke.php" onsubmit="return confirm('Revoke this certificate?')" style="margin: 0;">
                    <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #dc2626;">Revoke</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">No certificates found</td></tr>
    <?php endif; ?>

    </tbody>
  </table>

<script>
  document.addEventListener('click', function (event) {
    const isToggle = event.target.closest('.action-menu-toggle');
    const wrappers = document.querySelectorAll('.action-menu-wrapper');

    wrappers.forEach(function (wrapper) {
      const menu = wrapper.querySelector('.action-menu');
      if (!menu) return;

      if (isToggle && wrapper.contains(isToggle)) {
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.action-menu.open').forEach(function (openMenu) {
          openMenu.classList.remove('open');
        });
        if (!isOpen) {
          menu.classList.add('open');
        }
      } else {
        menu.classList.remove('open');
      }
    });
  });
</script>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




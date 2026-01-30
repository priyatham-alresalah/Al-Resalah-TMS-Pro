<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('certificates', 'view');

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
  file_get_contents(SUPABASE_URL . "/rest/v1/trainings?select=id,training_date,client_id,course_name", false, $ctx),
  true
) ?: [];

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
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        // Debug: Log if training is missing or training_date is empty
        if (!$training) {
          error_log("Certificates page: Training not found for ID: {$c['training_id']} (Certificate: {$c['certificate_no']})");
        } elseif (empty($training['training_date'])) {
          error_log("Certificates page: Training date is empty for training ID: {$c['training_id']} (Certificate: {$c['certificate_no']})");
        }
      ?>
      <tr>
        <td><?= htmlspecialchars($c['certificate_no']) ?></td>
        <td><?= htmlspecialchars($candidate['full_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
        <td>
          <?php 
            if ($training && !empty($training['training_date'])) {
              $date = strtotime($training['training_date']);
              echo $date !== false ? date('d M Y', $date) : '-';
            } elseif (!empty($c['issued_date'])) {
              // Fallback: show issued date when training date is missing (e.g. old records)
              $date = strtotime($c['issued_date']);
              echo $date !== false ? date('d M Y', $date) . ' <span style="color:#6b7280;font-size:11px;">(issued)</span>' : '-';
            } else {
              echo '-';
            }
          ?>
        </td>
        <td><?= !empty($c['issued_date']) ? date('d M Y', strtotime($c['issued_date'])) : '-' ?></td>
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
                <?= csrfField() ?>
                <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
                <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #374151;">Send Mail</button>
              </form>
              <a href="<?= BASE_PATH ?>/api/certificates/download.php?id=<?= $c['id'] ?>">Download</a>
              <form method="post" action="../api/certificates/regenerate.php" style="margin: 0;" onsubmit="return confirm('Regenerate PDF for this certificate? Next download will create a fresh PDF.');">
                <?= csrfField() ?>
                <input type="hidden" name="certificate_id" value="<?= $c['id'] ?>">
                <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #374151;">Regenerate PDF</button>
              </form>
              <?php if ($status === 'active'): ?>
                <div class="danger">
                  <form method="post" action="../api/certificates/revoke.php" onsubmit="return confirm('Revoke this certificate?')" style="margin: 0;">
                    <?= csrfField() ?>
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




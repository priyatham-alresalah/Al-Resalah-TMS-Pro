<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';
require '../includes/workflow.php';

/* RBAC Check */
requirePermission('invoices', 'create');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Completed trainings (include inquiry_id to fetch approved quotation) */
$trainingsRaw = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?status=eq.completed&select=id,course_name,client_id,training_date,inquiry_id&order=training_date.desc",
    false,
    $ctx
  ),
  true
) ?: [];

/* Training IDs that have an active certificate */
$certsRaw = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?status=eq.active&select=training_id",
    false,
    $ctx
  ),
  true
) ?: [];
$certTrainingIds = array_unique(array_column($certsRaw, 'training_id'));

/* Training IDs that already have an invoice */
$invoicesRaw = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?select=training_id",
    false,
    $ctx
  ),
  true
) ?: [];
$invoicedTrainingIds = array_unique(array_column($invoicesRaw, 'training_id'));

/* Eligible: completed, has certificate, no invoice yet */
$eligibleTrainings = [];
foreach ($trainingsRaw as $t) {
  $tid = $t['id'] ?? null;
  if (!$tid) continue;
  if (!in_array($tid, $certTrainingIds, true)) continue;
  if (in_array($tid, $invoicedTrainingIds, true)) continue;
  $eligibleTrainings[] = $t;
}

/* Fetch approved quotations for eligible trainings (amounts come from quotation) */
$quotationByInquiry = [];
$inquiryIds = array_unique(array_filter(array_column($eligibleTrainings, 'inquiry_id')));
if (!empty($inquiryIds)) {
  $inquiryIdsSet = array_flip($inquiryIds);
  $quotationsRaw = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?status=eq.accepted&select=inquiry_id,subtotal,vat,total&order=created_at.desc&limit=500",
      false,
      $ctx
    ),
    true
  ) ?: [];
  foreach ($quotationsRaw as $q) {
    $iid = $q['inquiry_id'] ?? null;
    if ($iid && isset($inquiryIdsSet[$iid]) && !isset($quotationByInquiry[$iid])) {
      $quotationByInquiry[$iid] = [
        'amount' => floatval($q['subtotal'] ?? 0),
        'vat' => floatval($q['vat'] ?? 0),
        'total' => floatval($q['total'] ?? 0)
      ];
    }
  }
}

/* Clients for display */
$clientsRaw = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?select=id,company_name",
    false,
    $ctx
  ),
  true
) ?: [];
$clientMap = [];
foreach ($clientsRaw as $c) {
  $clientMap[$c['id']] = $c['company_name'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Invoice</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Create Invoice</h2>
      <p class="muted">Create an invoice for a training that has an issued certificate</p>
    </div>
    <a href="<?= BASE_PATH ?>/pages/invoices.php" class="btn btn-cancel">Back to Invoices</a>
  </div>

  <?php if (empty($eligibleTrainings)): ?>
    <div class="card">
      <p>No trainings are eligible for invoicing. A training must be <strong>completed</strong>, have an <strong>active certificate</strong> issued, and <strong>not already have an invoice</strong>. Amounts are taken from the <strong>approved quotation</strong> for that training.</p>
      <p><a href="<?= BASE_PATH ?>/pages/trainings.php">View Trainings</a> | <a href="<?= BASE_PATH ?>/pages/invoices.php">Back to Invoices</a></p>
    </div>
  <?php else: ?>
    <div class="card">
      <form method="post" action="<?= BASE_PATH ?>/api/invoices/create.php" id="invoiceCreateForm">
        <?= csrfField() ?>
        <div class="form-group">
          <label>Training *</label>
          <select name="training_id" id="training_id" required>
            <option value="">— Select training —</option>
            <?php foreach ($eligibleTrainings as $t):
              $iid = $t['inquiry_id'] ?? null;
              $q = ($iid !== null && isset($quotationByInquiry[$iid])) ? $quotationByInquiry[$iid] : null;
              $qAmount = $q ? $q['amount'] : '';
              $qVat = $q ? $q['vat'] : '0';
              $qTotal = $q ? $q['total'] : '';
            ?>
              <option value="<?= htmlspecialchars($t['id']) ?>"
                data-client-id="<?= htmlspecialchars($t['client_id'] ?? '') ?>"
                data-course="<?= htmlspecialchars($t['course_name'] ?? '') ?>"
                data-amount="<?= htmlspecialchars($qAmount !== '' ? $qAmount : '') ?>"
                data-vat="<?= htmlspecialchars($qVat !== '' ? $qVat : '0') ?>"
                data-total="<?= htmlspecialchars($qTotal !== '' ? $qTotal : '') ?>">
                <?= htmlspecialchars($t['course_name'] ?? '') ?> — <?= htmlspecialchars($clientMap[$t['client_id']] ?? 'Client') ?> (<?= $t['training_date'] ? date('d M Y', strtotime($t['training_date'])) : '-' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="client_id" id="client_id" value="">
        <div class="form-group">
          <label>Amount (AED) *</label>
          <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="">
        </div>
        <div class="form-group">
          <label>VAT (AED)</label>
          <input type="number" name="vat" id="vat" step="0.01" min="0" value="0">
        </div>
        <div class="form-group">
          <label>Total (AED)</label>
          <input type="number" name="total" id="total" step="0.01" readonly value="">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Create Invoice</button>
          <a href="<?= BASE_PATH ?>/pages/invoices.php" class="btn btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
    <script>
      (function() {
        var trainingId = document.getElementById('training_id');
        var clientId = document.getElementById('client_id');
        var amount = document.getElementById('amount');
        var vat = document.getElementById('vat');
        var total = document.getElementById('total');

        function updateTotal() {
          var a = parseFloat(amount.value) || 0;
          var v = parseFloat(vat.value) || 0;
          total.value = (a + v).toFixed(2);
        }

        trainingId.addEventListener('change', function() {
          var opt = this.options[this.selectedIndex];
          if (opt && opt.value) {
            clientId.value = opt.getAttribute('data-client-id') || '';
            /* Prefill from approved quotation */
            var amt = opt.getAttribute('data-amount') || '';
            var v = opt.getAttribute('data-vat') || '0';
            var tot = opt.getAttribute('data-total') || '';
            amount.value = amt;
            vat.value = v;
            if (tot !== '') {
              total.value = tot;
            } else {
              updateTotal();
            }
          } else {
            clientId.value = '';
            amount.value = '';
            vat.value = '0';
            total.value = '';
            updateTotal();
          }
        });

        amount.addEventListener('input', updateTotal);
        vat.addEventListener('input', updateTotal);
        updateTotal();
      })();
    </script>
  <?php endif; ?>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>

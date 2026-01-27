<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH INVOICES */
$invoices = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?order=created_at.desc",
    false,
    $ctx
  ),
  true
);

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
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Invoices</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Invoices</h2>
  <p class="muted">Training invoices and payments</p>

  <table class="table">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Client</th>
        <th>Amount</th>
        <th>VAT</th>
        <th>Total</th>
        <th>Status</th>
        <th>Issued</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if (!empty($invoices)): foreach ($invoices as $inv): ?>
      <tr>
        <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
        <td>
          <?= htmlspecialchars(
            $clientMap[$inv['client_id']]['company_name'] ?? '-'
          ) ?>
        </td>
        <td><?= number_format($inv['amount'], 2) ?></td>
        <td><?= number_format($inv['vat'], 2) ?></td>
        <td><strong><?= number_format($inv['total'], 2) ?></strong></td>
        <td>
          <span class="badge badge-<?= strtolower($inv['status']) ?>">
            <?= strtoupper($inv['status']) ?>
          </span>
        </td>
        <td>
          <?= $inv['issued_date']
            ? date('d M Y', strtotime($inv['issued_date']))
            : '-' ?>
        </td>
        <td class="col-actions">
          <div class="action-menu-wrapper">
            <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
              &#8942;
            </button>
            <div class="action-menu">
              <a href="invoice_view.php?id=<?= $inv['id'] ?>">View</a>
              <a href="api/invoices/download.php?id=<?= $inv['id'] ?>">Download</a>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="8">No invoices found</td></tr>
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

<?php include 'layout/footer.php'; ?>
</body>
</html>

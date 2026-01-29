<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/pagination.php';
require '../includes/cache.php';

/* RBAC Check */
requirePermission('invoices', 'view');

/* PAGINATION */
$pagination = getPaginationParams();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Prefer: count=exact"
  ]
]);

/* FETCH INVOICES (paginated) */
$invoicesUrl = SUPABASE_URL . "/rest/v1/invoices?order=created_at.desc&limit=$limit&offset=$offset";
$invoicesResponse = @file_get_contents($invoicesUrl, false, $ctx);

// Get total count from headers
$totalCount = 0;
if ($invoicesResponse !== false) {
  $responseHeaders = $http_response_header ?? [];
  foreach ($responseHeaders as $header) {
    if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $header, $matches)) {
      $totalCount = intval($matches[1]);
      break;
    }
  }
}

$invoices = json_decode($invoicesResponse, true) ?: [];
$totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

/* FETCH CLIENTS (cached) */
$clientsCacheKey = 'clients_all';
$clients = getCache($clientsCacheKey, 600);
if ($clients === null) {
  $clients = json_decode(
    @file_get_contents(SUPABASE_URL . "/rest/v1/clients?select=id,company_name", false, $ctx),
    true
  ) ?: [];
  setCache($clientsCacheKey, $clients);
}

$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Invoices</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

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
              <a href="invoice_edit.php?id=<?= $inv['id'] ?>">Edit</a>
              <a href="<?= BASE_PATH ?>/api/invoices/print_pdf.php?id=<?= $inv['id'] ?>" target="_blank">Print PDF</a>
              <form method="post" action="../api/invoices/send_email.php" style="margin: 0;">
                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer; font-size: 14px; font-weight: 500; color: #374151;">Send Mail</button>
              </form>
              <a href="<?= BASE_PATH ?>/api/invoices/download.php?id=<?= $inv['id'] ?>">Download</a>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr>
        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
          <div style="font-size: 16px; margin-bottom: 8px;">No invoices found</div>
          <div style="font-size: 14px;">Invoices will appear here once created</div>
        </td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>

  <!-- Mobile Cards -->
  <div class="mobile-cards">
    <?php if (!empty($invoices)): foreach ($invoices as $inv): 
      $status = strtolower($inv['status'] ?? 'unpaid');
      $badgeClass = 'badge-info';
      if ($status === 'paid') $badgeClass = 'badge-success';
      elseif ($status === 'overdue') $badgeClass = 'badge-danger';
    ?>
      <div class="mobile-card">
        <div class="mobile-card-header">
          <div class="mobile-card-title"><?= htmlspecialchars($inv['invoice_no']) ?></div>
          <span class="badge <?= $badgeClass ?> mobile-card-badge">
            <?= strtoupper($status) ?>
          </span>
        </div>
        <div class="mobile-card-field">
          <span class="mobile-card-label">Client:</span>
          <span class="mobile-card-value">
            <?= htmlspecialchars($clientMap[$inv['client_id']]['company_name'] ?? '-') ?>
          </span>
        </div>
        <div class="mobile-card-field">
          <span class="mobile-card-label">Amount:</span>
          <span class="mobile-card-value"><?= number_format($inv['amount'], 2) ?></span>
        </div>
        <div class="mobile-card-field">
          <span class="mobile-card-label">VAT:</span>
          <span class="mobile-card-value"><?= number_format($inv['vat'], 2) ?></span>
        </div>
        <div class="mobile-card-field">
          <span class="mobile-card-label">Total:</span>
          <span class="mobile-card-value"><strong><?= number_format($inv['total'], 2) ?></strong></span>
        </div>
        <div class="mobile-card-field">
          <span class="mobile-card-label">Issued:</span>
          <span class="mobile-card-value">
            <?= $inv['issued_date'] ? date('d M Y', strtotime($inv['issued_date'])) : '-' ?>
          </span>
        </div>
        <div class="mobile-card-actions">
          <a href="invoice_edit.php?id=<?= $inv['id'] ?>" class="btn">View / Edit</a>
        </div>
      </div>
    <?php endforeach; else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">📄</div>
        <div class="empty-state-title">No invoices found</div>
        <div class="empty-state-message">Invoices will appear here once created</div>
      </div>
    <?php endif; ?>
  </div>

  <?php
  // Render pagination
  if ($totalPages > 1) {
    renderPagination($page, $totalPages);
  }
  ?>

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




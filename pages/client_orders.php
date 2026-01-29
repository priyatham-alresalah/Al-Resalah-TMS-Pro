<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* Permission Check */
if (!canAccessModule('client_orders')) {
  http_response_code(403);
  die('Access denied');
}

$role = getUserRole();
$userId = $_SESSION['user']['id'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Fetch client orders with related data
$baseUrl = SUPABASE_URL . "/rest/v1/client_orders?select=*,quotations(quotation_no,status,inquiry_id,inquiries(course_name,client_id,clients(company_name))),profiles!client_orders_verified_by_fkey(full_name)";
$baseUrl .= "&order=created_at.desc";

$clientOrders = json_decode(
  @file_get_contents($baseUrl, false, $ctx),
  true
) ?: [];

// Fetch quotations for creating new orders
$quotations = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/quotations?status=in.(approved,accepted)&select=id,quotation_no,inquiry_id,inquiries(course_name,clients(company_name))&order=created_at.desc",
    false,
    $ctx
  ),
  true
) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Client Orders (LPO)</title>
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
      <h2>Client Orders (LPO)</h2>
      <p class="muted">Manage LPO uploads and verifications</p>
    </div>
    <?php if (hasPermission('client_orders', 'create')): ?>
      <button type="button" class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='block'">
        + Upload LPO
      </button>
    <?php endif; ?>
  </div>

  <?php if (isset($_GET['success'])): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th>LPO Number</th>
        <th>Quotation No</th>
        <th>Client</th>
        <th>Course</th>
        <th>Received Date</th>
        <th>Status</th>
        <th>Verified By</th>
        <th>Verified At</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($clientOrders)): ?>
        <?php foreach ($clientOrders as $order): 
          $quotation = $order['quotations'] ?? null;
          $inquiry = $quotation['inquiries'] ?? null;
          $client = $inquiry['clients'] ?? null;
          $status = strtolower($order['status'] ?? 'pending');
          $badgeClass = 'badge-warning';
          if ($status === 'verified') $badgeClass = 'badge-success';
          elseif ($status === 'rejected') $badgeClass = 'badge-danger';
        ?>
          <tr>
            <td><?= htmlspecialchars($order['lpo_number']) ?></td>
            <td><?= htmlspecialchars($quotation['quotation_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= $order['received_date'] ? date('d M Y', strtotime($order['received_date'])) : '-' ?></td>
            <td>
              <span class="badge <?= $badgeClass ?>">
                <?= strtoupper($status) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($order['profiles']['full_name'] ?? '-') ?></td>
            <td><?= $order['verified_at'] ? date('d M Y H:i', strtotime($order['verified_at'])) : '-' ?></td>
            <td class="col-actions">
              <div class="action-menu-wrapper">
                <button type="button" class="btn-icon action-menu-toggle">&#8942;</button>
                <div class="action-menu">
                  <?php if ($status === 'pending' && hasPermission('client_orders', 'update')): ?>
                    <form method="post" action="../api/client_orders/verify.php" style="margin: 0;">
                      <?php require '../includes/csrf.php'; echo csrfField(); ?>
                      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                      <input type="hidden" name="action" value="verify">
                      <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Verify</button>
                    </form>
                    <form method="post" action="../api/client_orders/verify.php" style="margin: 0;">
                      <?php require '../includes/csrf.php'; echo csrfField(); ?>
                      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Reject</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($order['lpo_file_path']): ?>
                    <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($order['lpo_file_path']) ?>" target="_blank">View LPO</a>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="9">No client orders found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

<!-- Upload LPO Modal -->
<?php if (hasPermission('client_orders', 'create')): ?>
<div id="uploadModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
  <div style="background:white; margin:5% auto; padding:20px; border-radius:8px; max-width:500px;">
    <h3>Upload LPO</h3>
    <form method="post" action="../api/client_orders/create.php" enctype="multipart/form-data">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      <div style="margin-bottom:15px;">
        <label>Quotation *</label>
        <select name="quotation_id" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
          <option value="">Select Quotation</option>
          <?php foreach ($quotations as $q): 
            $inq = $q['inquiries'] ?? null;
            $cl = $inq['clients'] ?? null;
          ?>
            <option value="<?= $q['id'] ?>">
              <?= htmlspecialchars($q['quotation_no']) ?> - 
              <?= htmlspecialchars($cl['company_name'] ?? '') ?> - 
              <?= htmlspecialchars($inq['course_name'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:15px;">
        <label>LPO Number *</label>
        <input type="text" name="lpo_number" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:15px;">
        <label>Received Date</label>
        <input type="date" name="received_date" value="<?= date('Y-m-d') ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:15px;">
        <label>LPO File (Optional)</label>
        <input type="file" name="lpo_file" accept=".pdf,.jpg,.jpeg,.png" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

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

  // Close modal when clicking outside
  window.onclick = function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target == modal) {
      modal.style.display = "none";
    }
  }
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>

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

// Fetch client orders - use simpler query to avoid join issues
$baseUrl = SUPABASE_URL . "/rest/v1/client_orders?select=*";
$baseUrl .= "&order=created_at.desc";

// Debug: Log the query
error_log("Client Orders query: $baseUrl");

// Fetch client orders first
$clientOrders = json_decode(
  @file_get_contents($baseUrl, false, $ctx),
  true
) ?: [];

// Debug: Log results
error_log("Client Orders page - Found " . count($clientOrders) . " order(s)");
if (empty($clientOrders)) {
  error_log("No client orders found. Query was: $baseUrl");
  
  // Try fetching ALL orders to see if any exist (for debugging)
  $allOrdersUrl = SUPABASE_URL . "/rest/v1/client_orders?select=id,lpo_number,status,quotation_id,created_at&order=created_at.desc&limit=10";
  $allOrders = json_decode(
    @file_get_contents($allOrdersUrl, false, $ctx),
    true
  ) ?: [];
  error_log("Total client orders in database: " . count($allOrders));
  if (!empty($allOrders)) {
    error_log("Sample orders: " . json_encode(array_slice($allOrders, 0, 3)));
  }
}

// Now fetch related data separately if orders exist
$quotationMap = [];
$inquiryMap = [];
$clientMap = [];
$profileMap = [];

if (!empty($clientOrders)) {
  // Get quotation IDs, inquiry IDs, client IDs, and verified_by IDs
  $quotationIds = array_unique(array_filter(array_column($clientOrders, 'quotation_id')));
  $verifiedByIds = array_unique(array_filter(array_column($clientOrders, 'verified_by')));
  
  // Fetch quotations
  if (!empty($quotationIds)) {
    $quotationIdsStr = implode(',', $quotationIds);
    $quotations = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/quotations?id=in.($quotationIdsStr)&select=id,quotation_no,status,inquiry_id",
        false,
        $ctx
      ),
      true
    ) ?: [];
    foreach ($quotations as $q) {
      $quotationMap[$q['id']] = $q;
    }
    
    // Get inquiry IDs from quotations
    $inquiryIds = array_unique(array_filter(array_column($quotations, 'inquiry_id')));
    
    // Fetch inquiries
    if (!empty($inquiryIds)) {
      $inquiryIdsStr = implode(',', $inquiryIds);
      $inquiries = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/inquiries?id=in.($inquiryIdsStr)&select=id,course_name,client_id",
          false,
          $ctx
        ),
        true
      ) ?: [];
      foreach ($inquiries as $inq) {
        $inquiryMap[$inq['id']] = $inq;
      }
      
      // Get client IDs from inquiries
      $clientIds = array_unique(array_filter(array_column($inquiries, 'client_id')));
      
      // Fetch clients
      if (!empty($clientIds)) {
        $clientIdsStr = implode(',', $clientIds);
        $clients = json_decode(
          @file_get_contents(
            SUPABASE_URL . "/rest/v1/clients?id=in.($clientIdsStr)&select=id,company_name",
            false,
            $ctx
          ),
          true
        ) ?: [];
        foreach ($clients as $cl) {
          $clientMap[$cl['id']] = $cl;
        }
      }
    }
  }
  
  // Fetch verified_by profiles
  if (!empty($verifiedByIds)) {
    $verifiedByIdsStr = implode(',', $verifiedByIds);
    $profiles = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/profiles?id=in.($verifiedByIdsStr)&select=id,full_name",
        false,
        $ctx
      ),
      true
    ) ?: [];
    foreach ($profiles as $prof) {
      $profileMap[$prof['id']] = $prof;
    }
  }
  
  // Attach related data to orders for easier access
  foreach ($clientOrders as &$order) {
    $quotation = $quotationMap[$order['quotation_id']] ?? null;
    $inquiry = $quotation ? ($inquiryMap[$quotation['inquiry_id']] ?? null) : null;
    $client = $inquiry ? ($clientMap[$inquiry['client_id']] ?? null) : null;
    
    $order['quotations'] = $quotation;
    $order['quotations']['inquiries'] = $inquiry;
    $order['quotations']['inquiries']['clients'] = $client;
    $order['profiles'] = $order['verified_by'] ? ($profileMap[$order['verified_by']] ?? null) : null;
  }
  unset($order); // Unset reference
}

// Fetch quotations for creating new orders - simplified query
$quotationsUrl = SUPABASE_URL . "/rest/v1/quotations?status=in.(approved,accepted)&select=id,quotation_no,inquiry_id&order=created_at.desc";
$quotations = json_decode(
  @file_get_contents($quotationsUrl, false, $ctx),
  true
) ?: [];

// Fetch inquiry and client data separately for dropdown
if (!empty($quotations)) {
  $inquiryIdsForDropdown = array_unique(array_filter(array_column($quotations, 'inquiry_id')));
  if (!empty($inquiryIdsForDropdown)) {
    $inquiryIdsStr = implode(',', $inquiryIdsForDropdown);
    $inquiriesForDropdown = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/inquiries?id=in.($inquiryIdsStr)&select=id,course_name,client_id",
        false,
        $ctx
      ),
      true
    ) ?: [];
    
    $clientIdsForDropdown = array_unique(array_filter(array_column($inquiriesForDropdown, 'client_id')));
    if (!empty($clientIdsForDropdown)) {
      $clientIdsStr = implode(',', $clientIdsForDropdown);
      $clientsForDropdown = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/clients?id=in.($clientIdsStr)&select=id,company_name",
          false,
          $ctx
        ),
        true
      ) ?: [];
      
      // Create maps
      $inquiryMapForDropdown = [];
      foreach ($inquiriesForDropdown as $inq) {
        $inquiryMapForDropdown[$inq['id']] = $inq;
      }
      $clientMapForDropdown = [];
      foreach ($clientsForDropdown as $cl) {
        $clientMapForDropdown[$cl['id']] = $cl;
      }
      
      // Attach to quotations
      foreach ($quotations as &$q) {
        $inq = $inquiryMapForDropdown[$q['inquiry_id']] ?? null;
        $cl = $inq ? ($clientMapForDropdown[$inq['client_id']] ?? null) : null;
        $q['inquiries'] = $inq;
        $q['inquiries']['clients'] = $cl;
      }
      unset($q);
    }
  }
}
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

  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <?php 
  // Debug: Show helpful message if no client orders found
  if (empty($clientOrders)): 
    // Try to fetch all orders (without filters) to check if any exist
    $allOrdersUrl = SUPABASE_URL . "/rest/v1/client_orders?select=id,lpo_number,status,quotation_id,created_at&order=created_at.desc&limit=20";
    $allOrdersDebug = json_decode(
      @file_get_contents($allOrdersUrl, false, $ctx),
      true
    ) ?: [];
  ?>
    <?php if (!empty($allOrdersDebug)): ?>
      <div style="background: #fef3c7; color: #92400e; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
        <strong>⚠ Query Issue Detected:</strong>
        <p style="margin: 8px 0 0 0;">
          Found <strong><?= count($allOrdersDebug) ?></strong> client order(s) in database, but they're not displaying correctly.<br>
          This might be due to a query join issue. Please check error logs for details.
        </p>
        <?php if (isAdmin() || $role === 'admin'): ?>
          <details style="margin-top: 10px;">
            <summary style="cursor: pointer; font-weight: bold; color: #92400e;">Show All Client Orders in Database (Debug)</summary>
            <table style="margin-top: 10px; width: 100%; font-size: 12px; border-collapse: collapse;">
              <thead>
                <tr style="background: #f3f4f6;">
                  <th style="padding: 8px; border: 1px solid #d1d5db;">LPO Number</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Status</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Quotation ID</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allOrdersDebug as $order): ?>
                  <tr>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= htmlspecialchars($order['lpo_number'] ?? '-') ?></td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;">
                      <span class="badge badge-<?= strtolower($order['status'] ?? 'pending') ?>">
                        <?= strtoupper($order['status'] ?? 'PENDING') ?>
                      </span>
                    </td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= htmlspecialchars($order['quotation_id'] ?? '-') ?></td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= $order['created_at'] ? date('d M Y H:i', strtotime($order['created_at'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </details>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #dc2626;">
        <strong>❌ No Client Orders Found:</strong>
        <p style="margin: 8px 0 0 0;">
          There are no LPO/Client Orders in the database at all.<br>
          <strong>To create a Client Order:</strong>
          <ol style="margin: 8px 0 0 20px;">
            <li>Ensure you have an <strong>approved or accepted quotation</strong> (check Quotations module)</li>
            <li>Click the <strong>"+ Upload LPO"</strong> button above</li>
            <li>Select the quotation and enter LPO details</li>
            <li>Upload the LPO file (optional)</li>
          </ol>
          <strong>Prerequisites:</strong>
          <ul style="margin: 8px 0 0 20px;">
            <li>Quotation must be in <strong>'approved'</strong> or <strong>'accepted'</strong> status</li>
            <li>You need permission to create client orders</li>
          </ul>
        </p>
      </div>
    <?php endif; ?>
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
          // Handle both old format (nested) and new format (attached)
          $quotation = $order['quotations'] ?? null;
          $inquiry = $quotation['inquiries'] ?? null;
          $client = $inquiry['clients'] ?? null;
          $status = strtolower($order['status'] ?? 'pending');
          $badgeClass = 'badge-warning';
          if ($status === 'verified') $badgeClass = 'badge-success';
          elseif ($status === 'rejected') $badgeClass = 'badge-danger';
          
          // Handle verified_by profile
          $verifiedByName = $order['profiles']['full_name'] ?? '-';
        ?>
          <tr>
            <td><?= htmlspecialchars($order['lpo_number'] ?? '-') ?></td>
            <td><?= htmlspecialchars($quotation['quotation_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= $order['received_date'] ? date('d M Y', strtotime($order['received_date'])) : '-' ?></td>
            <td>
              <span class="badge <?= $badgeClass ?>">
                <?= strtoupper($status) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($verifiedByName) ?></td>
            <td><?= $order['verified_at'] ? date('d M Y H:i', strtotime($order['verified_at'])) : '-' ?></td>
            <td class="col-actions">
              <div class="action-menu-wrapper">
                <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
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

<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/pagination.php';
require '../includes/cache.php';

/* RBAC Check */
requirePermission('inquiries', 'view');

$role = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

/* ---------------------------
   PAGINATION
---------------------------- */
$pagination = getPaginationParams();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

/* ---------------------------
   FETCH CLIENTS (cached)
---------------------------- */
$cacheKey = 'clients_' . ($role === 'admin' ? 'all' : $userId);
$clients = getCache($cacheKey, 600); // Cache for 10 minutes

if ($clients === null) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  $baseUrl = SUPABASE_URL . "/rest/v1/clients?select=id,company_name";
  if ($role !== 'admin') {
    $baseUrl .= "&created_by=eq.$userId";
  }
  $clients = json_decode(
    @file_get_contents($baseUrl, false, $ctx),
    true
  ) ?: [];
  
  setCache($cacheKey, $clients);
}

$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c['company_name'];
}

/* ---------------------------
   FETCH INQUIRIES (paginated)
---------------------------- */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Prefer: count=exact"
  ]
]);

// Apply role-based filters
$baseUrl = SUPABASE_URL . "/rest/v1/inquiries?select=*";
if ($role === 'bdo') {
  // BDO sees their own inquiries
  $baseUrl .= "&created_by=eq.$userId";
}
// Admin and other roles see ALL inquiries (no filter)

$baseUrl .= "&order=created_at.desc&limit=$limit&offset=$offset";

$inquiriesResponse = @file_get_contents($baseUrl, false, $ctx);

// Get total count from headers
$totalCount = 0;
if ($inquiriesResponse !== false) {
  $responseHeaders = $http_response_header ?? [];
  foreach ($responseHeaders as $header) {
    if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $header, $matches)) {
      $totalCount = intval($matches[1]);
      break;
    }
  }
}

$inquiries = json_decode($inquiriesResponse, true) ?: [];
$totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

/* ---------------------------
   FETCH CREATOR PROFILES
---------------------------- */
$profileMap = [];
if (!empty($inquiries)) {
  $createdByIds = array_unique(array_filter(array_column($inquiries, 'created_by')));
  
  if (!empty($createdByIds)) {
    $createdByIdsStr = implode(',', $createdByIds);
    $profiles = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/profiles?id=in.($createdByIdsStr)&select=id,full_name",
        false,
        $ctx
      ),
      true
    ) ?: [];
    foreach ($profiles as $prof) {
      $profileMap[$prof['id']] = $prof;
    }
  }
}

/* ---------------------------
   FETCH QUOTATIONS FOR QUOTE AMOUNT
---------------------------- */
$quotationMap = [];
if (!empty($inquiries)) {
  $inquiryIds = array_column($inquiries, 'id');
  $inquiryIdsStr = implode(',', $inquiryIds);
  
  $quotations = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?inquiry_id=in.($inquiryIdsStr)&select=inquiry_id,total",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  foreach ($quotations as $q) {
    if (!isset($quotationMap[$q['inquiry_id']])) {
      $quotationMap[$q['inquiry_id']] = $q['total'] ?? 0;
    }
  }
}

/* ---------------------------
   GROUP INQUIRIES BY BATCH (same client, creator, within 5 sec)
   Build list: each item = single inquiry OR batch of inquiries
---------------------------- */
function groupInquiriesByBatch($inquiries) {
  $groups = [];
  foreach ($inquiries as $index => $inquiry) {
    $clientId = $inquiry['client_id'] ?? null;
    $createdBy = $inquiry['created_by'] ?? null;
    $createdAt = strtotime($inquiry['created_at'] ?? 'now');
    $timeWindow = floor($createdAt / 5) * 5;
    $batchKey = ($clientId ?? 'null') . '_' . ($createdBy ?? 'null') . '_' . $timeWindow;
    
    if (!isset($groups[$batchKey])) {
      $groups[$batchKey] = [];
    }
    $groups[$batchKey][] = $inquiry;
  }
  return $groups;
}

$inquiryBatches = groupInquiriesByBatch($inquiries);

// Ensure batches are created even if grouping fails
if (!empty($inquiries) && empty($inquiryBatches)) {
  error_log("WARNING: Inquiries exist but batch grouping returned empty. Count: " . count($inquiries));
  // Fallback: create batches manually (each inquiry as its own batch)
  foreach ($inquiries as $inq) {
    $clientId = $inq['client_id'] ?? null;
    $createdBy = $inq['created_by'] ?? null;
    $createdAt = strtotime($inq['created_at'] ?? 'now');
    $timeWindow = floor($createdAt / 5) * 5;
    $batchKey = ($clientId ?? 'null') . '_' . ($createdBy ?? 'null') . '_' . $timeWindow;
    if (!isset($inquiryBatches[$batchKey])) {
      $inquiryBatches[$batchKey] = [];
    }
    $inquiryBatches[$batchKey][] = $inq;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Inquiries</title>
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
      <h2>Inquiries</h2>
      <p class="muted">Client training inquiries and quotations</p>
    </div>
    <div class="actions">
      <a href="inquiry_create.php" class="btn">+ Create Inquiry</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th style="width: 36px;"></th>
        <th>Inquiry ID</th>
        <th>Client</th>
        <th>Course</th>
        <th>Status</th>
        <th>Quote Amount</th>
        <th>Created By</th>
        <th>Created</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($inquiryBatches)): ?>
        <!-- Debug: <?= count($inquiries) ?> inquiries, <?= count($inquiryBatches) ?> batches -->
        <?php
        // Helper: render one inquiry row. $expandCell=show expand btn, $batchId=for child rows class, $quoteAllIds=ids for "Quote all" link
        $renderInquiryRow = function($i, $clientMap, $profileMap, $quotationMap, $expandCell = false, $batchId = null, $quoteAllIds = []) {
          $clientId = $i['client_id'] ?? null;
          $status = strtolower($i['status'] ?? 'new');
          $badgeClass = 'badge-info';
          if ($status === 'quoted') $badgeClass = 'badge-warning';
          elseif ($status === 'closed') $badgeClass = 'badge-success';
          
          $creatorName = $profileMap[$i['created_by']]['full_name'] ?? '-';
          $quoteAmount = $quotationMap[$i['id']] ?? null;
          $isChildRow = $batchId !== null && !$expandCell;
          ?>
          <tr<?= $isChildRow ? ' class="batch-' . $batchId . '-child" style="display: none;"' : '' ?>>
            <?php if ($expandCell): ?>
              <td style="vertical-align: middle; padding: 8px;">
                <button type="button" class="batch-toggle-btn" data-batch="<?= $batchId ?>" aria-label="Expand" style="background: none; border: none; cursor: pointer; padding: 4px; color: #3b82f6; font-size: 12px;">▶</button>
              </td>
            <?php else: ?>
              <td></td>
            <?php endif; ?>
            <td><?= htmlspecialchars(substr($i['id'], 0, 8)) ?>...</td>
            <td><?= htmlspecialchars($clientId && isset($clientMap[$clientId]) ? $clientMap[$clientId] : 'Individual') ?></td>
            <td><?= htmlspecialchars($i['course_name']) ?></td>
            <td>
              <span class="badge <?= $badgeClass ?>">
                <?= strtoupper($status) ?>
              </span>
            </td>
            <td>
              <?php if ($quoteAmount !== null): ?>
                <strong><?= number_format($quoteAmount, 2) ?> AED</strong>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($creatorName) ?></td>
            <td><?= $i['created_at'] ? date('d M Y', strtotime($i['created_at'])) : '-' ?></td>
            <td class="col-actions">
              <div class="action-menu-wrapper">
                <?php if (!empty($quoteAllIds)): ?>
                  <a href="inquiry_quote.php?ids=<?= implode(',', $quoteAllIds) ?>" class="btn" style="font-size: 12px; padding: 6px 10px; margin-right: 6px;">Quote all (<?= count($quoteAllIds) ?>)</a>
                <?php endif; ?>
                <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
                <div class="action-menu">
                  <?php if ($status === 'new'): ?>
                    <a href="inquiry_quote.php?id=<?= $i['id'] ?>">Quote</a>
                    <?php if (hasPermission('inquiries', 'delete')): ?>
                      <form method="post" action="../api/inquiries/delete.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this inquiry? This action cannot be undone.');">
                        <?php require '../includes/csrf.php'; echo csrfField(); ?>
                        <input type="hidden" name="inquiry_id" value="<?= $i['id'] ?>">
                        <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Delete</button>
                      </form>
                    <?php endif; ?>
                  <?php elseif ($status === 'quoted'): ?>
                    <a href="<?= BASE_PATH ?>/api/inquiries/download_quote.php?inquiry_id=<?= $i['id'] ?>">Download PDF</a>
                    <?php if (!empty($i['quote_pdf'])): ?>
                      <form action="<?= BASE_PATH ?>/api/inquiries/send_quote_email.php" method="post" style="margin: 0;">
                        <?php require '../includes/csrf.php'; echo csrfField(); ?>
                        <input type="hidden" name="inquiry_id" value="<?= $i['id'] ?>">
                        <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Send Email</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                  <a href="inquiry_view.php?id=<?= $i['id'] ?>">View Details</a>
                </div>
              </div>
            </td>
          </tr>
          <?php
        };
        ?>
        
        <?php foreach ($inquiryBatches as $batchKey => $batchList): 
          $batchId = 'b' . substr(md5($batchKey), 0, 8);
          $isMulti = count($batchList) > 1;
          $first = $batchList[0];
          $quotableIds = array_values(array_map(function($inq) { return $inq['id']; }, array_filter($batchList, function($inq) { return strtolower($inq['status'] ?? '') === 'new'; })));
          $quoteAllIds = $isMulti ? $quotableIds : [];
        ?>
          <?php
          // First row of batch (always visible). If multi, pass expand + Quote all ids.
          $renderInquiryRow($first, $clientMap, $profileMap, $quotationMap, $isMulti, $batchId, $quoteAllIds);
          ?>
          <?php if ($isMulti): ?>
            <?php for ($j = 1; $j < count($batchList); $j++): 
              $renderInquiryRow($batchList[$j], $clientMap, $profileMap, $quotationMap, false, $batchId, []);
            endfor; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" style="text-align: center; padding: 60px 20px; color: #6b7280;">
            <div style="font-size: 18px; margin-bottom: 8px; color: #9ca3af;">No inquiries found</div>
            <div style="font-size: 14px;">Create your first inquiry to get started</div>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $queryParams = $_GET;
      unset($queryParams['page']);
      $queryString = http_build_query($queryParams);
      $baseUrl = 'inquiries.php' . ($queryString ? '?' . $queryString . '&' : '?');
      
      if ($page > 1): ?>
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="btn">Previous</a>
      <?php endif; ?>
      
      <span style="margin: 0 16px;">
        Page <?= $page ?> of <?= $totalPages ?> (<?= $totalCount ?> total)
      </span>
      
      <?php if ($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="btn">Next</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<script src="<?= BASE_PATH ?>/assets/js/mobile.js"></script>
<script>
  // Toggle batch expand/collapse (per batch, closed by default)
  document.addEventListener('click', function (event) {
    const btn = event.target.closest('.batch-toggle-btn');
    if (btn) {
      event.preventDefault();
      event.stopPropagation();
      const batchId = btn.getAttribute('data-batch');
      const rows = document.querySelectorAll('.batch-' + batchId + '-child');
      const isHidden = rows[0] && rows[0].style.display === 'none';
      rows.forEach(function (row) {
        row.style.display = isHidden ? 'table-row' : 'none';
      });
      btn.textContent = isHidden ? '▼' : '▶';
    }
  });

  // Action menu toggle
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

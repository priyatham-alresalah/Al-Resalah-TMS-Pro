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

$inquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?order=created_at.desc&limit=$limit&offset=$offset";
$inquiriesResponse = @file_get_contents($inquiriesUrl, false, $ctx);

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
      <p class="muted">Client training inquiries and conversion</p>
    </div>
    <div class="actions">
      <a href="inquiry_create.php" class="btn">+ Create Inquiry</a>
    </div>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>

  <!-- INQUIRIES LIST -->
  <table class="table">
    <thead>
      <tr>
        <th style="width: 200px;">Client</th>
        <th>Course</th>
        <th>Status</th>
        <th>Quote Amount</th>
        <th>Date</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if ($inquiries): 
      /* Group inquiries by client */
      $groupedInquiries = [];
      foreach ($inquiries as $i) {
        $clientId = $i['client_id'] ?? null;
        $groupKey = $clientId ?: 'individual_' . $i['id'];
        if (!isset($groupedInquiries[$groupKey])) {
          $groupedInquiries[$groupKey] = [];
        }
        $groupedInquiries[$groupKey][] = $i;
      }
      
      foreach ($groupedInquiries as $groupKey => $clientInquiries):
        $clientId = $clientInquiries[0]['client_id'] ?? null; 
        $clientName = ($clientId && isset($clientMap[$clientId])) ? $clientMap[$clientId] : 'Individual';
        $firstInquiry = $clientInquiries[0];
        $totalCount = count($clientInquiries);
        $remainingCount = $totalCount - 1;
        $rowId = 'client_' . ($clientId ?: 'individual_' . $firstInquiry['id']);
    ?>
      <tr class="client-row" data-client-id="<?= $clientId ?? '' ?>">
        <td>
          <?php if ($clientId && isset($clientMap[$clientId])): ?>
            <strong><?= htmlspecialchars($clientMap[$clientId]) ?></strong>
          <?php else: ?>
            <span style="color: #6b7280; font-style: italic;">Individual</span>
          <?php endif; ?>
        </td>
        <td>
          <span><?= htmlspecialchars($firstInquiry['course_name']) ?></span>
          <?php if ($remainingCount > 0): ?>
            <span style="color: #2563eb; font-weight: 600; margin-left: 6px;">+<?= $remainingCount ?></span>
            <button type="button" onclick="toggleClientInquiries('<?= $rowId ?>')" 
                    style="margin-left: 8px; background: none; border: none; color: #2563eb; cursor: pointer; font-size: 12px; text-decoration: underline;">
              <span class="toggle-text-<?= $rowId ?>">Show</span>
            </button>
          <?php endif; ?>
        </td>
        <td>
          <?php
            $status = strtolower($firstInquiry['status'] ?? 'new');
            $badgeClass = 'badge-info';
            if ($status === 'quoted') $badgeClass = 'badge-warning';
            elseif ($status === 'accepted') $badgeClass = 'badge-success';
            elseif ($status === 'rejected') $badgeClass = 'badge-danger';
            elseif ($status === 'closed') $badgeClass = 'badge-success';
          ?>
          <span class="badge <?= $badgeClass ?>">
            <?= strtoupper($status) ?>
          </span>
        </td>
        <td>
          <?php if (!empty($firstInquiry['quote_total'])): ?>
            <?= number_format($firstInquiry['quote_total'], 2) ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td><?= date('d M Y', strtotime($firstInquiry['created_at'])) ?></td>
        <td class="col-actions">
          <div class="action-menu-wrapper">
            <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
              &#8942;
            </button>
            <div class="action-menu">
              <?php if ($status === 'new'): ?>
                <a href="inquiry_quote.php?id=<?= $firstInquiry['id'] ?>">Quote</a>
              <?php elseif ($status === 'quoted'): ?>
                <a href="inquiry_view.php?id=<?= $firstInquiry['id'] ?>">View</a>
                <?php if (!empty($firstInquiry['quote_pdf'])): ?>
                  <a href="<?= BASE_PATH ?>/api/inquiries/download_quote.php?file=<?= urlencode($firstInquiry['quote_pdf']) ?>">Download PDF</a>
                  <form action="../api/inquiries/send_quote_email.php" method="post">
                    <input type="hidden" name="inquiry_id" value="<?= $firstInquiry['id'] ?>">
                    <button type="submit" class="danger">Send Email</button>
                  </form>
                <?php endif; ?>
              <?php elseif ($status === 'accepted'): ?>
                <a href="schedule_training.php?inquiry_id=<?= $firstInquiry['id'] ?>">Schedule Training</a>
              <?php endif; ?>
              <a href="inquiry_view.php?id=<?= $firstInquiry['id'] ?>">View Details</a>
            </div>
          </div>
        </td>
      </tr>
      <?php foreach (array_slice($clientInquiries, 1) as $idx => $i): ?>
        <tr class="client-detail-<?= $rowId ?>" style="display: none;">
          <td></td>
          <td><?= htmlspecialchars($i['course_name']) ?></td>
          <td>
            <?php
              $s = strtolower($i['status'] ?? 'new');
              $bc = 'badge-info';
              if ($s === 'quoted') $bc = 'badge-warning';
              elseif ($s === 'accepted') $bc = 'badge-success';
              elseif ($s === 'rejected') $bc = 'badge-danger';
              elseif ($s === 'closed') $bc = 'badge-success';
            ?>
            <span class="badge <?= $bc ?>"><?= strtoupper($s) ?></span>
          </td>
          <td>
            <?php if (!empty($i['quote_total'])): ?>
              <?= number_format($i['quote_total'], 2) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= date('d M Y', strtotime($i['created_at'])) ?></td>
          <td class="col-actions">
            <div class="action-menu-wrapper">
              <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
                &#8942;
              </button>
              <div class="action-menu">
                <?php
                  $s = strtolower($i['status'] ?? 'new');
                  if ($s === 'new'): ?>
                  <a href="inquiry_quote.php?id=<?= $i['id'] ?>">Quote</a>
                <?php elseif ($s === 'quoted'): ?>
                  <a href="inquiry_view.php?id=<?= $i['id'] ?>">View</a>
                  <?php if (!empty($i['quote_pdf'])): ?>
                    <a href="<?= BASE_PATH ?>/api/inquiries/download_quote.php?file=<?= urlencode($i['quote_pdf']) ?>">Download PDF</a>
                    <form action="../api/inquiries/send_quote_email.php" method="post">
                      <input type="hidden" name="inquiry_id" value="<?= $i['id'] ?>">
                      <button type="submit" class="danger">Send Email</button>
                    </form>
                  <?php endif; ?>
                <?php elseif ($s === 'accepted'): ?>
                  <a href="convert_to_training.php?inquiry_id=<?= $i['id'] ?>">Create Training</a>
                <?php endif; ?>
                <a href="inquiry_view.php?id=<?= $i['id'] ?>">View Details</a>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; else: ?>
      <tr>
        <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
          <div style="font-size: 16px; margin-bottom: 8px;">No inquiries found</div>
          <div style="font-size: 14px;">Create your first inquiry to get started</div>
        </td>
      </tr>
    <?php endif; ?>

    </tbody>
  </table>

<script src="<?= BASE_PATH ?>/assets/js/mobile.js"></script>
<script>
  function toggleClientInquiries(rowId) {
    const rows = document.querySelectorAll('.client-detail-' + rowId);
    const toggleText = document.querySelector('.toggle-text-' + rowId);
    const isHidden = rows[0].style.display === 'none';
    
    rows.forEach(row => {
      row.style.display = isHidden ? 'table-row' : 'none';
    });
    
    if (toggleText) {
      toggleText.textContent = isHidden ? 'Hide' : 'Show';
    }
  }

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




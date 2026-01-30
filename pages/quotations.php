<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* Permission Check */
if (!canAccessModule('quotations')) {
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

// Fetch quotations based on role
// Use simpler query to avoid join issues with Supabase PostgREST
$baseUrl = SUPABASE_URL . "/rest/v1/quotations?select=*";

// Apply role-based filters
if ($role === 'bdo') {
  // BDO sees their own quotations
  $baseUrl .= "&created_by=eq.$userId";
} elseif ($role === 'bdm') {
  // BDM sees quotations pending approval, approved, and accepted
  $baseUrl .= "&status=in.(pending_approval,approved,accepted,rejected)";
}
// Admin and other roles see ALL quotations (no filter)

$baseUrl .= "&order=created_at.desc";

// Debug: Log the query
error_log("Quotations query for role '$role' (user: $userId): $baseUrl");

// Fetch quotations first
$quotations = json_decode(
  @file_get_contents($baseUrl, false, $ctx),
  true
) ?: [];

// Debug: Log results
error_log("Quotations page - Role: $role, User ID: $userId, Found " . count($quotations) . " quotation(s)");
if (empty($quotations)) {
  error_log("No quotations found. Query was: $baseUrl");
  
  // Try fetching ALL quotations to see if any exist (for debugging)
  $allQuotationsUrl = SUPABASE_URL . "/rest/v1/quotations?select=id,quotation_no,status,created_by&order=created_at.desc&limit=10";
  $allQuotations = json_decode(
    @file_get_contents($allQuotationsUrl, false, $ctx),
    true
  ) ?: [];
  error_log("Total quotations in database: " . count($allQuotations));
  if (!empty($allQuotations)) {
    error_log("Sample quotations: " . json_encode(array_slice($allQuotations, 0, 3)));
  }
}

// Now fetch related data separately if quotations exist
$inquiryMap = [];
$profileMap = [];

if (!empty($quotations)) {
  // Get inquiry IDs and creator IDs
  $inquiryIds = array_unique(array_filter(array_column($quotations, 'inquiry_id')));
  $createdByIds = array_unique(array_filter(array_column($quotations, 'created_by')));
  
  // Fetch inquiries
  if (!empty($inquiryIds)) {
    $inquiryIdsStr = implode(',', $inquiryIds);
    $inquiries = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/inquiries?id=in.($inquiryIdsStr)&select=id,client_id,course_name",
        false,
        $ctx
      ),
      true
    ) ?: [];
    foreach ($inquiries as $inq) {
      $inquiryMap[$inq['id']] = $inq;
    }
  }
  
  // Fetch creator profiles
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
  
  // Attach related data to quotations for easier access
  foreach ($quotations as &$q) {
    $q['inquiries'] = $inquiryMap[$q['inquiry_id']] ?? null;
    $q['profiles'] = $profileMap[$q['created_by']] ?? null;
  }
  unset($q); // Unset reference
}

// Fetch LPO status for each accepted quotation to determine if training can be scheduled
$lpoStatusMap = [];
foreach ($quotations as $q) {
  if (strtolower($q['status'] ?? '') === 'accepted') {
    $lpos = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/client_orders?quotation_id=eq.{$q['id']}&select=id,status&order=created_at.desc&limit=1",
        false,
        $ctx
      ),
      true
    ) ?: [];
    $lpoStatusMap[$q['id']] = !empty($lpos) ? strtolower($lpos[0]['status'] ?? '') : null;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Quotations</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Quotations</h2>
      <p class="muted">Manage training quotations and approvals</p>
    </div>
  </div>

  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <?php 
  // Debug: Show role and user info if no quotations found
  if (empty($quotations)): 
    // Try to fetch all quotations (without filters) to check if any exist
    $allQuotationsUrl = SUPABASE_URL . "/rest/v1/quotations?select=id,quotation_no,status,created_by,created_at,inquiry_id&order=created_at.desc&limit=20";
    $allQuotationsDebug = json_decode(
      @file_get_contents($allQuotationsUrl, false, $ctx),
      true
    ) ?: [];
  ?>
    <?php if (!empty($allQuotationsDebug)): ?>
      <div style="background: #fef3c7; color: #92400e; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
        <strong>⚠ Filter Issue Detected:</strong>
        <p style="margin: 8px 0 0 0;">
          Found <strong><?= count($allQuotationsDebug) ?></strong> quotation(s) in database, but none match your current filter.<br>
          <strong>Your Role:</strong> <?= htmlspecialchars($role ?? 'unknown') ?> | 
          <strong>Your User ID:</strong> <?= htmlspecialchars($userId ?? 'unknown') ?><br>
          <?php if ($role === 'bdo'): ?>
            <em style="color: #dc2626;">⚠ BDO role only sees quotations you created. The quotations in database were created by different users.</em><br>
            <strong>Solution:</strong> Quotations need to be created by you, or you need admin access to see all quotations.
          <?php elseif ($role === 'bdm'): ?>
            <em style="color: #dc2626;">⚠ BDM role only sees quotations with status: pending_approval, approved, accepted, rejected.</em><br>
            <strong>Solution:</strong> Quotations may be in 'draft' status. They need to be submitted for approval first.
          <?php else: ?>
            <em>This shouldn't happen for your role. Please check the query filters.</em>
          <?php endif; ?>
        </p>
        <?php if (isAdmin() || $role === 'admin'): ?>
          <details style="margin-top: 10px;">
            <summary style="cursor: pointer; font-weight: bold; color: #92400e;">Show All Quotations in Database (Debug)</summary>
            <table style="margin-top: 10px; width: 100%; font-size: 12px; border-collapse: collapse;">
              <thead>
                <tr style="background: #f3f4f6;">
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Quotation No</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Status</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Created By</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Inquiry ID</th>
                  <th style="padding: 8px; border: 1px solid #d1d5db;">Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allQuotationsDebug as $q): ?>
                  <tr>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= htmlspecialchars($q['quotation_no'] ?? '-') ?></td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;">
                      <span class="badge badge-<?= strtolower($q['status'] ?? 'draft') ?>">
                        <?= strtoupper($q['status'] ?? 'DRAFT') ?>
                      </span>
                    </td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;">
                      <?= htmlspecialchars($q['created_by'] ?? '-') ?>
                      <?php if ($q['created_by'] === $userId): ?>
                        <span style="color: #059669;">(You)</span>
                      <?php endif; ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= htmlspecialchars($q['inquiry_id'] ?? '-') ?></td>
                    <td style="padding: 8px; border: 1px solid #d1d5db;"><?= $q['created_at'] ? date('d M Y H:i', strtotime($q['created_at'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </details>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #dc2626;">
        <strong>❌ No Quotations Found:</strong>
        <p style="margin: 8px 0 0 0;">
          There are no quotations in the database at all.<br>
          <strong>Possible causes:</strong>
          <ul style="margin: 8px 0 0 20px;">
            <li>Quotations haven't been created yet - quotes need to be generated from inquiries first</li>
            <li>When accepting a quote from Inquiry module, the quotation record might not have been created</li>
            <li>Check if quotes were created via "Create Quote" button in Inquiries module</li>
          </ul>
          <strong>Solution:</strong> Go to Inquiries → Select an inquiry → Click "Quote" → Create the quote. This will create a quotation record.
        </p>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th>Quotation No</th>
        <th>Inquiry</th>
        <th>Course</th>
        <th>Subtotal</th>
        <th>VAT</th>
        <th>Total</th>
        <th>Status</th>
        <th>Created By</th>
        <th>Created</th>
        <th style="width: 60px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($quotations)): ?>
        <?php foreach ($quotations as $q): 
          // Handle both old format (inquiryMap) and new format (inquiries nested)
          $inquiry = $q['inquiries'] ?? ($inquiryMap[$q['inquiry_id']] ?? null);
          $status = strtolower($q['status'] ?? 'draft');
          $badgeClass = 'badge-info';
          if ($status === 'approved') $badgeClass = 'badge-success';
          elseif ($status === 'rejected') $badgeClass = 'badge-danger';
          elseif ($status === 'pending_approval') $badgeClass = 'badge-warning';
          elseif ($status === 'accepted') $badgeClass = 'badge-success';
          
          // Handle both old format (profiles nested) and new format
          $creatorName = $q['profiles']['full_name'] ?? '-';
        ?>
          <tr>
            <td><?= htmlspecialchars($q['quotation_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= number_format($q['subtotal'] ?? 0, 2) ?></td>
            <td><?= number_format($q['vat'] ?? 0, 2) ?></td>
            <td><strong><?= number_format($q['total'] ?? 0, 2) ?></strong></td>
            <td>
              <span class="badge <?= $badgeClass ?>">
                <?= strtoupper(str_replace('_', ' ', $status)) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($creatorName) ?></td>
            <td><?= $q['created_at'] ? date('d M Y', strtotime($q['created_at'])) : '-' ?></td>
            <td class="col-actions">
              <div class="action-menu-wrapper">
                <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">&#8942;</button>
                <div class="action-menu">
                  <?php if ($status === 'pending_approval' && ($role === 'bdm' || isAdmin())): ?>
                    <form method="post" action="../api/quotations/approve.php" style="margin: 0;">
                      <?php require '../includes/csrf.php'; echo csrfField(); ?>
                      <input type="hidden" name="quotation_id" value="<?= $q['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Approve</button>
                    </form>
                    <form method="post" action="../api/quotations/approve.php" style="margin: 0;">
                      <?php require '../includes/csrf.php'; echo csrfField(); ?>
                      <input type="hidden" name="quotation_id" value="<?= $q['id'] ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="danger" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Reject</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($status === 'approved'): ?>
                    <form method="post" action="../api/quotations/accept.php" style="margin: 0;">
                      <?php require '../includes/csrf.php'; echo csrfField(); ?>
                      <input type="hidden" name="quotation_id" value="<?= $q['id'] ?>">
                      <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 10px 16px; cursor: pointer;">Accept</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($status === 'accepted'): ?>
                    <?php
                      $lpoStatus = $lpoStatusMap[$q['id']] ?? null;
                      $hasLPO = $lpoStatus !== null;
                      $lpoVerified = $lpoStatus === 'verified';
                    ?>
                    <a href="client_orders.php?quotation_id=<?= $q['id'] ?>">
                      <?= $hasLPO ? 'View LPO' : 'Upload LPO' ?>
                    </a>
                    <?php if ($lpoVerified): ?>
                      <a href="schedule_training.php?quotation_id=<?= $q['id'] ?>">Schedule Training (Post-Quotation)</a>
                    <?php else: ?>
                      <span style="display: block; padding: 10px 16px; color: #6b7280; cursor: not-allowed; opacity: 0.6;" 
                            title="Training can be scheduled only after LPO verification">
                        Schedule Training (LPO Required)
                      </span>
                      <?php if (!$hasLPO): ?>
                        <span style="display: block; padding: 8px 16px; color: #dc2626; font-size: 12px; font-style: italic;">
                          ⚠ Upload and verify LPO first
                        </span>
                      <?php else: ?>
                        <span style="display: block; padding: 8px 16px; color: #dc2626; font-size: 12px; font-style: italic;">
                          ⚠ LPO verification pending
                        </span>
                      <?php endif; ?>
                    <?php endif; ?>
                  <?php endif; ?>
                  <a href="quotation_view.php?id=<?= $q['id'] ?>">View</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="10">No quotations found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

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

<?php include '../layout/footer.php'; ?>
</body>
</html>

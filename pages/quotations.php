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
$baseUrl = SUPABASE_URL . "/rest/v1/quotations?select=*,inquiries(client_id,course_name),profiles!quotations_created_by_fkey(full_name)";
if ($role === 'bdo') {
  $baseUrl .= "&created_by=eq.$userId";
} elseif ($role === 'bdm') {
  // BDM sees quotations pending approval and approved
  $baseUrl .= "&status=in.(pending_approval,approved,rejected)";
}

$baseUrl .= "&order=created_at.desc";

$quotations = json_decode(
  @file_get_contents($baseUrl, false, $ctx),
  true
) ?: [];

// Fetch inquiries for reference
$inquiries = json_decode(
  @file_get_contents(SUPABASE_URL . "/rest/v1/inquiries?select=id,client_id,course_name", false, $ctx),
  true
) ?: [];

$inquiryMap = [];
foreach ($inquiries as $inq) {
  $inquiryMap[$inq['id']] = $inq;
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
          $inquiry = $inquiryMap[$q['inquiry_id']] ?? null;
          $status = strtolower($q['status'] ?? 'draft');
          $badgeClass = 'badge-info';
          if ($status === 'approved') $badgeClass = 'badge-success';
          elseif ($status === 'rejected') $badgeClass = 'badge-danger';
          elseif ($status === 'pending_approval') $badgeClass = 'badge-warning';
          elseif ($status === 'accepted') $badgeClass = 'badge-success';
        ?>
          <tr>
            <td><?= htmlspecialchars($q['quotation_no']) ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
            <td><?= number_format($q['subtotal'], 2) ?></td>
            <td><?= number_format($q['vat'], 2) ?></td>
            <td><strong><?= number_format($q['total'], 2) ?></strong></td>
            <td>
              <span class="badge <?= $badgeClass ?>">
                <?= strtoupper(str_replace('_', ' ', $status)) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($q['profiles']['full_name'] ?? '-') ?></td>
            <td><?= date('d M Y', strtotime($q['created_at'])) ?></td>
            <td class="col-actions">
              <div class="action-menu-wrapper">
                <button type="button" class="btn-icon action-menu-toggle">&#8942;</button>
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

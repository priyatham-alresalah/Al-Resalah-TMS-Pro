<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('quotations', 'view');

$id = $_GET['id'] ?? '';
if (!$id) {
  header('Location: quotations.php?error=' . urlencode('Quotation ID missing'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch quotation */
$quotation = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/quotations?id=eq.$id&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$quotation) {
  header('Location: quotations.php?error=' . urlencode('Quotation not found'));
  exit;
}

/* Fetch inquiry */
$inquiry = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$quotation['inquiry_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* Fetch client */
$client = null;
if ($inquiry && !empty($inquiry['client_id'])) {
  $client = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
}

/* Fetch creator profile */
$creator = null;
if (!empty($quotation['created_by'])) {
  $creator = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/profiles?id=eq.{$quotation['created_by']}&select=id,full_name,email",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
}

/* Fetch LPO status if quotation is accepted */
$lpoStatus = null;
$lpoDetails = null;
if (strtolower($quotation['status'] ?? '') === 'accepted') {
  $lpos = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/client_orders?quotation_id=eq.{$quotation['id']}&select=*&order=created_at.desc&limit=1",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  if (!empty($lpos)) {
    $lpoDetails = $lpos[0];
    $lpoStatus = strtolower($lpoDetails['status'] ?? 'pending');
  }
}

$status = strtolower($quotation['status'] ?? 'draft');
$badgeClass = 'badge-info';
if ($status === 'approved') $badgeClass = 'badge-success';
elseif ($status === 'rejected') $badgeClass = 'badge-danger';
elseif ($status === 'pending_approval') $badgeClass = 'badge-warning';
elseif ($status === 'accepted') $badgeClass = 'badge-success';
?>
<!DOCTYPE html>
<html>
<head>
  <title>View Quotation</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Quotation Details</h2>
      <p class="muted">View quotation information and status</p>
    </div>
    <div class="actions">
      <a href="quotations.php" class="btn btn-sm btn-secondary">Back to Quotations</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>

  <div class="form-card" style="max-width: 900px;">
    <!-- Quotation Header -->
    <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
      <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
        <div>
          <h3 style="margin: 0 0 10px 0; color: #111827;"><?= htmlspecialchars($quotation['quotation_no']) ?></h3>
          <p style="color: #6b7280; margin: 0;">
            Created: <?= date('d M Y H:i', strtotime($quotation['created_at'])) ?>
          </p>
        </div>
        <div style="text-align: right;">
          <span class="badge <?= $badgeClass ?>" style="font-size: 14px; padding: 8px 16px;">
            <?= strtoupper(str_replace('_', ' ', $status)) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Client Information -->
    <?php if ($client): ?>
    <div style="margin-bottom: 25px;">
      <h4 style="margin-bottom: 15px; color: #374151;">Client Information</h4>
      <div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
        <p style="margin: 5px 0;"><strong>Company:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?></p>
        <?php if (!empty($client['email'])): ?>
          <p style="margin: 5px 0;"><strong>Email:</strong> <?= htmlspecialchars($client['email']) ?></p>
        <?php endif; ?>
        <?php if (!empty($client['phone'])): ?>
          <p style="margin: 5px 0;"><strong>Phone:</strong> <?= htmlspecialchars($client['phone']) ?></p>
        <?php endif; ?>
        <?php if (!empty($client['address'])): ?>
          <p style="margin: 5px 0;"><strong>Address:</strong> <?= htmlspecialchars($client['address']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Course Information -->
    <?php if ($inquiry): ?>
    <div style="margin-bottom: 25px;">
      <h4 style="margin-bottom: 15px; color: #374151;">Course Information</h4>
      <div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
        <p style="margin: 5px 0;"><strong>Course:</strong> <?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></p>
        <?php if (!empty($inquiry['id'])): ?>
          <p style="margin: 5px 0;">
            <strong>Inquiry ID:</strong> 
            <a href="inquiry_view.php?id=<?= htmlspecialchars($inquiry['id']) ?>" style="color: #2563eb; text-decoration: underline;">
              <?= htmlspecialchars($inquiry['id']) ?>
            </a>
          </p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Financial Details -->
    <div style="margin-bottom: 25px;">
      <h4 style="margin-bottom: 15px; color: #374151;">Financial Details</h4>
      <table class="table" style="margin: 0;">
        <tbody>
          <tr>
            <td style="font-weight: 600; width: 200px;">Subtotal:</td>
            <td style="text-align: right;"><?= number_format($quotation['subtotal'] ?? 0, 2) ?> AED</td>
          </tr>
          <?php if (!empty($quotation['discount']) && $quotation['discount'] > 0): ?>
          <tr>
            <td style="font-weight: 600;">Discount:</td>
            <td style="text-align: right; color: #059669;">- <?= number_format($quotation['discount'] ?? 0, 2) ?> AED</td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="font-weight: 600;">VAT (<?= number_format(($quotation['vat'] ?? 0) / ($quotation['subtotal'] ?? 1) * 100, 1) ?>%):</td>
            <td style="text-align: right;"><?= number_format($quotation['vat'] ?? 0, 2) ?> AED</td>
          </tr>
          <tr style="background: #f9fafb; font-size: 16px;">
            <td style="font-weight: 700;">Total:</td>
            <td style="text-align: right; font-weight: 700; color: #2563eb;"><?= number_format($quotation['total'] ?? 0, 2) ?> AED</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- LPO Status (if accepted) -->
    <?php if ($status === 'accepted' && $lpoDetails): ?>
    <div style="margin-bottom: 25px;">
      <h4 style="margin-bottom: 15px; color: #374151;">LPO Status</h4>
      <div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
        <p style="margin: 5px 0;">
          <strong>LPO Number:</strong> <?= htmlspecialchars($lpoDetails['lpo_number'] ?? '-') ?>
        </p>
        <p style="margin: 5px 0;">
          <strong>Status:</strong> 
          <span class="badge <?= $lpoStatus === 'verified' ? 'badge-success' : ($lpoStatus === 'rejected' ? 'badge-danger' : 'badge-warning') ?>">
            <?= strtoupper($lpoStatus ?? 'PENDING') ?>
          </span>
        </p>
        <?php if (!empty($lpoDetails['received_date'])): ?>
          <p style="margin: 5px 0;">
            <strong>Received Date:</strong> <?= date('d M Y', strtotime($lpoDetails['received_date'])) ?>
          </p>
        <?php endif; ?>
        <?php if (!empty($lpoDetails['verified_at'])): ?>
          <p style="margin: 5px 0;">
            <strong>Verified At:</strong> <?= date('d M Y H:i', strtotime($lpoDetails['verified_at'])) ?>
          </p>
        <?php endif; ?>
        <?php if (!empty($lpoDetails['lpo_file_path'])): ?>
          <p style="margin: 5px 0;">
            <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($lpoDetails['lpo_file_path']) ?>" target="_blank" class="btn btn-sm" style="margin-top: 10px;">
              View LPO Document
            </a>
          </p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Additional Information -->
    <div style="margin-bottom: 25px;">
      <h4 style="margin-bottom: 15px; color: #374151;">Additional Information</h4>
      <div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
        <?php if ($creator): ?>
          <p style="margin: 5px 0;"><strong>Created By:</strong> <?= htmlspecialchars($creator['full_name'] ?? '-') ?></p>
        <?php endif; ?>
        <?php if (!empty($quotation['accepted_at'])): ?>
          <p style="margin: 5px 0;"><strong>Accepted At:</strong> <?= date('d M Y H:i', strtotime($quotation['accepted_at'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($quotation['notes'])): ?>
          <p style="margin: 5px 0;"><strong>Notes:</strong> <?= htmlspecialchars($quotation['notes']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php if ($status === 'pending_approval' && (hasPermission('quotations', 'update') || isAdmin())): ?>
          <form method="post" action="../api/quotations/approve.php" style="margin: 0;">
            <?php require '../includes/csrf.php'; echo csrfField(); ?>
            <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-primary">Approve</button>
          </form>
          <form method="post" action="../api/quotations/approve.php" style="margin: 0;">
            <?php require '../includes/csrf.php'; echo csrfField(); ?>
            <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn" style="background: #dc2626; color: white;">Reject</button>
          </form>
        <?php endif; ?>
        
        <?php if ($status === 'approved'): ?>
          <form method="post" action="../api/quotations/accept.php" style="margin: 0;">
            <?php require '../includes/csrf.php'; echo csrfField(); ?>
            <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
            <button type="submit" class="btn btn-primary">Accept Quotation</button>
          </form>
        <?php endif; ?>
        
        <?php if ($status === 'accepted'): ?>
          <?php if ($lpoStatus === 'verified'): ?>
            <a href="schedule_training.php?quotation_id=<?= $quotation['id'] ?>" class="btn btn-primary">
              Schedule Training
            </a>
          <?php else: ?>
            <a href="client_orders.php?quotation_id=<?= $quotation['id'] ?>" class="btn btn-primary">
              <?= $lpoStatus ? 'View LPO' : 'Upload LPO' ?>
            </a>
            <?php if ($lpoStatus && $lpoStatus !== 'verified'): ?>
              <span style="display: inline-block; padding: 8px 16px; color: #dc2626; font-size: 14px;">
                ⚠ LPO must be verified before scheduling training
              </span>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>

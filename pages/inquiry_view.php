<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('inquiries', 'view');

$id = $_GET['id'] ?? '';
if (!$id) die('Inquiry ID missing');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch inquiry */
$inquiry = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$id&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$inquiry) die('Inquiry not found');

/* Fetch client */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

$status = strtolower($inquiry['status'] ?? 'new');
?>
<!DOCTYPE html>
<html>
<head>
  <title>View Inquiry</title>
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
      <h2>Inquiry Details</h2>
      <p class="muted">View and manage inquiry</p>
    </div>
    <div class="actions">
      <a href="inquiries.php" class="btn btn-sm btn-secondary">Back to Inquiries</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>

  <div class="form-card" style="max-width: 800px;">
    <div style="margin-bottom: 20px;">
      <strong>Client:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?><br>
      <strong>Email:</strong> <?= htmlspecialchars($client['email'] ?? '-') ?><br>
      <strong>Course:</strong> <?= htmlspecialchars($inquiry['course_name']) ?><br>
      <strong>Status:</strong> 
      <?php
        $badgeClass = 'badge-info';
        if ($status === 'quoted') $badgeClass = 'badge-warning';
        elseif ($status === 'closed') $badgeClass = 'badge-success';
        // Note: 'accepted' and 'rejected' are not valid statuses - use 'closed' instead
      ?>
      <span class="badge <?= $badgeClass ?>"><?= strtoupper($status) ?></span><br>
      <?php if (!empty($inquiry['quote_no'])): ?>
        <strong>Quote No:</strong> <?= htmlspecialchars($inquiry['quote_no']) ?><br>
        <strong>Quote Amount:</strong> <?= number_format($inquiry['quote_total'] ?? 0, 2) ?><br>
      <?php endif; ?>
      <strong>Created:</strong> <?= date('d M Y H:i', strtotime($inquiry['created_at'])) ?>
    </div>

    <?php if ($status === 'quoted'): ?>
      <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 20px;">
        <h3>Client Response</h3>
        <form method="post" action="<?= BASE_PATH ?>/api/inquiries/respond_quote.php">
          <?= csrfField() ?>
          <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
          
          <div class="form-group">
            <label>Action *</label>
            <select name="action" required>
              <option value="">Select Action</option>
              <option value="accept">Accept Quote</option>
              <option value="reject">Reject Quote</option>
              <option value="requote">Request Requote</option>
            </select>
          </div>

          <div class="form-group">
            <label>Reason/Comments</label>
            <textarea name="reason" rows="4" placeholder="Enter reason for acceptance, rejection, or requote request" style="width: 100%; padding: 8px;"></textarea>
          </div>

          <div class="form-actions">
            <button class="btn" type="submit">Submit Response</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($status === 'closed'): ?>
      <?php
      // Check if there's a quotation with response info
      $quotation = null;
      if (!empty($inquiry['id'])) {
        $quoteCtx = stream_context_create([
          'http' => [
            'method' => 'GET',
            'header' =>
              "apikey: " . SUPABASE_SERVICE . "\r\n" .
              "Authorization: Bearer " . SUPABASE_SERVICE
          ]
        ]);
        $quotations = json_decode(
          @file_get_contents(
            SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.{$inquiry['id']}&select=*&order=created_at.desc&limit=1",
            false,
            $quoteCtx
          ),
          true
        ) ?: [];
        $quotation = $quotations[0] ?? null;
      }
      
      // Determine if it was accepted or rejected based on context
      // For now, show generic closed message - can be enhanced later
      ?>
      <div style="background: #e0f2fe; padding: 15px; border-radius: 6px; margin-top: 20px;">
        <strong>Inquiry Closed</strong>
        <p style="margin-top: 10px; color: #666;">This inquiry has been closed. To schedule training, please go to the Quotations module and ensure the quotation is accepted and LPO is verified.</p>
        <div style="margin-top: 15px;">
          <a href="quotations.php" class="btn">View Quotations</a>
          <a href="inquiry_create.php" class="btn btn-secondary" style="margin-left: 10px;">Create New Inquiry</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




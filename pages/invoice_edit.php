<?php
require '../includes/config.php';
require '../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (!$id) {
  header('Location: invoices.php?error=' . urlencode('Invoice ID missing'));
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

/* FETCH INVOICE */
$invoice = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?id=eq.$id&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$invoice) {
  header('Location: invoices.php?error=' . urlencode('Invoice not found'));
  exit;
}

/* FETCH CLIENT */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$invoice['client_id']}&limit=1",
    false,
    $ctx
  ),
  true
)[0] ?? null;

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Invoice</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Edit Invoice</h2>
      <p class="muted">Update invoice details</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" action="../api/invoices/update.php" id="invoiceForm">
      <input type="hidden" name="id" value="<?= $invoice['id'] ?>">
      
      <div class="form-group">
        <label>Invoice Number</label>
        <input type="text" name="invoice_no" value="<?= htmlspecialchars($invoice['invoice_no']) ?>" required>
      </div>

      <div class="form-group">
        <label>Client</label>
        <input type="text" value="<?= htmlspecialchars($client['company_name'] ?? '-') ?>" disabled>
        <small style="color: #6b7280;">Cannot be changed</small>
      </div>

      <div class="form-group">
        <label>Amount</label>
        <input type="number" name="amount" id="amount" step="0.01" value="<?= htmlspecialchars($invoice['amount']) ?>" required>
      </div>

      <div class="form-group">
        <label>VAT (%)</label>
        <input type="number" name="vat" id="vat" step="0.01" value="<?= htmlspecialchars($invoice['vat']) ?>" required>
      </div>

      <div class="form-group">
        <label>Total</label>
        <input type="number" name="total" id="total" step="0.01" value="<?= htmlspecialchars($invoice['total']) ?>" readonly>
        <small style="color: #6b7280;">Calculated automatically</small>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="draft" <?= ($invoice['status'] ?? 'issued') === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="issued" <?= ($invoice['status'] ?? 'issued') === 'issued' ? 'selected' : '' ?>>Issued</option>
          <option value="paid" <?= ($invoice['status'] ?? 'issued') === 'paid' ? 'selected' : '' ?>>Paid</option>
          <option value="cancelled" <?= ($invoice['status'] ?? 'issued') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>

      <div class="form-group">
        <label>Issued Date</label>
        <input type="date" name="issued_date" value="<?= htmlspecialchars($invoice['issued_date'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Due Date</label>
        <input type="date" name="due_date" value="<?= htmlspecialchars($invoice['due_date'] ?? '') ?>">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Update Invoice</button>
        <a href="invoices.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<script>
  document.getElementById('invoiceForm').addEventListener('input', function(e) {
    if (e.target.id === 'amount' || e.target.id === 'vat') {
      const amount = parseFloat(document.getElementById('amount').value) || 0;
      const vatPercent = parseFloat(document.getElementById('vat').value) || 0;
      const vatAmount = amount * vatPercent / 100;
      document.getElementById('total').value = (amount + vatAmount).toFixed(2);
    }
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




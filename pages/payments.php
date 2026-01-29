<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* Permission Check */
if (!canAccessModule('payments')) {
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

// Fetch payments with allocations
$payments = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/payments?select=*,invoices(invoice_no,client_id,clients(company_name)),payment_allocations(invoice_id,allocated_amount,invoices(invoice_no))&order=paid_on.desc,created_at.desc",
    false,
    $ctx
  ),
  true
) ?: [];

// Fetch unpaid invoices for creating new payments
$unpaidInvoices = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?status=in.(unpaid,overdue)&select=id,invoice_no,total,client_id,clients(company_name),trainings(course_name)&order=issued_date.desc",
    false,
    $ctx
  ),
  true
) ?: [];

// Calculate allocated amounts for each invoice
$invoiceAllocations = [];
foreach ($unpaidInvoices as $inv) {
  $allocations = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/payment_allocations?invoice_id=eq.{$inv['id']}&select=allocated_amount",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  $totalAllocated = 0;
  foreach ($allocations as $alloc) {
    $totalAllocated += floatval($alloc['allocated_amount']);
  }
  
  $invoiceAllocations[$inv['id']] = [
    'allocated' => $totalAllocated,
    'remaining' => floatval($inv['total']) - $totalAllocated
  ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Payments</title>
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
      <h2>Payments</h2>
      <p class="muted">Record payments and allocate to invoices</p>
    </div>
    <?php if (hasPermission('payments', 'create')): ?>
      <button type="button" class="btn btn-primary" onclick="document.getElementById('paymentModal').style.display='block'">
        + Record Payment
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
        <th>Payment Date</th>
        <th>Reference No</th>
        <th>Payment Mode</th>
        <th>Amount</th>
        <th>Primary Invoice</th>
        <th>Allocated To</th>
        <th>Created</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($payments)): ?>
        <?php foreach ($payments as $payment): 
          $invoice = $payment['invoices'] ?? null;
          $allocations = $payment['payment_allocations'] ?? [];
        ?>
          <tr>
            <td><?= $payment['paid_on'] ? date('d M Y', strtotime($payment['paid_on'])) : '-' ?></td>
            <td><?= htmlspecialchars($payment['reference_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($payment['payment_mode'] ?? '-') ?></td>
            <td><strong><?= number_format($payment['amount'], 2) ?></strong></td>
            <td>
              <?php if ($invoice): ?>
                <?= htmlspecialchars($invoice['invoice_no']) ?><br>
                <small class="muted"><?= htmlspecialchars($invoice['clients']['company_name'] ?? '-') ?></small>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($allocations)): ?>
                <ul style="margin:0; padding-left:20px;">
                  <?php foreach ($allocations as $alloc): ?>
                    <li>
                      <?= htmlspecialchars($alloc['invoices']['invoice_no'] ?? '-') ?>: 
                      <?= number_format($alloc['allocated_amount'], 2) ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><?= date('d M Y H:i', strtotime($payment['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7">No payments found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

<!-- Record Payment Modal -->
<?php if (hasPermission('payments', 'create')): ?>
<div id="paymentModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y:auto;">
  <div style="background:white; margin:5% auto; padding:20px; border-radius:8px; max-width:700px;">
    <h3>Record Payment</h3>
    <form method="post" action="../api/payments/create.php" id="paymentForm">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      
      <div style="margin-bottom:15px;">
        <label>Payment Mode *</label>
        <select name="payment_mode" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
          <option value="">Select Payment Mode</option>
          <option value="Cash">Cash</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="Cheque">Cheque</option>
          <option value="Credit Card">Credit Card</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div style="margin-bottom:15px;">
        <label>Reference Number</label>
        <input type="text" name="reference_no" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="Cheque/Transfer reference">
      </div>

      <div style="margin-bottom:15px;">
        <label>Payment Date *</label>
        <input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
      </div>

      <div style="margin-bottom:15px;">
        <label>Total Payment Amount *</label>
        <input type="number" name="total_amount" id="total_amount" step="0.01" min="0.01" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="0.00">
      </div>

      <div style="margin-bottom:15px;">
        <label>Allocate to Invoices *</label>
        <div id="invoiceAllocations" style="border:1px solid #ddd; border-radius:4px; padding:10px; max-height:300px; overflow-y:auto;">
          <?php if (!empty($unpaidInvoices)): ?>
            <?php foreach ($unpaidInvoices as $inv): 
              $remaining = $invoiceAllocations[$inv['id']]['remaining'] ?? floatval($inv['total']);
              $client = $inv['clients'] ?? null;
              $training = $inv['trainings'] ?? null;
            ?>
              <div style="margin-bottom:10px; padding:10px; background:#f9fafb; border-radius:4px;">
                <label style="display:flex; align-items:center; cursor:pointer;">
                  <input type="checkbox" name="invoice_ids[]" value="<?= $inv['id'] ?>" class="invoice-checkbox" style="margin-right:10px;" onchange="updateAllocation(this)">
                  <div style="flex:1;">
                    <strong><?= htmlspecialchars($inv['invoice_no']) ?></strong><br>
                    <small class="muted">
                      <?= htmlspecialchars($client['company_name'] ?? '-') ?> | 
                      Total: <?= number_format($inv['total'], 2) ?> | 
                      Remaining: <span class="remaining-<?= $inv['id'] ?>"><?= number_format($remaining, 2) ?></span>
                    </small>
                  </div>
                </label>
                <div class="allocation-input" style="display:none; margin-top:8px;">
                  <input type="number" name="amounts[]" step="0.01" min="0.01" max="<?= $remaining ?>" data-invoice-id="<?= $inv['id'] ?>" style="width:100%; padding:6px; border:1px solid #ddd; border-radius:4px;" placeholder="Amount to allocate" onchange="validateTotal()">
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="muted">No unpaid invoices found</p>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
        <button type="button" onclick="document.getElementById('paymentModal').style.display='none'" class="btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Record Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateAllocation(checkbox) {
  const input = checkbox.closest('div').querySelector('.allocation-input');
  const amountInput = input.querySelector('input[type="number"]');
  
  if (checkbox.checked) {
    input.style.display = 'block';
    amountInput.required = true;
  } else {
    input.style.display = 'none';
    amountInput.required = false;
    amountInput.value = '';
    validateTotal();
  }
}

function validateTotal() {
  const totalInput = document.getElementById('total_amount');
  const amountInputs = document.querySelectorAll('.allocation-input input[type="number"]');
  
  let allocatedTotal = 0;
  amountInputs.forEach(input => {
    if (input.value) {
      allocatedTotal += parseFloat(input.value) || 0;
    }
  });
  
  // Update total if allocations are entered
  if (allocatedTotal > 0 && !totalInput.value) {
    totalInput.value = allocatedTotal.toFixed(2);
  }
}

// Validate form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
  const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
  const amountInputs = document.querySelectorAll('.allocation-input input[type="number"]');
  
  let allocatedTotal = 0;
  amountInputs.forEach(input => {
    if (input.value) {
      allocatedTotal += parseFloat(input.value) || 0;
    }
  });
  
  if (Math.abs(allocatedTotal - totalAmount) > 0.01) {
    e.preventDefault();
    alert('Allocation amounts must match total payment amount');
    return false;
  }
  
  const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
  if (checkedBoxes.length === 0) {
    e.preventDefault();
    alert('Please select at least one invoice');
    return false;
  }
});
</script>
<?php endif; ?>

<script>
  // Close modal when clicking outside
  window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target == modal) {
      modal.style.display = "none";
    }
  }
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>

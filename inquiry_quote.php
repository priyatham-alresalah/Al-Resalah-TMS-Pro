<?php
require 'includes/config.php';
require 'includes/auth_check.php';

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

/* Group inquiries by client to show all courses for this client */
$allInquiries = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&status=eq.new&order=course_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Quote Inquiry</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Quote Inquiry</h2>
      <p class="muted">Create quote for <?= htmlspecialchars($client['company_name'] ?? 'Client') ?></p>
    </div>
    <div class="actions">
      <a href="inquiries.php" class="btn btn-sm btn-secondary">Back to Inquiries</a>
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
  
  <?php if (empty($allInquiries)): ?>
    <div style="background: #fef3c7; color: #92400e; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      No pending inquiries found for this client.
    </div>
    <div class="form-actions">
      <a href="inquiries.php" class="btn">Back to Inquiries</a>
    </div>
  <?php else: ?>

  <div class="form-card" style="max-width: 100%;">
    <form method="post" action="api/inquiries/create_quote.php">
      <input type="hidden" name="client_id" value="<?= $inquiry['client_id'] ?>">
      
      <div class="form-group">
        <label><strong>Client:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?></label>
      </div>

      <div class="form-group">
        <label><strong>Contact Email:</strong> <?= htmlspecialchars($client['email'] ?? '-') ?></label>
      </div>

      <div class="form-group">
        <label>Select Courses to Quote *</label>
        <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 15px; background: #f9fafb;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid #e5e7eb;">
                <th style="padding: 10px; text-align: left; width: 40px;">
                  <input type="checkbox" id="select_all" onchange="toggleAll()">
                </th>
                <th style="padding: 10px; text-align: left;">Course Name</th>
                <th style="padding: 10px; text-align: left; width: 150px;">Amount</th>
                <th style="padding: 10px; text-align: left; width: 100px;">VAT %</th>
                <th style="padding: 10px; text-align: left; width: 150px;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allInquiries as $inq): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                  <td style="padding: 10px;">
                    <input type="checkbox" name="inquiry_ids[]" value="<?= $inq['id'] ?>" 
                           class="course-check" onchange="updateTotal()" 
                           data-course="<?= htmlspecialchars($inq['course_name']) ?>">
                  </td>
                  <td style="padding: 10px;">
                    <?= htmlspecialchars($inq['course_name']) ?>
                  </td>
                  <td style="padding: 10px;">
                    <input type="number" name="amount[<?= $inq['id'] ?>]" 
                           step="0.01" min="0" placeholder="0.00" 
                           class="amount-input" oninput="calculateRow(<?= $inq['id'] ?>)" 
                           style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;">
                  </td>
                  <td style="padding: 10px;">
                    <input type="number" name="vat[<?= $inq['id'] ?>]" 
                           step="0.01" min="0" max="100" value="5" 
                           class="vat-input" oninput="calculateRow(<?= $inq['id'] ?>)" 
                           style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;">
                  </td>
                  <td style="padding: 10px;">
                    <span class="total-display" id="total_<?= $inq['id'] ?>">0.00</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="border-top: 2px solid #e5e7eb; font-weight: bold;">
                <td colspan="4" style="padding: 10px; text-align: right;">Grand Total:</td>
                <td style="padding: 10px;">
                  <span id="grand_total">0.00</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="form-group">
        <label>Quote Notes (Optional)</label>
        <textarea name="notes" rows="4" placeholder="Additional notes or terms for this quote" style="width: 100%; padding: 8px;"></textarea>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Create Quote</button>
        <a href="inquiries.php">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
</main>

<script>
  function toggleAll() {
    const selectAll = document.getElementById('select_all');
    const checkboxes = document.querySelectorAll('.course-check');
    checkboxes.forEach(cb => {
      cb.checked = selectAll.checked;
    });
    updateTotal();
  }

  function calculateRow(id) {
    const amount = parseFloat(document.querySelector(`input[name="amount[${id}]"]`).value) || 0;
    const vat = parseFloat(document.querySelector(`input[name="vat[${id}]"]`).value) || 0;
    const total = amount + (amount * vat / 100);
    document.getElementById(`total_${id}`).textContent = total.toFixed(2);
    updateGrandTotal();
  }

  function updateGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.course-check:checked').forEach(cb => {
      const id = cb.value;
      const total = parseFloat(document.getElementById(`total_${id}`).textContent) || 0;
      grandTotal += total;
    });
    document.getElementById('grand_total').textContent = grandTotal.toFixed(2);
  }

  function updateTotal() {
    updateGrandTotal();
  }

  document.querySelector('form')?.addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('.course-check:checked');
    if (selected.length === 0) {
      e.preventDefault();
      alert('Please select at least one course to quote');
      return false;
    }
    
    // Validate amounts
    let hasError = false;
    selected.forEach(cb => {
      const id = cb.value;
      const amount = document.querySelector(`input[name="amount[${id}]"]`).value;
      if (!amount || parseFloat(amount) <= 0) {
        hasError = true;
      }
    });
    
    if (hasError) {
      e.preventDefault();
      alert('Please enter valid amounts for all selected courses');
      return false;
    }
  });
</script>

<?php include 'layout/footer.php'; ?>
</body>
</html>

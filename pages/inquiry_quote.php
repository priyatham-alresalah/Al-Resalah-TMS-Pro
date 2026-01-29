<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('quotations', 'create');

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
      <form method="post" action="<?= BASE_PATH ?>/api/inquiries/create_quote.php">
      <?= csrfField() ?>
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
                <th style="padding: 10px; text-align: left; width: 120px;">No. of Candidates</th>
                <th style="padding: 10px; text-align: left; width: 150px;">Amount (per candidate)</th>
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
                    <input type="number" name="candidates[<?= $inq['id'] ?>]" 
                           id="candidates_<?= $inq['id'] ?>"
                           min="1" max="10000" placeholder="1" 
                           class="candidates-input" 
                           oninput="calculateRow('<?= $inq['id'] ?>')" 
                           onchange="calculateRow('<?= $inq['id'] ?>')"
                           style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;"
                           value="1">
                  </td>
                  <td style="padding: 10px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                      <input type="number" name="amount[<?= $inq['id'] ?>]" 
                             id="amount_<?= $inq['id'] ?>"
                             step="0.01" min="0" placeholder="0.00" 
                             class="amount-input" 
                             oninput="calculateRow('<?= $inq['id'] ?>')" 
                             onchange="calculateRow('<?= $inq['id'] ?>')"
                             style="flex: 1; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;"
                             value="100">
                      <span style="color: #6b7280; font-size: 12px;">AED</span>
                    </div>
                  </td>
                  <td style="padding: 10px;">
                    <input type="number" name="vat[<?= $inq['id'] ?>]" 
                           id="vat_<?= $inq['id'] ?>"
                           step="0.01" min="0" max="100" value="5" 
                           class="vat-input" 
                           oninput="calculateRow('<?= $inq['id'] ?>')" 
                           onchange="calculateRow('<?= $inq['id'] ?>')"
                           style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;">
                  </td>
                  <td style="padding: 10px;">
                    <span class="total-display" id="total_<?= $inq['id'] ?>" style="font-weight: 600;">0.00 AED</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="border-top: 2px solid #e5e7eb; font-weight: bold;">
                <td colspan="5" style="padding: 10px; text-align: right;">Grand Total:</td>
                <td style="padding: 10px;">
                  <span id="grand_total" style="font-weight: 600;">0.00 AED</span>
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
        <a href="inquiries.php" class="btn-cancel">Cancel</a>
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
    try {
      if (!id) {
        return;
      }
      
      // Use IDs for more reliable element selection
      const candidatesInput = document.getElementById('candidates_' + id);
      const amountInput = document.getElementById('amount_' + id);
      const vatInput = document.getElementById('vat_' + id);
      const totalElement = document.getElementById('total_' + id);
      
      if (!candidatesInput || !amountInput || !vatInput || !totalElement) {
        return;
      }
      
      // Get values and ensure they're numbers
      const candidates = parseFloat(candidatesInput.value) || 0;
      const amount = parseFloat(amountInput.value) || 0;
      const vat = parseFloat(vatInput.value) || 0;
      
      // Calculate: (amount per candidate * number of candidates) * (1 + VAT%)
      // Example: 100 AED * 7 candidates * 1.05 = 735.00 AED
      const subtotal = amount * candidates;
      const total = subtotal * (1 + vat / 100);
      
      // Update the display
      totalElement.textContent = total.toFixed(2) + ' AED';
      
      // Update grand total
      updateGrandTotal();
    } catch (error) {
      console.error('Error in calculateRow for id ' + id + ':', error);
    }
  }

  function updateGrandTotal() {
    let grandTotal = 0;
    // Sum only checked courses for grand total
    const checkedBoxes = document.querySelectorAll('.course-check:checked');
    
    checkedBoxes.forEach(cb => {
      const id = cb.value;
      const totalElement = document.getElementById(`total_${id}`);
      if (totalElement) {
        const totalText = totalElement.textContent || '0.00 AED';
        // Remove 'AED' and any whitespace, then parse
        const total = parseFloat(totalText.replace(/AED/gi, '').trim()) || 0;
        grandTotal += total;
      }
    });
    
    const grandTotalElement = document.getElementById('grand_total');
    if (grandTotalElement) {
      grandTotalElement.textContent = grandTotal.toFixed(2) + ' AED';
    }
  }

  function updateTotal() {
    // Recalculate all rows when checkbox state changes
    document.querySelectorAll('.course-check').forEach(cb => {
      const id = cb.value;
      calculateRow(id);
    });
  }

  // Initialize calculations on page load
  function initCalculations() {
    // Find all candidate input fields by ID and calculate their rows
    const candidateInputs = document.querySelectorAll('input[id^="candidates_"]');
    candidateInputs.forEach(input => {
      // Extract ID from id attribute like "candidates_123-456-789"
      const id = input.id.replace('candidates_', '');
      if (id) {
        calculateRow(id);
      }
    });
  }

  // Run calculations when page loads
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(initCalculations, 50);
    });
  } else {
    setTimeout(initCalculations, 50);
  }
  
  // Fallback - run again after page fully loads
  window.addEventListener('load', function() {
    setTimeout(initCalculations, 100);
  });

  document.querySelector('form')?.addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('.course-check:checked');
    if (selected.length === 0) {
      e.preventDefault();
      alert('Please select at least one course to quote');
      return false;
    }
    
    // Validate amounts and candidates
    let hasError = false;
    selected.forEach(cb => {
      const id = cb.value;
      const amount = document.querySelector(`input[name="amount[${id}]"]`).value;
      const candidates = document.querySelector(`input[name="candidates[${id}]"]`).value;
      
      if (!amount || parseFloat(amount) <= 0) {
        hasError = true;
      }
      if (!candidates || parseInt(candidates) < 1) {
        hasError = true;
      }
    });
    
    if (hasError) {
      e.preventDefault();
      alert('Please enter valid amounts and number of candidates (minimum 1) for all selected courses');
      return false;
    }
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




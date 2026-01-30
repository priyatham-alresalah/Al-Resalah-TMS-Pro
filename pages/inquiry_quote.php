<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('quotations', 'create');

$idParam = $_GET['id'] ?? '';
$idsParam = trim($_GET['ids'] ?? '');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$selectedInquiries = [];
$inquiry = null;

if ($idsParam !== '') {
  /* Batch quote: multiple inquiry IDs (comma-separated) */
  $requestedIds = array_filter(array_map('trim', explode(',', $idsParam)));
  if (empty($requestedIds)) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('No inquiry IDs provided'));
    exit;
  }
  /* Fetch all requested inquiries (Supabase: id=in.(id1,id2,...)) */
  $idsFilter = implode(',', $requestedIds);
  $inquiriesResponse = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=in.($idsFilter)&select=*&order=course_name.asc",
      false,
      $ctx
    ),
    true
  );
  if (empty($inquiriesResponse)) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Inquiries not found'));
    exit;
  }
  /* Use only same client and status=new so one quote covers the batch */
  $firstClientId = $inquiriesResponse[0]['client_id'] ?? null;
  $selectedInquiries = [];
  foreach ($inquiriesResponse as $inv) {
    if (($inv['client_id'] ?? null) === $firstClientId && strtolower($inv['status'] ?? '') === 'new') {
      $selectedInquiries[] = $inv;
    }
  }
  if (empty($selectedInquiries)) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('No quotable inquiries in this batch (same client, status NEW)'));
    exit;
  }
  $inquiry = $selectedInquiries[0];
} else {
  /* Single inquiry */
  if (!$idParam) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Inquiry ID missing'));
    exit;
  }
  $inquiry = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$idParam&select=*",
      false,
      $ctx
    ),
    true
  )[0] ?? null;
  if (!$inquiry) {
    header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Inquiry not found'));
    exit;
  }
  $selectedInquiries = [$inquiry];
}

/* Fetch client */
$client = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$client) {
  header('Location: ' . BASE_PATH . '/pages/inquiries.php?error=' . urlencode('Client not found'));
  exit;
}

/* Available inquiries: same client, status=new, not already in selected */
$selectedIds = array_column($selectedInquiries, 'id');
$excludeFilter = count($selectedIds) === 1
  ? "id=neq.{$inquiry['id']}"
  : "id=not.in.(" . implode(',', $selectedIds) . ")";
$availableInquiries = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&status=eq.new&$excludeFilter&order=course_name.asc",
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

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  
  <div class="form-card" style="max-width: 100%;">
      <form method="post" action="<?= BASE_PATH ?>/api/inquiries/create_quote.php" id="quoteForm">
      <?= csrfField() ?>
      <input type="hidden" name="client_id" value="<?= $inquiry['client_id'] ?>" id="client_id">
      
      <div class="form-group">
        <label><strong>Client:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?></label>
      </div>

      <div class="form-group">
        <label><strong>Contact Email:</strong> <?= htmlspecialchars($client['email'] ?? '-') ?></label>
      </div>

      <div class="form-group">
        <label>Selected Courses to Quote *</label>
        <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 15px; background: #f9fafb;">
          <table style="width: 100%; border-collapse: collapse;" id="coursesTable">
            <thead>
              <tr style="border-bottom: 2px solid #e5e7eb;">
                <th style="padding: 10px; text-align: left; width: 40px;">Remove</th>
                <th style="padding: 10px; text-align: left;">Course Name</th>
                <th style="padding: 10px; text-align: left; width: 120px;">No. of Candidates</th>
                <th style="padding: 10px; text-align: left; width: 150px;">Amount (per candidate)</th>
                <th style="padding: 10px; text-align: left; width: 100px;">VAT %</th>
                <th style="padding: 10px; text-align: left; width: 150px;">Total</th>
              </tr>
            </thead>
            <tbody id="coursesTableBody">
              <?php foreach ($selectedInquiries as $inq): ?>
                <tr data-inquiry-id="<?= $inq['id'] ?>" style="border-bottom: 1px solid #e5e7eb;">
                  <td style="padding: 10px;">
                    <button type="button" class="btn-remove-course" onclick="removeCourse('<?= $inq['id'] ?>')" 
                            style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                      ×
                    </button>
                  </td>
                  <td style="padding: 10px;">
                    <input type="hidden" name="inquiry_ids[]" value="<?= $inq['id'] ?>">
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

      <?php if (!empty($availableInquiries)): ?>
      <div class="form-group">
        <label>Add More Courses (Optional)</label>
        <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 15px; background: #f0f9ff;">
          <select id="addInquirySelect" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 10px;">
            <option value="">-- Select a course to add --</option>
            <?php foreach ($availableInquiries as $availInq): ?>
              <option value="<?= $availInq['id'] ?>" data-course="<?= htmlspecialchars($availInq['course_name']) ?>">
                <?= htmlspecialchars($availInq['course_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" onclick="addSelectedCourse()" class="btn" style="width: 100%;">
            + Add Selected Course
          </button>
        </div>
      </div>
      <?php endif; ?>

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
</main>

<script>
  // Store available inquiries data
  const availableInquiries = <?= json_encode($availableInquiries) ?>;
  const selectedInquiryIds = new Set([<?= json_encode($inquiry['id']) ?>]);

  function removeCourse(inquiryId) {
    const row = document.querySelector(`tr[data-inquiry-id="${inquiryId}"]`);
    if (row) {
      row.remove();
      selectedInquiryIds.delete(inquiryId);
      updateGrandTotal();
      
      // Re-enable in dropdown if it was there
      const option = document.querySelector(`#addInquirySelect option[value="${inquiryId}"]`);
      if (option) {
        option.disabled = false;
      }
    }
  }

  function addSelectedCourse() {
    const select = document.getElementById('addInquirySelect');
    if (!select) return;
    
    const inquiryId = select.value;
    
    if (!inquiryId) {
      alert('Please select a course to add');
      return;
    }
    
    if (selectedInquiryIds.has(inquiryId)) {
      alert('This course is already added');
      return;
    }
    
    const option = select.options[select.selectedIndex];
    const courseName = option.getAttribute('data-course');
    
    // Find the inquiry data
    const inquiry = availableInquiries.find(i => i.id === inquiryId);
    if (!inquiry) {
      alert('Course data not found');
      return;
    }
    
    // Add to selected set
    selectedInquiryIds.add(inquiryId);
    
    // Add row to table
    const tbody = document.getElementById('coursesTableBody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-inquiry-id', inquiryId);
    newRow.style.borderBottom = '1px solid #e5e7eb';
    newRow.innerHTML = `
      <td style="padding: 10px;">
        <button type="button" class="btn-remove-course" onclick="removeCourse('${inquiryId}')" 
                style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">
          ×
        </button>
      </td>
      <td style="padding: 10px;">
        <input type="hidden" name="inquiry_ids[]" value="${inquiryId}">
        ${courseName}
      </td>
      <td style="padding: 10px;">
        <input type="number" name="candidates[${inquiryId}]" 
               id="candidates_${inquiryId}"
               min="1" max="10000" placeholder="1" 
               class="candidates-input" 
               oninput="calculateRow('${inquiryId}')" 
               onchange="calculateRow('${inquiryId}')"
               style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;"
               value="1">
      </td>
      <td style="padding: 10px;">
        <div style="display: flex; align-items: center; gap: 5px;">
          <input type="number" name="amount[${inquiryId}]" 
                 id="amount_${inquiryId}"
                 step="0.01" min="0" placeholder="0.00" 
                 class="amount-input" 
                 oninput="calculateRow('${inquiryId}')" 
                 onchange="calculateRow('${inquiryId}')"
                 style="flex: 1; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;"
                 value="100">
          <span style="color: #6b7280; font-size: 12px;">AED</span>
        </div>
      </td>
      <td style="padding: 10px;">
        <input type="number" name="vat[${inquiryId}]" 
               id="vat_${inquiryId}"
               step="0.01" min="0" max="100" value="5" 
               class="vat-input" 
               oninput="calculateRow('${inquiryId}')" 
               onchange="calculateRow('${inquiryId}')"
               style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;">
      </td>
      <td style="padding: 10px;">
        <span class="total-display" id="total_${inquiryId}" style="font-weight: 600;">0.00 AED</span>
      </td>
    `;
    tbody.appendChild(newRow);
    
    // Disable option in dropdown
    option.disabled = true;
    select.value = '';
    
    // Calculate the new row
    setTimeout(() => calculateRow(inquiryId), 100);
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
    // Sum all courses in the table
    document.querySelectorAll('tr[data-inquiry-id]').forEach(row => {
      const inquiryId = row.getAttribute('data-inquiry-id');
      if (inquiryId) {
        const totalElement = document.getElementById(`total_${inquiryId}`);
        if (totalElement) {
          const totalText = totalElement.textContent || '0.00 AED';
          // Remove 'AED' and any whitespace, then parse
          const total = parseFloat(totalText.replace(/AED/gi, '').trim()) || 0;
          grandTotal += total;
        }
      }
    });
    
    const grandTotalElement = document.getElementById('grand_total');
    if (grandTotalElement) {
      grandTotalElement.textContent = grandTotal.toFixed(2) + ' AED';
    }
  }

  function updateTotal() {
    // Recalculate all rows
    document.querySelectorAll('tr[data-inquiry-id]').forEach(row => {
      const inquiryId = row.getAttribute('data-inquiry-id');
      if (inquiryId) {
        calculateRow(inquiryId);
      }
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
    const selectedRows = document.querySelectorAll('tr[data-inquiry-id]');
    if (selectedRows.length === 0) {
      e.preventDefault();
      alert('Please add at least one course to quote');
      return false;
    }
    
    // Validate amounts and candidates
    let hasError = false;
    selectedRows.forEach(row => {
      const inquiryId = row.getAttribute('data-inquiry-id');
      if (!inquiryId) return;
      
      const amountInput = document.querySelector(`input[name="amount[${inquiryId}]"]`);
      const candidatesInput = document.querySelector(`input[name="candidates[${inquiryId}]"]`);
      
      if (!amountInput || !amountInput.value || parseFloat(amountInput.value) <= 0) {
        hasError = true;
      }
      if (!candidatesInput || !candidatesInput.value || parseInt(candidatesInput.value) < 1) {
        hasError = true;
      }
    });
    
    if (hasError) {
      e.preventDefault();
      alert('Please enter valid amounts and number of candidates (minimum 1) for all courses');
      return false;
    }
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




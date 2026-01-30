<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';
require '../includes/workflow.php';

/* RBAC Check */
requirePermission('trainings', 'create');

$inquiryId = $_GET['inquiry_id'] ?? '';
$quotationId = $_GET['quotation_id'] ?? '';
$quotationInquiryIds = []; // Will store all inquiry IDs from quotation if coming from quotation

// If quotation_id provided, fetch inquiry_id from quotation
if ($quotationId && !$inquiryId) {
  $quoteCtx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);
  $quotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=inquiry_id,status",
      false,
      $quoteCtx
    ),
    true
  )[0] ?? null;
  
  if (!$quotation || strtolower($quotation['status'] ?? '') !== 'accepted') {
    header('Location: quotations.php?error=' . urlencode('Quotation must be accepted before scheduling training'));
    exit;
  }
  
  // Extract all inquiry IDs from quotation
  // For multi-inquiry quotations: Find all inquiries that have quotations with same quotation_no
  // Since notes column doesn't exist, we'll query by quotation_no and find inquiries quoted around the same time
  $allInquiryIdsFromQuotation = [];
  
  // Get quotation details including quotation_no
  $quotationDetails = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=id,inquiry_id,quotation_no,created_at",
      false,
      $quoteCtx
    ),
    true
  )[0] ?? null;
  
  if ($quotationDetails) {
    // Start with primary inquiry_id
    $allInquiryIdsFromQuotation = [$quotationDetails['inquiry_id']];
    
    // For multi-inquiry: Find other inquiries for same client that were quoted at the same time
    // Get the primary inquiry to find client_id
    $primaryInquiry = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/inquiries?id=eq.{$quotationDetails['inquiry_id']}&select=client_id",
        false,
        $quoteCtx
      ),
      true
    )[0] ?? null;
    
    if ($primaryInquiry && !empty($primaryInquiry['client_id'])) {
      // Find all inquiries for same client that were quoted around the same time (within 5 seconds)
      $quoteTime = strtotime($quotationDetails['created_at'] ?? 'now');
      $timeWindowStart = date('Y-m-d H:i:s', $quoteTime - 5);
      $timeWindowEnd = date('Y-m-d H:i:s', $quoteTime + 5);
      
      // Query quotations created around the same time for the same client
      $relatedQuotations = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.{$quotationDetails['inquiry_id']}&select=id,inquiry_id&limit=1",
          false,
          $quoteCtx
        ),
        true
      ) ?: [];
      
      // For now, use single inquiry_id (multi-inquiry support requires quotation_id column in inquiries table)
      $allInquiryIdsFromQuotation = [$quotationDetails['inquiry_id']];
    }
  } else {
    // Fallback: Use single inquiry_id from quotation
    $allInquiryIdsFromQuotation = [$quotation['inquiry_id'] ?? ''];
  }
  
  // Use first inquiry ID for backward compatibility (will be overridden below)
  $inquiryId = $allInquiryIdsFromQuotation[0] ?? '';
  
  // Check LPO status
  $lpos = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/client_orders?quotation_id=eq.$quotationId&select=id,status&order=created_at.desc&limit=1",
      false,
      $quoteCtx
    ),
    true
  ) ?: [];
  
  if (empty($lpos) || strtolower($lpos[0]['status'] ?? '') !== 'verified') {
    header('Location: quotations.php?error=' . urlencode('LPO must be verified before scheduling training. Please verify the LPO first.'));
    exit;
  }
  
  // Store all inquiry IDs for use in the form
  $quotationInquiryIds = array_filter($allInquiryIdsFromQuotation); // Remove empty values
}

// If no inquiry_id or quotation_id provided, show list of available quotations
if (!$inquiryId && !$quotationId) {
  // Fetch all accepted quotations with verified LPOs
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);
  
  // Get all accepted quotations
  $acceptedQuotations = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?status=eq.accepted&select=id,quotation_no,inquiry_id,inquiries(course_name,client_id,clients(company_name))&order=created_at.desc",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  // Filter to only those with verified LPOs
  $availableQuotations = [];
  foreach ($acceptedQuotations as $q) {
    $lpos = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/client_orders?quotation_id=eq.{$q['id']}&select=id,status&order=created_at.desc&limit=1",
        false,
        $ctx
      ),
      true
    ) ?: [];
    
    if (!empty($lpos) && strtolower($lpos[0]['status'] ?? '') === 'verified') {
      // Check if training already exists for this inquiry
      $existingTraining = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/trainings?inquiry_id=eq.{$q['inquiry_id']}&select=id",
          false,
          $ctx
        ),
        true
      ) ?: [];
      
      if (empty($existingTraining)) {
        $availableQuotations[] = $q;
      }
    }
  }
  
  // Show selection page
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Schedule Training - Select Quotation</title>
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
        <h2>Schedule Training (Post-Quotation)</h2>
        <p class="muted">Select a quotation with verified LPO to schedule training</p>
      </div>
      <a href="trainings.php" class="btn">Back to Trainings</a>
    </div>
  
    <?php if (!empty($_GET['error'])): ?>
      <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
  
    <?php if (empty($availableQuotations)): ?>
      <div style="background: #fef3c7; color: #92400e; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
        <strong>⚠ No Available Quotations:</strong>
        <p style="margin: 8px 0 0 0;">
          There are no quotations with verified LPOs available for training scheduling.<br>
          <strong>To schedule training:</strong>
          <ol style="margin: 8px 0 0 20px;">
            <li>Ensure you have an <strong>accepted quotation</strong> (check Quotations module)</li>
            <li>Upload and <strong>verify the LPO</strong> (check Client Orders module)</li>
            <li>Return here to schedule the training</li>
          </ol>
        </p>
      </div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Quotation No</th>
            <th>Client</th>
            <th>Course</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($availableQuotations as $q): 
            $inquiry = $q['inquiries'] ?? null;
            $client = $inquiry['clients'] ?? null;
          ?>
            <tr>
              <td><?= htmlspecialchars($q['quotation_no']) ?></td>
              <td><?= htmlspecialchars($client['company_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars($inquiry['course_name'] ?? '-') ?></td>
              <td>
                <a href="schedule_training.php?quotation_id=<?= $q['id'] ?>" class="btn btn-primary">
                  Schedule New Training
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
  
  <?php include '../layout/footer.php'; ?>
  </body>
  </html>
  <?php
  exit;
}

if (!$inquiryId) {
  header('Location: schedule_training.php?error=' . urlencode('Inquiry ID or Quotation ID is required'));
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

/* Fetch inquiry */
$inquiry = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

if (!$inquiry) die('Inquiry not found');

// Enforce workflow - check prerequisites (quotation accepted + LPO verified)
$workflowCheck = canCreateTraining($inquiryId);
if (!$workflowCheck['allowed']) {
  header('Location: quotations.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

/* Check if already scheduled */
$existingTraining = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?inquiry_id=eq.$inquiryId&select=id",
    false,
    $ctx
  ),
  true
);

if (!empty($existingTraining)) {
  header('Location: trainings.php?error=' . urlencode('Training already scheduled for this inquiry'));
  exit;
}

/* Fetch client */
$client = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?id=eq.{$inquiry['client_id']}&select=*",
    false,
    $ctx
  ),
  true
)[0] ?? null;

/* Fetch trainers */
$trainers = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?role=eq.trainer&is_active=eq.true&select=id,full_name&order=full_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];

/* Fetch inquiries for training scheduling */
$allInquiries = [];

// If coming from a quotation, use inquiry IDs stored in quotation notes
if ($quotationId && !empty($quotationInquiryIds)) {
  // Use inquiry IDs from quotation (for multi-inquiry quotations)
  $allInquiries = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=in.(" . implode(',', $quotationInquiryIds) . ")&order=course_name.asc",
      false,
      $ctx
    ),
    true
  ) ?: [];
} else {
  // Otherwise, fetch all inquiries for this client that have accepted quotations and verified LPOs
  $clientInquiries = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&select=id",
      false,
      $ctx
    ),
    true
  ) ?: [];

  $clientInquiryIds = array_column($clientInquiries, 'id');

  if (!empty($clientInquiryIds)) {
    // Get accepted quotations for these inquiries (including notes for multi-inquiry)
    $acceptedQuotations = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/quotations?inquiry_id=in.(" . implode(',', $clientInquiryIds) . ")&status=eq.accepted&select=id,inquiry_id,notes",
        false,
        $ctx
      ),
      true
    ) ?: [];
    
    $quotationIds = array_column($acceptedQuotations, 'id');
    $allEligibleInquiryIds = [];
    
    // Extract inquiry IDs from each quotation (including multi-inquiry ones from notes)
    foreach ($acceptedQuotations as $q) {
      $qInquiryIds = [$q['inquiry_id']];
      
      // Check if quotation has multiple inquiry IDs in notes
      if (!empty($q['notes']) && preg_match('/MULTI_INQUIRY_IDS:\s*(\[.*?\])/s', $q['notes'], $matches)) {
        $multiIds = json_decode($matches[1], true);
        if (!empty($multiIds)) {
          $qInquiryIds = $multiIds;
        }
      }
      
      // Collect all inquiry IDs
      $allEligibleInquiryIds = array_merge($allEligibleInquiryIds, $qInquiryIds);
    }
    
    // Check which quotations have verified LPOs
    if (!empty($quotationIds)) {
      $verifiedLPOs = json_decode(
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/client_orders?quotation_id=in.(" . implode(',', $quotationIds) . ")&status=eq.verified&select=quotation_id",
          false,
          $ctx
        ),
        true
      ) ?: [];
      
      $verifiedQuotationIds = array_column($verifiedLPOs, 'quotation_id');
      $eligibleInquiryIds = [];
      
      // Get all inquiry IDs from verified quotations
      foreach ($verifiedQuotationIds as $qId) {
        // Use primary inquiry_id from quotation
        foreach ($acceptedQuotations as $q) {
          if ($q['id'] === $qId) {
            $eligibleInquiryIds[] = $q['inquiry_id'];
            break;
          }
        }
      }
      
      // Remove duplicates
      $eligibleInquiryIds = array_unique(array_filter($eligibleInquiryIds));
      
      // Fetch inquiries that are eligible (have accepted quotation + verified LPO)
      if (!empty($eligibleInquiryIds)) {
        $allInquiries = json_decode(
          file_get_contents(
            SUPABASE_URL . "/rest/v1/inquiries?id=in.(" . implode(',', $eligibleInquiryIds) . ")&order=course_name.asc",
            false,
            $ctx
          ),
          true
        ) ?: [];
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Schedule New Training</title>
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
      <h2>Schedule New Training</h2>
      <p class="muted">Create training session from accepted quotation with verified LPO</p>
    </div>
    <div class="actions">
      <a href="trainings.php" class="btn btn-sm btn-secondary">Back to Trainings</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <div class="form-card" style="max-width: 1000px;">
    <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">
      <h3 style="margin-bottom: 10px;">Client Information</h3>
      <p style="color: #6b7280; margin: 5px 0;"><strong>Client:</strong> <?= htmlspecialchars($client['company_name'] ?? '-') ?></p>
      <p style="color: #6b7280; margin: 5px 0;"><strong>Course:</strong> <?= htmlspecialchars($inquiry['course_name']) ?></p>
      <?php if (!empty($inquiry['quote_no'])): ?>
        <p style="color: #6b7280; margin: 5px 0;"><strong>Quote No:</strong> <?= htmlspecialchars($inquiry['quote_no']) ?></p>
      <?php endif; ?>
    </div>

    <form method="post" action="../api/trainings/schedule.php" id="scheduleForm">
      <?= csrfField() ?>
      <input type="hidden" name="inquiry_id" value="<?= $inquiryId ?>">
      <input type="hidden" name="client_id" value="<?= $inquiry['client_id'] ?>">
      
      <!-- Course Selection -->
      <div class="form-group">
        <label>Select Courses to Schedule *</label>
        <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 15px; background: #f9fafb;">
          <?php if (!empty($allInquiries)): ?>
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 2px solid #e5e7eb;">
                  <th style="padding: 10px; text-align: left; width: 40px;">
                    <input type="checkbox" id="select_all_courses" onchange="toggleAllCourses()" style="cursor: pointer;">
                  </th>
                  <th style="padding: 10px; text-align: left;">Course Name</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allInquiries as $inq): ?>
                  <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px;">
                      <input type="checkbox" name="inquiry_ids[]" value="<?= $inq['id'] ?>" 
                             class="course-checkbox" style="cursor: pointer;" 
                             <?= ($inq['id'] == $inquiryId || ($quotationId && in_array($inq['id'], $quotationInquiryIds ?? []))) ? 'checked' : '' ?>>
                    </td>
                    <td style="padding: 10px; font-weight: 500;">
                      <?= htmlspecialchars($inq['course_name']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <input type="hidden" name="inquiry_ids[]" value="<?= $inquiryId ?>">
            <p style="color: #6b7280; padding: 10px;"><?= htmlspecialchars($inquiry['course_name']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Training Date Selection -->
      <div class="form-group">
        <label>Training Date *</label>
        <input type="date" name="training_date" id="training_date" required 
               min="<?= date('Y-m-d') ?>" 
               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
               onchange="updateTimeSlots()">
        <small style="color: #6b7280; display: block; margin-top: 5px;">
          Select the date for this training session
        </small>
      </div>

      <!-- Time Selection -->
      <div class="form-group">
        <label>Select Time *</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 10px;">
          <?php
            // Generate time slots from 8:30 AM to 6:00 PM (30-minute intervals)
            $times = [];
            $startHour = 8;
            $startMinute = 30;
            $endHour = 18;
            
            for ($hour = $startHour; $hour <= $endHour; $hour++) {
              if ($hour == $startHour) {
                $minutes = [30];
              } elseif ($hour == $endHour) {
                $minutes = [0];
              } else {
                $minutes = [0, 30];
              }
              
              foreach ($minutes as $min) {
                $time24 = sprintf('%02d:%02d', $hour, $min);
                $time12 = date('g:i A', strtotime("$hour:$min"));
                $times[] = ['value' => $time24, 'display' => $time12];
              }
            }
            
            foreach ($times as $time):
          ?>
            <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: #fff;" 
                   class="time-slot-label" 
                   data-time="<?= $time['value'] ?>"
                   onmouseover="if(!this.classList.contains('disabled')) {this.style.borderColor='#2563eb'; this.style.background='#eff6ff'}" 
                   onmouseout="if(!this.querySelector('input').checked && !this.classList.contains('disabled')) {this.style.borderColor='#d1d5db'; this.style.background='#fff'}">
              <input type="radio" name="training_time" value="<?= $time['value'] ?>" 
                     class="time-radio" 
                     data-time="<?= $time['value'] ?>"
                     style="margin-right: 8px; cursor: pointer;"
                     onchange="updateTimeStyle(this)" required>
              <span style="font-weight: 500;"><?= $time['display'] ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Trainer Selection (Required) -->
      <div class="form-group">
        <label>Select Trainer <span style="color: #dc2626;">*</span></label>
        <select name="trainer_id" id="trainer_id" required style="width: 100%; padding: 10px;">
          <option value="">— Select trainer (required) —</option>
          <?php foreach ($trainers as $trainer): ?>
            <option value="<?= $trainer['id'] ?>">
              <?= htmlspecialchars($trainer['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #6b7280; display: block; margin-top: 5px;">
          Training cannot be scheduled without a trainer.
        </small>
      </div>


      <div class="form-actions">
        <button class="btn" type="submit">Schedule Training</button>
        <a href="quotations.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<script>
  function toggleAllCourses() {
    const selectAll = document.getElementById('select_all_courses');
    const checkboxes = document.querySelectorAll('.course-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = selectAll.checked;
    });
  }


  function updateTimeStyle(radio) {
    // Reset all time labels
    document.querySelectorAll('.time-radio').forEach(r => {
      const lbl = r.closest('label');
      if (!r.checked) {
        lbl.style.borderColor = '#d1d5db';
        lbl.style.background = '#fff';
        lbl.style.fontWeight = '500';
      }
    });
    
    // Update selected time label
    if (radio.checked) {
      const label = radio.closest('label');
      label.style.borderColor = '#2563eb';
      label.style.background = '#dbeafe';
      label.style.fontWeight = '600';
    }
  }


  // Function to update time slots based on selected date
  function updateTimeSlots() {
    const dateInput = document.getElementById('training_date');
    const selectedDate = dateInput.value;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const selectedDateObj = new Date(selectedDate + 'T00:00:00');
    selectedDateObj.setHours(0, 0, 0, 0);
    
    const isToday = selectedDateObj.getTime() === today.getTime();
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    const currentTimeMinutes = currentHour * 60 + currentMinute;
    
    // Get all time slot inputs and labels
    const timeRadios = document.querySelectorAll('.time-radio');
    const timeLabels = document.querySelectorAll('.time-slot-label');
    
    timeRadios.forEach(function(radio) {
      const timeValue = radio.getAttribute('data-time'); // Format: "HH:MM"
      const [hour, minute] = timeValue.split(':').map(Number);
      const slotTimeMinutes = hour * 60 + minute;
      
      const label = radio.closest('.time-slot-label');
      
      if (isToday && slotTimeMinutes <= currentTimeMinutes) {
        // Disable past time slots for today
        radio.disabled = true;
        radio.required = false;
        label.classList.add('disabled');
        label.style.opacity = '0.5';
        label.style.cursor = 'not-allowed';
        label.style.background = '#f3f4f6';
        label.style.borderColor = '#d1d5db';
        
        // If this time slot was selected, uncheck it
        if (radio.checked) {
          radio.checked = false;
          updateTimeStyle(radio);
        }
      } else {
        // Enable future time slots
        radio.disabled = false;
        radio.required = true;
        label.classList.remove('disabled');
        label.style.opacity = '1';
        label.style.cursor = 'pointer';
        
        // Reset hover styles if not checked
        if (!radio.checked) {
          label.style.background = '#fff';
          label.style.borderColor = '#d1d5db';
        }
      }
    });
    
    // Update required attribute on form
    const form = document.getElementById('scheduleForm');
    if (form) {
      const enabledRadios = Array.from(timeRadios).filter(r => !r.disabled);
      if (enabledRadios.length > 0) {
        enabledRadios.forEach(r => r.required = true);
      }
    }
  }
  
  // Call on page load to handle pre-selected dates
  document.addEventListener('DOMContentLoaded', function() {
    updateTimeSlots();
  });

  document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
    const trainingDate = document.getElementById('training_date')?.value;
    const selectedTime = document.querySelector('input[name="training_time"]:checked');
    const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
    const trainerId = document.getElementById('trainer_id')?.value?.trim();
    
    // Validate trainer is required
    if (!trainerId) {
      e.preventDefault();
      alert('Please select a trainer. Training cannot be scheduled without a trainer.');
      return;
    }
    
    // Validate training date
    if (!trainingDate || trainingDate.trim() === '') {
      e.preventDefault();
      alert('Please select a training date');
      return false;
    }
    
    // Validate time slot
    if (!selectedTime || selectedTime.disabled) {
      e.preventDefault();
      alert('Please select a valid time slot');
      return false;
    }
    
    // Validate at least one course is selected (only if course checkboxes exist)
    if (selectedCourses.length === 0 && document.querySelectorAll('.course-checkbox').length > 0) {
      e.preventDefault();
      alert('Please select at least one course');
      return false;
    }
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




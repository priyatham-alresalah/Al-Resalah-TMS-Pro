<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('trainings', 'create');

$inquiryId = $_GET['inquiry_id'] ?? '';
if (!$inquiryId) die('Inquiry ID missing');

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

/* Fetch all inquiries for this client to show all courses */
$allInquiries = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.{$inquiry['client_id']}&status=eq.accepted&order=course_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Schedule Training</title>
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
      <h2>Schedule Training</h2>
      <p class="muted">Select date and time for training session</p>
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
                             <?= $inq['id'] == $inquiryId ? 'checked' : '' ?>>
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
        <input type="date" name="training_date" required 
               min="<?= date('Y-m-d') ?>" 
               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
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
                   onmouseover="this.style.borderColor='#2563eb'; this.style.background='#eff6ff'" 
                   onmouseout="if(!this.querySelector('input').checked) {this.style.borderColor='#d1d5db'; this.style.background='#fff'}">
              <input type="radio" name="training_time" value="<?= $time['value'] ?>" 
                     class="time-radio" style="margin-right: 8px; cursor: pointer;"
                     onchange="updateTimeStyle(this)" required>
              <span style="font-weight: 500;"><?= $time['display'] ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Trainer Selection -->
      <div class="form-group">
        <label>Select Trainer (Optional)</label>
        <select name="trainer_id" style="width: 100%; padding: 10px;">
          <option value="">Not assigned - assign later</option>
          <?php foreach ($trainers as $trainer): ?>
            <option value="<?= $trainer['id'] ?>">
              <?= htmlspecialchars($trainer['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #6b7280; display: block; margin-top: 5px;">
          You can assign a trainer now or later from the Trainings page
        </small>
      </div>


      <div class="form-actions">
        <button class="btn" type="submit">Schedule Training</button>
        <a href="inquiries.php" class="btn-cancel">Cancel</a>
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


  document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
    const selectedDays = document.querySelectorAll('.day-checkbox:checked');
    const selectedTime = document.querySelector('input[name="training_time"]:checked');
    const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
    
    if (selectedDays.length === 0) {
      e.preventDefault();
      alert('Please select at least one day');
      return false;
    }
    
    if (!selectedTime) {
      e.preventDefault();
      alert('Please select a time');
      return false;
    }
    
    if (selectedCourses.length === 0) {
      e.preventDefault();
      alert('Please select at least one course');
      return false;
    }
  });
</script>

<?php include '../layout/footer.php'; ?>
</body>
</html>




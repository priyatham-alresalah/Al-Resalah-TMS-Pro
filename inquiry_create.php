<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$role = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

/* ---------------------------
   FETCH CLIENTS (for map + dropdown)
---------------------------- */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch clients - admin sees all, others see only their own */
$baseUrl = SUPABASE_URL . "/rest/v1/clients?select=id,company_name";
if ($role !== 'admin') {
  $baseUrl .= "&created_by=eq.$userId";
}
$clients = json_decode(
  file_get_contents($baseUrl, false, $ctx),
  true
) ?: [];

/* ---------------------------
   FETCH COURSES
---------------------------- */
$courses = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/training_master?is_active=eq.true&order=course_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Inquiry</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Create Inquiry</h2>
      <p class="muted">Add a new training inquiry for a client</p>
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

  <!-- ADD INQUIRY -->
  <?php if (!empty($clients)): ?>
    <div class="form-card" style="max-width: 100%;">
      <form method="post" action="api/inquiries/create.php" id="inquiryForm">
        <div class="form-group">
          <label>Select Client *</label>
          <select name="client_id" required>
            <option value="">Select Client *</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['company_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Select Courses *</label>
          <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 15px; background: #f9fafb;">
            <?php if (!empty($courses)): ?>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="border-bottom: 2px solid #e5e7eb;">
                    <th style="padding: 10px; text-align: left; width: 40px;">
                      <input type="checkbox" id="select_all_courses" onchange="toggleAllCourses()" style="cursor: pointer;">
                    </th>
                    <th style="padding: 10px; text-align: left;">Course Name</th>
                    <th style="padding: 10px; text-align: left; width: 120px;">Duration</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($courses as $course): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb; cursor: pointer;" 
                        onmouseover="this.style.background='#f3f4f6'" 
                        onmouseout="this.style.background='transparent'"
                        onclick="document.querySelector('input[value=\'<?= htmlspecialchars(addslashes($course['course_name'])) ?>\']').click()">
                      <td style="padding: 10px;">
                        <input type="checkbox" name="courses[]" value="<?= htmlspecialchars($course['course_name']) ?>" 
                               class="course-checkbox" style="cursor: pointer;" 
                               onclick="event.stopPropagation()">
                      </td>
                      <td style="padding: 10px; font-weight: 500;">
                        <?= htmlspecialchars($course['course_name']) ?>
                      </td>
                      <td style="padding: 10px; color: #6b7280;">
                        <?= htmlspecialchars($course['duration'] ?? '-') ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p style="color: #6b7280; padding: 10px;">No courses available. Use custom course names below.</p>
            <?php endif; ?>
          </div>
          <small style="color: #6b7280; display: block; margin-top: 5px;">
            Select one or more courses by checking the boxes above
          </small>
        </div>

        <div class="form-group">
          <label>Or Enter Custom Course Name(s) (one per line)</label>
          <textarea name="custom_courses" id="custom_courses" rows="4" placeholder="Enter custom course names, one per line" style="width: 100%; padding: 8px;"></textarea>
          <small style="color: #6b7280; display: block; margin-top: 5px;">
            Enter custom course names if not in the list above (one course per line)
          </small>
        </div>

        <div class="form-actions">
          <button class="btn" type="submit">Create Inquiry</button>
          <a href="inquiries.php">Cancel</a>
        </div>
      </form>
    </div>
    <script>
      const selectedCourses = {};
      
      function addCourse() {
        const dropdown = document.getElementById('course_dropdown');
        const courseName = dropdown.value;
        
        if (!courseName) {
          alert('Please select a course first');
          return;
        }
        
        if (selectedCourses[courseName]) {
          selectedCourses[courseName]++;
        } else {
          selectedCourses[courseName] = 1;
        }
        
        updateSelectedCoursesDisplay();
        dropdown.value = '';
      }
      
      function removeCourse(courseName) {
        if (selectedCourses[courseName]) {
          selectedCourses[courseName]--;
          if (selectedCourses[courseName] <= 0) {
            delete selectedCourses[courseName];
          }
        }
        updateSelectedCoursesDisplay();
      }
      
      function updateSelectedCoursesDisplay() {
        const container = document.getElementById('selected_courses');
        const courseNames = Object.keys(selectedCourses);
        
        if (courseNames.length === 0) {
          container.innerHTML = '<p style="color: #6b7280; margin: 0; font-size: 14px;">No courses selected yet</p>';
          return;
        }
        
        let html = '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
        courseNames.forEach(courseName => {
          const count = selectedCourses[courseName];
          html += `
            <div style="display: inline-flex; align-items: center; background: #fff; border: 1px solid #d1d5db; border-radius: 20px; padding: 6px 12px; font-size: 14px;">
              <span style="font-weight: 500;">${courseName}</span>
              ${count > 1 ? `<span style="margin-left: 6px; color: #2563eb; font-weight: 600;">+${count - 1}</span>` : ''}
              <button type="button" onclick="removeCourse('${courseName.replace(/'/g, "\\'")}')" 
                      style="margin-left: 8px; background: #fee2e2; color: #991b1b; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 12px; line-height: 1;">×</button>
            </div>
          `;
        });
        html += '</div>';
        container.innerHTML = html;
      }
      
      document.getElementById('inquiryForm')?.addEventListener('submit', function(e) {
        const courseNames = Object.keys(selectedCourses);
        const customCourses = document.getElementById('custom_courses').value.trim();
        
        if (courseNames.length === 0 && !customCourses) {
          e.preventDefault();
          alert('Please select at least one course or enter a custom course name');
          return false;
        }
        
        // Add hidden inputs for selected courses
        courseNames.forEach(courseName => {
          const count = selectedCourses[courseName];
          for (let i = 0; i < count; i++) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'courses[]';
            input.value = courseName;
            this.appendChild(input);
          }
        });
      });
      
      // Allow Enter key to add course
      document.getElementById('course_dropdown')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addCourse();
        }
      });
    </script>
  <?php else: ?>
    <div class="form-card">
      <p style="color: #6b7280;">No clients available. Please create a client first.</p>
      <div class="form-actions" style="margin-top: 15px;">
        <a href="clients.php" class="btn">Go to Clients</a>
        <a href="inquiries.php">Back to Inquiries</a>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>

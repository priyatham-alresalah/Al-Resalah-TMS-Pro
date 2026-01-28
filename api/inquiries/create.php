<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'];
$clientId = $_POST['client_id'] ?? '';

/* Verify client ownership (unless admin) */
if ($role !== 'admin' && $clientId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  $client = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.$clientId&select=id,created_by",
      false,
      $ctx
    ),
    true
  )[0] ?? null;

  if (!$client || $client['created_by'] !== $userId) {
    die('Access denied: You can only create inquiries for your own clients');
  }
}

/* Get selected courses from dropdown */
$selectedCourses = $_POST['courses'] ?? [];

/* Get custom courses from textarea (one per line) */
$customCoursesText = trim($_POST['custom_courses'] ?? '');
$customCourses = [];
if (!empty($customCoursesText)) {
  $lines = explode("\n", $customCoursesText);
  foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line)) {
      $customCourses[] = $line;
    }
  }
}

/* Combine all courses */
$allCourses = array_merge($selectedCourses, $customCourses);

if (empty($allCourses)) {
  header('Location: ../../pages/inquiries.php?error=' . urlencode('Please select at least one course or enter a custom course name'));
  exit;
}

/* Create one inquiry per course */
$successCount = 0;
foreach ($allCourses as $courseName) {
  $courseName = trim($courseName);
  if (empty($courseName)) continue;
  
  $data = [
    'client_id'   => $clientId,
    'course_name' => $courseName,
    'status'      => 'new',
    'created_by'  => $userId
  ];

  $ctx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($data)
    ]
  ]);

  $response = file_get_contents(SUPABASE_URL . "/rest/v1/inquiries", false, $ctx);
  if ($response !== false) {
    $successCount++;
  }
}

if ($successCount > 0) {
  header('Location: ../../pages/inquiries.php?success=' . urlencode("Successfully created $successCount inquiry(ies)!"));
} else {
  header('Location: ../../pages/inquiry_create.php?error=' . urlencode('Failed to create inquiries. Please try again.'));
}
exit;

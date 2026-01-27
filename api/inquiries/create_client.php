<?php
session_start();
require '../../includes/config.php';

if (!isset($_SESSION['client'])) {
  die('Access denied');
}

$client = $_SESSION['client'];
$clientId = $client['id'];

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
  header('Location: ../../client_portal/inquiry.php?error=' . urlencode('Please select at least one course or enter a custom course name'));
  exit;
}

/* Create one inquiry per course */
$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$successCount = 0;
foreach ($allCourses as $courseName) {
  $courseName = trim($courseName);
  if (empty($courseName)) continue;
  
  $data = json_encode([
    'client_id' => $clientId,
    'course_name' => $courseName,
    'status' => 'new'
  ]);

  $createCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => $data
    ]
  ]);

  $response = file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries",
    false,
    $createCtx
  );

  if ($response !== false) {
    $successCount++;
  }
}

if ($successCount > 0) {
  header('Location: ../../client_portal/inquiry.php?success=' . urlencode("Successfully submitted $successCount inquiry(ies)!"));
} else {
  header('Location: ../../client_portal/inquiry.php?error=' . urlencode('Failed to submit inquiries. Please try again.'));
}
exit;

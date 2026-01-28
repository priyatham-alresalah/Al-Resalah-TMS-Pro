<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

$data = [
  'course_name' => $_POST['course_name'],
  'duration'    => $_POST['duration'],
  'is_active'   => true
];

$ctx = stream_context_create([
  'http' => [
    'method'  => 'POST',
    'header'  =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/training_master",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to create training master course");
  header('Location: ' . BASE_PATH . '/pages/training_master.php?error=' . urlencode('Failed to create course. Please try again.'));
  exit;
}

header('Location: ' . BASE_PATH . '/pages/training_master.php?success=' . urlencode('Course created successfully'));
exit;

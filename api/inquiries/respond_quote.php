<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$inquiryId = $_POST['inquiry_id'] ?? '';
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$inquiryId || !$action) {
  die('Missing required fields');
}

$newStatus = '';
if ($action === 'accept') {
  $newStatus = 'accepted';
} elseif ($action === 'reject') {
  $newStatus = 'rejected';
} elseif ($action === 'requote') {
  $newStatus = 'new'; // Reset to new for requote
}

if (!$newStatus) {
  die('Invalid action');
}

$data = [
  'status' => $newStatus,
  'response_reason' => $reason,
  'responded_at' => date('Y-m-d H:i:s')
];

if ($action === 'accept') {
  $data['accepted_at'] = date('Y-m-d H:i:s');
} elseif ($action === 'reject') {
  $data['rejected_at'] = date('Y-m-d H:i:s');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId",
  false,
  $ctx
);

header('Location: ../../pages/inquiry_view.php?id=' . $inquiryId . '&success=' . urlencode("Response submitted successfully"));
exit;

<?php
/**
 * Test page to debug users fetching
 */
require '../includes/config.php';
require '../includes/auth_check.php';

if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$usersUrl = SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,is_active,created_at&order=created_at.desc";

echo "<h1>Users Debug Test</h1>";
echo "<p><strong>URL:</strong> " . htmlspecialchars($usersUrl) . "</p>";

$usersResponse = @file_get_contents($usersUrl, false, $ctx);

if ($usersResponse === false) {
  $error = error_get_last();
  die("Failed to fetch. Error: " . ($error['message'] ?? 'Unknown'));
}

echo "<p><strong>Raw Response Length:</strong> " . strlen($usersResponse) . " bytes</p>";
echo "<p><strong>Raw Response (first 500 chars):</strong></p>";
echo "<pre>" . htmlspecialchars(substr($usersResponse, 0, 500)) . "</pre>";

$users = json_decode($usersResponse, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  die("JSON Error: " . json_last_error_msg());
}

echo "<h2>Decoded Users:</h2>";
echo "<p><strong>Total:</strong> " . count($users) . "</p>";

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Index</th><th>ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Has ID?</th></tr>";

foreach ($users as $index => $u) {
  $hasId = !empty($u['id']);
  $rowColor = $hasId ? '' : 'background: #fee2e2;';
  echo "<tr style='$rowColor'>";
  echo "<td>$index</td>";
  echo "<td>" . htmlspecialchars($u['id'] ?? 'NULL') . "</td>";
  echo "<td>" . htmlspecialchars($u['full_name'] ?? 'NULL') . "</td>";
  echo "<td>" . htmlspecialchars($u['email'] ?? 'NULL') . "</td>";
  echo "<td>" . htmlspecialchars($u['role'] ?? 'NULL') . "</td>";
  echo "<td>" . ($hasId ? '✓ YES' : '✗ NO') . "</td>";
  echo "</tr>";
}

echo "</table>";

echo "<h2>Filtered Valid Users:</h2>";
$validUsers = array_values(array_filter($users, function($u) {
  return !empty($u['id']);
}));

echo "<p><strong>Valid Count:</strong> " . count($validUsers) . "</p>";

echo "<h2>Full Raw Data:</h2>";
echo "<pre>" . htmlspecialchars(print_r($users, true)) . "</pre>";

echo "<p><a href='users.php'>← Back to Users</a></p>";

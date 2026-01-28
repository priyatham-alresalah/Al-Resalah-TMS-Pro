<?php
/**
 * Debug page to check users data
 * Access: ?debug=1 on users.php or use this page directly
 */
require '../includes/config.php';
require '../includes/auth_check.php';

/* Admin only */
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

// Fetch all users from profiles table
$usersUrl = SUPABASE_URL . "/rest/v1/profiles?select=*&order=created_at.desc";

$usersResponse = @file_get_contents($usersUrl, false, $ctx);

if ($usersResponse === false) {
  $error = error_get_last();
  die("Failed to fetch users. Error: " . ($error['message'] ?? 'Unknown'));
}

$users = json_decode($usersResponse, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  die("JSON decode error: " . json_last_error_msg() . "<br>Response: " . htmlspecialchars(substr($usersResponse, 0, 1000)));
}

?>
<!DOCTYPE html>
<html>
<head>
  <title>Users Debug</title>
  <style>
    body { font-family: monospace; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f3f4f6; }
    .null { color: #999; font-style: italic; }
    .error { color: red; }
  </style>
</head>
<body>
  <h1>Users Debug Information</h1>
  
  <p><strong>Total users fetched:</strong> <?= count($users) ?></p>
  <p><strong>URL:</strong> <?= htmlspecialchars($usersUrl) ?></p>
  
  <h2>Raw Data:</h2>
  <pre><?= htmlspecialchars(print_r($users, true)) ?></pre>
  
  <h2>Table View:</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Is Active</th>
        <th>Created At</th>
        <th>Has ID?</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['id'] ?? 'NULL') ?></td>
          <td><?= htmlspecialchars($u['full_name'] ?? 'NULL') ?></td>
          <td><?= htmlspecialchars($u['email'] ?? 'NULL') ?></td>
          <td><?= htmlspecialchars($u['role'] ?? 'NULL') ?></td>
          <td><?= isset($u['is_active']) ? ($u['is_active'] ? 'true' : 'false') : 'NULL' ?></td>
          <td><?= htmlspecialchars($u['created_at'] ?? 'NULL') ?></td>
          <td class="<?= empty($u['id']) ? 'error' : '' ?>">
            <?= !empty($u['id']) ? '✓' : '✗ MISSING' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <h2>Validation:</h2>
  <ul>
    <?php
    $valid = 0;
    $invalid = 0;
    foreach ($users as $u) {
      if (empty($u['id'])) {
        $invalid++;
        echo "<li class='error'>User missing ID: " . htmlspecialchars(print_r($u, true)) . "</li>";
      } else {
        $valid++;
      }
    }
    ?>
  </ul>
  
  <p><strong>Valid users:</strong> <?= $valid ?></p>
  <p><strong>Invalid users:</strong> <?= $invalid ?></p>
  
  <p><a href="users.php">← Back to Users</a></p>
</body>
</html>

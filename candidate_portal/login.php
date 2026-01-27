<?php
session_start();
require '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email && $password) {
    /* Check if candidate exists */
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' =>
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE
      ]
    ]);

    $candidates = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/candidates?email=eq.$email&select=id,full_name,email,client_id",
        false,
        $ctx
      ),
      true
    );

    if (!empty($candidates[0])) {
      $candidate = $candidates[0];
      $_SESSION['candidate'] = [
        'id' => $candidate['id'],
        'full_name' => $candidate['full_name'],
        'email' => $candidate['email'],
        'client_id' => $candidate['client_id']
      ];
      header('Location: dashboard.php');
      exit;
    } else {
      $error = 'Invalid email or password';
    }
  } else {
    $error = 'Please enter email and password';
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Candidate Portal - Login</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <h2>Candidate Portal</h2>
      <p style="color: #6b7280; margin-bottom: 20px;">Login to access your certificates and invoices</p>
      
      <?php if ($error): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="email" name="email" placeholder="Email" required autocomplete="email">
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        <button type="submit">Login</button>
      </form>
      
      <a href="../index.php">Back to Admin Login</a>
    </div>
  </div>
</body>
</html>

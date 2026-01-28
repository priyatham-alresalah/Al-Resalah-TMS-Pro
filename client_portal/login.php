<?php
session_start();
require '../includes/config.php';

/* If already logged in, go to dashboard */
if (isset($_SESSION['client'])) {
  header('Location: dashboard.php');
  exit;
}

$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email && $password) {
    /* Check if client exists */
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' =>
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE
      ]
    ]);

    $clients = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/clients?email=eq.$email&select=id,company_name,email",
        false,
        $ctx
      ),
      true
    );

    if (!empty($clients[0])) {
      $client = $clients[0];
      $_SESSION['client'] = [
        'id' => $client['id'],
        'company_name' => $client['company_name'],
        'email' => $client['email']
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Client Login | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<!-- ================= HEADER ================= -->
<header class="auth-header">
  <div class="header-inner">
    <img src="../assets/images/logo.png" alt="AI Resalah">
    <h1><?= APP_NAME ?></h1>
  </div>
</header>

<!-- ================= LOGIN ================= -->
<div class="auth-container">
  <div class="auth-card">

    <h2>Client Portal</h2>
    <p style="color: #6b7280; margin-bottom: 20px; font-size: 14px;">Login to access your invoices and certificates</p>

    <?php if ($error): ?>
      <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <input
        type="email"
        name="email"
        placeholder="Email"
        required
        autocomplete="email"
      >

      <input
        type="password"
        name="password"
        placeholder="Password"
        required
        autocomplete="current-password"
      >

      <button type="submit">Login</button>
    </form>

    <a href="../index.php">Back to Admin Login</a>
  </div>
</div>

<!-- ================= FOOTER ================= -->
<footer class="auth-footer">
  © <?= date('Y') ?> <?= APP_NAME ?>
</footer>

</body>
</html>

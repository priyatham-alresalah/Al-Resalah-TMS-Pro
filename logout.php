<?php
require 'includes/config.php';

/* =========================
   DESTROY SESSION SAFELY
========================= */
$_SESSION = [];

if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

session_destroy();

/* =========================
   REDIRECT TO LOGIN
========================= */
header('Location: ' . BASE_PATH . '/index.php');
exit;

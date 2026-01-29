<?php
require_once __DIR__ . '/config.php';

/**
 * Centralized Authentication & Session Management
 * Handles session timeout, role validation, and user status checks
 */

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header("Location: " . BASE_PATH . "/");
  exit;
}

// Initialize last_activity if not set
if (!isset($_SESSION['last_activity'])) {
  $_SESSION['last_activity'] = time();
}

// Check session timeout (30 minutes = 1800 seconds)
$sessionTimeout = 1800;
$timeSinceLastActivity = time() - $_SESSION['last_activity'];

if ($timeSinceLastActivity > $sessionTimeout) {
  // Session expired - destroy and redirect
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
  header("Location: " . BASE_PATH . "/?error=session_expired");
  exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Verify user is still active (check every 5 minutes to reduce DB calls)
if (!isset($_SESSION['last_status_check']) || (time() - $_SESSION['last_status_check']) > 300) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);
  
  $profileResponse = @file_get_contents(
    SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $_SESSION['user']['id'] . '&select=is_active,role',
    false,
    $ctx
  );
  
  if ($profileResponse !== false) {
    $profile = json_decode($profileResponse, true)[0] ?? null;
    
    // Check if user is inactive or role changed
    if (!$profile || !$profile['is_active']) {
      // User deactivated - destroy session
      $_SESSION = [];
      if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
      }
      session_destroy();
      header("Location: " . BASE_PATH . "/?error=account_inactive");
      exit;
    }
    
    // Check if role changed
    if (isset($profile['role']) && $profile['role'] !== $_SESSION['user']['role']) {
      // Role changed - regenerate session
      $_SESSION['user']['role'] = $profile['role'];
      session_regenerate_id(true);
    }
    
    $_SESSION['last_status_check'] = time();
  }
}

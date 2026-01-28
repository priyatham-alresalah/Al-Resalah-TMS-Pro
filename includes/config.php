<?php
/* =========================
   SECURITY HEADERS (Early)
========================= */
require_once __DIR__ . '/security_headers.php';
setSecurityHeaders();

/* =========================
   SESSION
========================= */
if (session_status() === PHP_SESSION_NONE) {
  // Configure secure session settings
  ini_set('session.cookie_httponly', 1);
  ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
  ini_set('session.cookie_samesite', 'Strict');
  ini_set('session.gc_maxlifetime', 1800); // 30 minutes
  
  session_start();
  
  // Set secure cookie parameters
  $cookieParams = session_get_cookie_params();
  session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
  ]);
}

/* =========================
   BASE PATH (IMPORTANT)
========================= */
/*
  This makes paths work on:
  - localhost: '/training-management-system'
  - cPanel subdomain: '' (empty for root)
  - subfolder hosting: '/subfolder-name'

  For subdomain https://reports.alresalahct.com/, use empty string
  For localhost, use '/training-management-system'
*/
// PRODUCTION: Set to '' for subdomain root
// DEVELOPMENT: Set to '/training-management-system' for localhost
// Auto-detect: Check if running on localhost
$isLocalhost = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || 
               strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
define('BASE_PATH', $isLocalhost ? '/training-management-system' : '');

/* =========================
   SUPABASE CONFIG
========================= */
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://qqmzkqsbvsmteqdtparn.supabase.co');

/*
  ANON key → used for LOGIN / RESET
  Load from environment variable or .env file
*/
$supabaseAnon = getenv('SUPABASE_ANON');
if ($supabaseAnon === false) {
  // Fallback: Try to load from .env file if exists
  if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $supabaseAnon = $env['SUPABASE_ANON'] ?? null;
  }
  // Last resort: Use hardcoded (should be removed in production)
  if (empty($supabaseAnon)) {
    $supabaseAnon = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158';
  }
}
define('SUPABASE_ANON', $supabaseAnon);

/*
  SERVICE key → used for DB CRUD (server-side only)
  Load from environment variable or .env file
*/
$supabaseService = getenv('SUPABASE_SERVICE');
if ($supabaseService === false) {
  // Fallback: Try to load from .env file if exists
  if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $supabaseService = $env['SUPABASE_SERVICE'] ?? null;
  }
  // Last resort: Use hardcoded (should be removed in production)
  if (empty($supabaseService)) {
    $supabaseService = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs';
  }
}
define('SUPABASE_SERVICE', $supabaseService);

/* =========================
   APP SETTINGS
========================= */
define('APP_NAME', 'AI Resalah Consultancies & Training');

/* =========================
   ERROR REPORTING (PRODUCTION)
========================= */
/*
  PRODUCTION: Errors logged but not displayed
  DEVELOPMENT: Set to E_ALL and display_errors = 1
*/
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

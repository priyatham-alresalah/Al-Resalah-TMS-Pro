<?php
/* =========================
   PREVENT DOUBLE LOAD (avoids "Constant already defined" on login API)
========================= */
if (defined('BASE_PATH')) {
  return;
}

/* =========================
   SECURITY HEADERS (Early)
========================= */
require_once __DIR__ . '/security_headers.php';
// Headers are set automatically when security_headers.php is included

/* =========================
   SESSION
========================= */
if (session_status() === PHP_SESSION_NONE) {
  // Configure secure session settings BEFORE session_start()
  // Using ini_set() is safer and works before session_start()
  ini_set('session.cookie_httponly', 1);
  ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
  ini_set('session.cookie_samesite', 'Strict');
  ini_set('session.gc_maxlifetime', 1800); // 30 minutes
  ini_set('session.cookie_lifetime', 0); // Browser session
  ini_set('session.cookie_path', '/');
  ini_set('session.cookie_domain', '');
  
  // Now start the session with secure parameters
  session_start();
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
// Auto-detect: Check if running on localhost or production subdomain
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = $host === 'localhost' || 
               strpos($host, 'localhost') !== false ||
               strpos($host, '127.0.0.1') !== false ||
               strpos($host, '.local') !== false;

// Production subdomain: reports.alresalahct.com uses empty BASE_PATH
define('BASE_PATH', $isLocalhost ? '/training-management-system' : '');

/* Load .env into $_ENV (simple KEY=value parser, avoids parse_ini_file issues) */
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $pos = strpos($line, '=');
    if ($pos === false) continue;
    $key = trim(substr($line, 0, $pos));
    if ($key === '') continue;
    $val = trim(substr($line, $pos + 1));
    if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
      $val = trim($val, '"\'');
    }
    $_ENV[$key] = $val;
  }
}

/* =========================
   SUPABASE CONFIG
========================= */
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? 'https://qqmzkqsbvsmteqdtparn.supabase.co'));

/*
  ANON key → used for LOGIN / RESET
  Load from environment variable or .env file
*/
$supabaseAnon = getenv('SUPABASE_ANON') ?: ($_ENV['SUPABASE_ANON'] ?? null);
if (empty($supabaseAnon)) {
  $supabaseAnon = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158';
}
define('SUPABASE_ANON', $supabaseAnon);

/*
  SERVICE key → used for DB CRUD (server-side only)
  Load from environment variable or .env file
*/
$supabaseService = getenv('SUPABASE_SERVICE') ?: ($_ENV['SUPABASE_SERVICE'] ?? null);
if ($supabaseService === false || $supabaseService === null) {
  // Last resort: Fail safely if no key found (production safety)
  if (empty($supabaseService)) {
    error_log("CRITICAL: SUPABASE_SERVICE key not found in environment or .env file");
    // TEMPORARY: Allow hardcoded key for quick deployment (REMOVE AFTER SETTING .env FILE)
    // TODO: Create .env file on production server with SUPABASE_SERVICE key
    $supabaseService = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs';
    error_log("WARNING: Using hardcoded SUPABASE_SERVICE key. Please create .env file for production security.");
  }
}
define('SUPABASE_SERVICE', $supabaseService);

/* =========================
   APP SETTINGS
========================= */
define('APP_NAME', 'AI Resalah Consultancies & Training');

/* =========================
   EMAIL (SMTP) CONFIG
   Used for: quotations, invoices, certificates, password reset
   Change app password in .env as SMTP_PASS or set environment variable.
========================= */
$smtpHost   = getenv('SMTP_HOST')   ?: ($_ENV['SMTP_HOST']   ?? 'smtp.gmail.com');
$smtpPort   = getenv('SMTP_PORT')   ?: ($_ENV['SMTP_PORT']   ?? '587');
$smtpUser   = getenv('SMTP_USER')   ?: ($_ENV['SMTP_USER']   ?? '');
$smtpPass   = getenv('SMTP_PASS')   ?: ($_ENV['SMTP_PASS']   ?? '');
$smtpFrom   = getenv('SMTP_FROM')   ?: ($_ENV['SMTP_FROM']   ?? $smtpUser);
$smtpFromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? APP_NAME);

if (!defined('SMTP_HOST'))     define('SMTP_HOST',     $smtpHost);
if (!defined('SMTP_PORT'))     define('SMTP_PORT',     $smtpPort);
if (!defined('SMTP_USER'))     define('SMTP_USER',     $smtpUser);
if (!defined('SMTP_PASS'))     define('SMTP_PASS',     $smtpPass);
if (!defined('SMTP_FROM'))     define('SMTP_FROM',     $smtpFrom);
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', $smtpFromName);

/* =========================
   ERROR REPORTING (PRODUCTION)
========================= */
/*
  PRODUCTION: Errors logged but not displayed
  DEVELOPMENT: Set display_errors = 1 in local env if needed
*/
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (!$isLocalhost) {
  @ini_set('display_errors', 0);
  @ini_set('expose_php', 0);
}

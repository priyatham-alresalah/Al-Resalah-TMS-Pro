<?php
/* =========================
   SESSION
========================= */
if (session_status() === PHP_SESSION_NONE) {
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
define('BASE_PATH', '');

/* =========================
   SUPABASE CONFIG
========================= */
define('SUPABASE_URL', 'https://qqmzkqsbvsmteqdtparn.supabase.co');

/*
  ANON key → used for LOGIN / RESET
*/
define('SUPABASE_ANON', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158');

/*
  SERVICE key → used for DB CRUD (server-side only)
*/
define('SUPABASE_SERVICE', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs');

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

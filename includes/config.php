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
  - localhost
  - cPanel
  - subfolder hosting

  Example:
  https://reports.alresalahct.com/training-management-system/
*/
define('BASE_PATH', '/training-management-system');

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
   ERROR REPORTING (DEV ONLY)
========================= */
/*
  TURN OFF IN PRODUCTION
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);

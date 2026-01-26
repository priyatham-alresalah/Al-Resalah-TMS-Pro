<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);


/* Supabase credentials (backend only) */
define('SUPABASE_URL', 'https://qqmzkqsbvsmteqdtparn.supabase.co');

/* anon public key */
define('SUPABASE_ANON', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158');

/* service role key (NEVER expose in frontend) */
define('SUPABASE_SERVICE', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs');

// SMTP EMAIL CONFIG
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'priyatham.ramsai@gmail.com');
define('SMTP_PASS', 'fufy xjtu vrqo szld');
define('SMTP_FROM', 'priyatham.ramsai@gmail.com');
define('SMTP_FROM_NAME', 'Training Department');

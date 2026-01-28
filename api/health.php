<?php
/**
 * Health Check Endpoint
 * Returns system health status for monitoring
 */

require '../includes/config.php';
require '../includes/cache.php';

header('Content-Type: application/json');

$health = [
  'status' => 'healthy',
  'timestamp' => date('Y-m-d H:i:s'),
  'checks' => []
];

// Check 1: Application is up
$health['checks']['app'] = [
  'status' => 'ok',
  'message' => 'Application is running'
];

// Check 2: Database connectivity
try {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'timeout' => 5
    ]
  ]);
  
  $response = @file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?select=id&limit=1",
    false,
    $ctx
  );
  
  if ($response === false) {
    throw new Exception('Database connection failed');
  }
  
  $health['checks']['database'] = [
    'status' => 'ok',
    'message' => 'Database is reachable'
  ];
} catch (Exception $e) {
  $health['status'] = 'degraded';
  $health['checks']['database'] = [
    'status' => 'error',
    'message' => 'Database connection failed: ' . $e->getMessage()
  ];
}

// Check 3: Cache directory writable
try {
  ensureCacheDir();
  $testFile = CACHE_DIR . '/health_check_' . time() . '.tmp';
  $testWrite = @file_put_contents($testFile, 'test');
  
  if ($testWrite === false) {
    throw new Exception('Cache directory is not writable');
  }
  
  @unlink($testFile);
  
  $health['checks']['cache'] = [
    'status' => 'ok',
    'message' => 'Cache directory is writable'
  ];
} catch (Exception $e) {
  $health['status'] = 'degraded';
  $health['checks']['cache'] = [
    'status' => 'error',
    'message' => 'Cache directory error: ' . $e->getMessage()
  ];
}

// Check 4: Log directory writable
try {
  require '../includes/app_log.php';
  ensureLogDir();
  $testFile = LOG_DIR . '/health_check_' . time() . '.tmp';
  $testWrite = @file_put_contents($testFile, 'test');
  
  if ($testWrite === false) {
    throw new Exception('Log directory is not writable');
  }
  
  @unlink($testFile);
  
  $health['checks']['logs'] = [
    'status' => 'ok',
    'message' => 'Log directory is writable'
  ];
} catch (Exception $e) {
  $health['status'] = 'degraded';
  $health['checks']['logs'] = [
    'status' => 'error',
    'message' => 'Log directory error: ' . $e->getMessage()
  ];
}

// Set HTTP status code
if ($health['status'] === 'healthy') {
  http_response_code(200);
} else {
  http_response_code(503); // Service Unavailable
}

echo json_encode($health, JSON_PRETTY_PRINT);

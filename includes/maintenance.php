<?php
/**
 * Maintenance Mode Helper
 * Prevents writes during maintenance mode
 */

require __DIR__ . '/config.php';

/**
 * Check if maintenance mode is enabled
 * @return bool True if maintenance mode is active
 */
function isMaintenanceMode() {
  // Check environment variable first
  $envMaintenance = getenv('MAINTENANCE_MODE');
  if ($envMaintenance !== false && strtolower($envMaintenance) === 'true') {
    return true;
  }
  
  // Check for maintenance flag file
  $maintenanceFile = __DIR__ . '/../.maintenance';
  return file_exists($maintenanceFile);
}

/**
 * Enable maintenance mode
 * @return bool Success
 */
function enableMaintenanceMode() {
  $maintenanceFile = __DIR__ . '/../.maintenance';
  return @file_put_contents($maintenanceFile, date('Y-m-d H:i:s')) !== false;
}

/**
 * Disable maintenance mode
 * @return bool Success
 */
function disableMaintenanceMode() {
  $maintenanceFile = __DIR__ . '/../.maintenance';
  if (file_exists($maintenanceFile)) {
    return @unlink($maintenanceFile);
  }
  return true;
}

/**
 * Check if current request should be blocked
 * @param string $method HTTP method
 * @return bool True if request should be blocked
 */
function shouldBlockRequest($method) {
  if (!isMaintenanceMode()) {
    return false;
  }
  
  // Allow GET requests (read-only)
  if ($method === 'GET') {
    return false;
  }
  
  // Block all write operations
  return in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH']);
}

/**
 * Enforce maintenance mode (middleware-style)
 * Should be called at the start of API endpoints
 */
function enforceMaintenanceMode() {
  if (!isMaintenanceMode()) {
    return;
  }
  
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  
  if (shouldBlockRequest($method)) {
    http_response_code(503);
    header('Retry-After: 3600'); // Suggest retry after 1 hour
    
    require __DIR__ . '/app_log.php';
    logWarning('maintenance_mode_blocked', [
      'method' => $method,
      'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    die(json_encode([
      'error' => 'Service Unavailable',
      'message' => 'System is under maintenance. Please try again later.',
      'maintenance_mode' => true
    ]));
  }
}

<?php
/**
 * API Middleware
 * Centralized middleware for rate limiting, maintenance mode, and security headers
 * Include this at the start of API endpoints
 */

require __DIR__ . '/config.php';
require __DIR__ . '/security_headers.php';
require __DIR__ . '/maintenance.php';
require __DIR__ . '/rate_limit.php';

/**
 * Initialize API middleware
 * @param string $endpoint Endpoint identifier for rate limiting
 */
function initAPIMiddleware($endpoint = null) {
  // Set security headers
  setSecurityHeaders();
  
  // Enforce maintenance mode
  enforceMaintenanceMode();
  
  // Enforce rate limiting
  if ($endpoint !== null) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $config = getRateLimitConfig($method, $endpoint);
    enforceRateLimit($endpoint, $config['limit'], $config['type']);
  }
}

<?php
/**
 * Rate Limiting Helper
 * Implements IP and user-based rate limiting for API endpoints
 */

require __DIR__ . '/config.php';

define('RATE_LIMIT_DIR', __DIR__ . '/../cache/rate_limits');
define('RATE_LIMIT_WINDOW', 60); // 60 seconds window

/**
 * Ensure rate limit directory exists
 */
function ensureRateLimitDir() {
  if (!is_dir(RATE_LIMIT_DIR)) {
    @mkdir(RATE_LIMIT_DIR, 0755, true);
  }
}

/**
 * Get rate limit key for IP or user
 * @param string $type 'ip' or 'user'
 * @param string $identifier IP address or user ID
 * @param string $endpoint Endpoint identifier
 * @return string Cache key
 */
function getRateLimitKey($type, $identifier, $endpoint) {
  return md5("rate_limit_{$type}_{$identifier}_{$endpoint}");
}

/**
 * Get rate limit file path
 * @param string $key Rate limit key
 * @return string File path
 */
function getRateLimitFilePath($key) {
  ensureRateLimitDir();
  return RATE_LIMIT_DIR . '/' . $key . '.json';
}

/**
 * Get current rate limit count
 * @param string $key Rate limit key
 * @return array ['count' => int, 'reset_time' => int]
 */
function getRateLimitCount($key) {
  $file = getRateLimitFilePath($key);
  
  if (!file_exists($file)) {
    return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW];
  }
  
  $data = @json_decode(@file_get_contents($file), true);
  if (!$data || !is_array($data)) {
    return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW];
  }
  
  // Check if window expired
  if (time() > ($data['reset_time'] ?? 0)) {
    return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW];
  }
  
  return $data;
}

/**
 * Increment rate limit count
 * @param string $key Rate limit key
 * @return array ['count' => int, 'reset_time' => int, 'remaining' => int]
 */
function incrementRateLimit($key) {
  $current = getRateLimitCount($key);
  $current['count']++;
  $current['remaining'] = max(0, $current['limit'] - $current['count']);
  
  $file = getRateLimitFilePath($key);
  @file_put_contents($file, json_encode($current), LOCK_EX);
  
  return $current;
}

/**
 * Set rate limit
 * @param string $key Rate limit key
 * @param int $limit Maximum requests per window
 */
function setRateLimit($key, $limit) {
  $current = getRateLimitCount($key);
  $current['limit'] = $limit;
  $current['remaining'] = $limit - $current['count'];
  
  $file = getRateLimitFilePath($key);
  @file_put_contents($file, json_encode($current), LOCK_EX);
}

/**
 * Check rate limit for IP address
 * @param string $endpoint Endpoint identifier
 * @param int $limit Maximum requests per window
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimitIP($endpoint, $limit) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $key = getRateLimitKey('ip', $ip, $endpoint);
  
  $current = getRateLimitCount($key);
  setRateLimit($key, $limit);
  
  $result = incrementRateLimit($key);
  
  return [
    'allowed' => $result['count'] <= $limit,
    'remaining' => max(0, $limit - $result['count']),
    'reset_time' => $result['reset_time']
  ];
}

/**
 * Check rate limit for authenticated user
 * @param string $endpoint Endpoint identifier
 * @param int $limit Maximum requests per window
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimitUser($endpoint, $limit) {
  if (!isset($_SESSION['user']['id'])) {
    // Fallback to IP if not authenticated
    return checkRateLimitIP($endpoint, $limit);
  }
  
  $userId = $_SESSION['user']['id'];
  $key = getRateLimitKey('user', $userId, $endpoint);
  
  $current = getRateLimitCount($key);
  setRateLimit($key, $limit);
  
  $result = incrementRateLimit($key);
  
  return [
    'allowed' => $result['count'] <= $limit,
    'remaining' => max(0, $limit - $result['count']),
    'reset_time' => $result['reset_time']
  ];
}

/**
 * Enforce rate limit (middleware-style)
 * @param string $endpoint Endpoint identifier
 * @param int $limit Maximum requests per window
 * @param string $type 'ip' or 'user' (default: 'user')
 */
function enforceRateLimit($endpoint, $limit, $type = 'user') {
  if ($type === 'ip') {
    $result = checkRateLimitIP($endpoint, $limit);
  } else {
    $result = checkRateLimitUser($endpoint, $limit);
  }
  
  if (!$result['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . ($result['reset_time'] - time()));
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: 0');
    header('X-RateLimit-Reset: ' . $result['reset_time']);
    
    // Log rate limit violation
    require __DIR__ . '/app_log.php';
    appLog('warning', 'rate_limit_exceeded', [
      'endpoint' => $endpoint,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_id' => $_SESSION['user']['id'] ?? null,
      'limit' => $limit
    ]);
    
    die(json_encode([
      'error' => 'Rate limit exceeded',
      'message' => 'Too many requests. Please try again later.',
      'retry_after' => $result['reset_time'] - time()
    ]));
  }
  
  // Set response headers
  header('X-RateLimit-Limit: ' . $limit);
  header('X-RateLimit-Remaining: ' . $result['remaining']);
  header('X-RateLimit-Reset: ' . $result['reset_time']);
}

/**
 * Get rate limit configuration for endpoint type
 * @param string $method HTTP method
 * @param string $endpoint Endpoint path
 * @return array ['limit' => int, 'type' => string]
 */
function getRateLimitConfig($method, $endpoint) {
  // Auth endpoints: stricter limits
  if (strpos($endpoint, '/auth/') !== false || strpos($endpoint, 'login') !== false) {
    return ['limit' => 10, 'type' => 'ip']; // 10 per minute per IP
  }
  
  // Write endpoints: moderate limits
  if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    return ['limit' => 60, 'type' => 'user']; // 60 per minute per user
  }
  
  // Read endpoints: higher limits
  return ['limit' => 300, 'type' => 'user']; // 300 per minute per user
}

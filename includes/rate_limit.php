<?php
/**
 * Rate Limiting Helper
 * Implements IP and user-based rate limiting for API endpoints
 */

require_once __DIR__ . '/config.php';

define('RATE_LIMIT_DIR', __DIR__ . '/../cache/rate_limits');
define('RATE_LIMIT_WINDOW', 60); // 60 seconds window

/**
 * Ensure rate limit directory exists
 */
function ensureRateLimitDir() {
  if (!is_dir(RATE_LIMIT_DIR)) {
    $created = @mkdir(RATE_LIMIT_DIR, 0755, true);
    if (!$created && !is_dir(RATE_LIMIT_DIR)) {
      // If directory creation fails, log error but don't crash
      error_log("Failed to create rate limit directory: " . RATE_LIMIT_DIR);
      return false;
    }
  }
  return true;
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
  if (!ensureRateLimitDir()) {
    // Fallback to temp directory if cache dir can't be created
    return sys_get_temp_dir() . '/rate_limit_' . $key . '.json';
  }
  return RATE_LIMIT_DIR . '/' . $key . '.json';
}

/**
 * Get current rate limit count
 * @param string $key Rate limit key
 * @return array ['count' => int, 'reset_time' => int, 'limit' => int]
 */
function getRateLimitCount($key) {
  try {
    $file = getRateLimitFilePath($key);
    
    if (!file_exists($file)) {
      return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW, 'limit' => 0];
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
      return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW, 'limit' => 0];
    }
    
    $data = @json_decode($content, true);
    if (!$data || !is_array($data)) {
      return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW, 'limit' => 0];
    }
    
    // Check if window expired
    if (time() > ($data['reset_time'] ?? 0)) {
      return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW, 'limit' => $data['limit'] ?? 0];
    }
    
    // Ensure all required keys are set
    if (!isset($data['count'])) {
      $data['count'] = 0;
    }
    if (!isset($data['limit'])) {
      $data['limit'] = 0;
    }
    if (!isset($data['reset_time'])) {
      $data['reset_time'] = time() + RATE_LIMIT_WINDOW;
    }
    
    return $data;
  } catch (Throwable $e) {
    // If reading fails, return default values
    error_log("Rate limit read error: " . $e->getMessage());
    return ['count' => 0, 'reset_time' => time() + RATE_LIMIT_WINDOW, 'limit' => 0];
  }
}

/**
 * Increment rate limit count
 * @param string $key Rate limit key
 * @param int $limit Maximum limit (optional, will use stored limit if not provided)
 * @return array ['count' => int, 'reset_time' => int, 'remaining' => int, 'limit' => int]
 */
function incrementRateLimit($key, $limit = null) {
  $current = getRateLimitCount($key);
  
  // Ensure limit is set
  if ($limit === null) {
    $limit = $current['limit'] ?? 0;
  }
  $current['limit'] = $limit;
  
  // Initialize count if not set
  if (!isset($current['count'])) {
    $current['count'] = 0;
  }
  
  $current['count']++;
  $current['remaining'] = max(0, $limit - $current['count']);
  
  // Ensure reset_time is set
  if (!isset($current['reset_time'])) {
    $current['reset_time'] = time() + RATE_LIMIT_WINDOW;
  }
  
  $file = getRateLimitFilePath($key);
  $written = @file_put_contents($file, json_encode($current), LOCK_EX);
  
  // If file write fails, log but don't crash
  if ($written === false) {
    error_log("Failed to write rate limit file: " . $file);
  }
  
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
  $current['remaining'] = $limit - ($current['count'] ?? 0);
  
  $file = getRateLimitFilePath($key);
  $written = @file_put_contents($file, json_encode($current), LOCK_EX);
  
  // If file write fails, log but don't crash
  if ($written === false) {
    error_log("Failed to write rate limit file: " . $file);
  }
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
  
  // Increment with limit passed directly
  $result = incrementRateLimit($key, $limit);
  
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
  // Check if session is available and user is authenticated
  if (!isset($_SESSION) || !isset($_SESSION['user']['id'])) {
    // Fallback to IP if not authenticated
    return checkRateLimitIP($endpoint, $limit);
  }
  
  $userId = $_SESSION['user']['id'];
  $key = getRateLimitKey('user', $userId, $endpoint);
  
  // Increment with limit passed directly
  $result = incrementRateLimit($key, $limit);
  
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
  // Temporarily disable rate limiting if RATE_LIMIT_DISABLED is set
  if (defined('RATE_LIMIT_DISABLED') && RATE_LIMIT_DISABLED) {
    return;
  }
  
  try {
    if ($type === 'ip') {
      $result = checkRateLimitIP($endpoint, $limit);
    } else {
      $result = checkRateLimitUser($endpoint, $limit);
    }
  } catch (Throwable $e) {
    // If rate limiting fails, log error but don't block request
    error_log("Rate limit error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    return; // Allow request to proceed
  }
  
  if (!$result['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . ($result['reset_time'] - time()));
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: 0');
    header('X-RateLimit-Reset: ' . $result['reset_time']);
    
    // Log rate limit violation (only if app_log functions exist)
    if (function_exists('appLog')) {
      @appLog('warning', 'rate_limit_exceeded', [
        'endpoint' => $endpoint,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null,
        'limit' => $limit
      ]);
    } else {
      // Fallback: require app_log if function doesn't exist
      @require_once __DIR__ . '/app_log.php';
      if (function_exists('appLog')) {
        @appLog('warning', 'rate_limit_exceeded', [
          'endpoint' => $endpoint,
          'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
          'user_id' => isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null,
          'limit' => $limit
        ]);
      }
    }
    
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

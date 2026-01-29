<?php
/**
 * Centralized Application Logging Helper
 * Production-safe logging with sanitization
 */

// Only require config if not already loaded
if (!defined('BASE_PATH')) {
  require __DIR__ . '/config.php';
}

define('LOG_DIR', __DIR__ . '/../logs');
define('LOG_FILE', LOG_DIR . '/app.log');
define('ERROR_LOG_FILE', LOG_DIR . '/error.log');
define('SECURITY_LOG_FILE', LOG_DIR . '/security.log');

/**
 * Ensure log directory exists
 */
function ensureLogDir() {
  if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
  }
}

/**
 * Sanitize log data (remove sensitive information)
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeLogData($data) {
  if (is_array($data)) {
    $sanitized = [];
    $sensitiveKeys = ['password', 'token', 'secret', 'key', 'api_key', 'csrf_token', 'authorization', 'apikey'];
    
    foreach ($data as $key => $value) {
      $keyLower = strtolower($key);
      $isSensitive = false;
      
      foreach ($sensitiveKeys as $sensitive) {
        if (strpos($keyLower, $sensitive) !== false) {
          $isSensitive = true;
          break;
        }
      }
      
      if ($isSensitive) {
        $sanitized[$key] = '[REDACTED]';
      } elseif (is_array($value)) {
        $sanitized[$key] = sanitizeLogData($value);
      } elseif (is_string($value) && strlen($value) > 500) {
        $sanitized[$key] = substr($value, 0, 500) . '...[TRUNCATED]';
      } else {
        $sanitized[$key] = $value;
      }
    }
    
    return $sanitized;
  }
  
  if (is_string($data) && strlen($data) > 500) {
    return substr($data, 0, 500) . '...[TRUNCATED]';
  }
  
  return $data;
}

/**
 * Format log entry
 * @param string $level Log level
 * @param string $message Log message
 * @param array|null $context Additional context
 * @return string Formatted log entry
 */
function formatLogEntry($level, $message, $context = null) {
  $timestamp = date('Y-m-d H:i:s');
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $userId = $_SESSION['user']['id'] ?? 'anonymous';
  $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
  
  $entry = "[$timestamp] [$level] $message";
  $entry .= " | IP: $ip | User: $userId | URI: $requestUri";
  
  if ($context !== null) {
    $sanitized = sanitizeLogData($context);
    $entry .= " | Context: " . json_encode($sanitized);
  }
  
  return $entry . PHP_EOL;
}

/**
 * Write log entry to file
 * @param string $file Log file path
 * @param string $entry Log entry
 */
function writeLogEntry($file, $entry) {
  ensureLogDir();
  @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
  
  // Rotate log if > 10MB
  if (file_exists($file) && filesize($file) > 10 * 1024 * 1024) {
    $backupFile = $file . '.' . date('Y-m-d-His');
    @rename($file, $backupFile);
    
    // Keep only last 5 backups
    $backups = glob($file . '.*');
    if (count($backups) > 5) {
      usort($backups, function($a, $b) {
        return filemtime($a) - filemtime($b);
      });
      foreach (array_slice($backups, 0, -5) as $oldBackup) {
        @unlink($oldBackup);
      }
    }
  }
}

/**
 * Log application event
 * @param string $level Log level (info, warning, error, debug)
 * @param string $message Log message
 * @param array|null $context Additional context
 */
function appLog($level, $message, $context = null) {
  $entry = formatLogEntry($level, $message, $context);
  
  // Write to main log
  writeLogEntry(LOG_FILE, $entry);
  
  // Write errors to error log
  if ($level === 'error') {
    writeLogEntry(ERROR_LOG_FILE, $entry);
  }
  
  // Write security events to security log
  $securityKeywords = ['rate_limit', 'csrf', 'auth', 'unauthorized', 'forbidden', 'security', 'violation'];
  foreach ($securityKeywords as $keyword) {
    if (stripos($message, $keyword) !== false || ($context && is_array($context) && isset($context['type']) && stripos($context['type'], $keyword) !== false)) {
      writeLogEntry(SECURITY_LOG_FILE, $entry);
      break;
    }
  }
}

/**
 * Log error with context
 * @param string $message Error message
 * @param array|null $context Additional context
 * @param Exception|null $exception Exception object
 */
function logError($message, $context = null, $exception = null) {
  if ($exception !== null) {
    $context = $context ?? [];
    $context['exception'] = [
      'message' => $exception->getMessage(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => substr($exception->getTraceAsString(), 0, 1000) // Limit trace
    ];
  }
  
  appLog('error', $message, $context);
}

/**
 * Log warning
 * @param string $message Warning message
 * @param array|null $context Additional context
 */
function logWarning($message, $context = null) {
  appLog('warning', $message, $context);
}

/**
 * Log info
 * @param string $message Info message
 * @param array|null $context Additional context
 */
function logInfo($message, $context = null) {
  appLog('info', $message, $context);
}

/**
 * Log security event
 * @param string $message Security message
 * @param array|null $context Additional context
 */
function logSecurity($message, $context = null) {
  appLog('warning', "SECURITY: $message", $context);
  writeLogEntry(SECURITY_LOG_FILE, formatLogEntry('security', $message, $context));
}

<?php
/**
 * Simple File-Based Cache Helper
 * Provides lightweight caching for static/reference data
 */

define('CACHE_DIR', __DIR__ . '/../cache');
define('CACHE_DEFAULT_TTL', 300); // 5 minutes

/**
 * Ensure cache directory exists
 */
function ensureCacheDir() {
  if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
  }
}

/**
 * Get cache key file path
 * @param string $key Cache key
 * @return string File path
 */
function getCacheFilePath($key) {
  ensureCacheDir();
  return CACHE_DIR . '/' . md5($key) . '.cache';
}

/**
 * Get cached value
 * @param string $key Cache key
 * @param int $ttl Time to live in seconds
 * @return mixed|null Cached value or null if expired/not found
 */
function getCache($key, $ttl = CACHE_DEFAULT_TTL) {
  $file = getCacheFilePath($key);
  
  if (!file_exists($file)) {
    return null;
  }
  
  // Check if expired
  if (time() - filemtime($file) > $ttl) {
    @unlink($file);
    return null;
  }
  
  $content = @file_get_contents($file);
  if ($content === false) {
    return null;
  }
  
  $data = @unserialize($content);
  return $data !== false ? $data : null;
}

/**
 * Set cached value
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @return bool Success
 */
function setCache($key, $value) {
  $file = getCacheFilePath($key);
  ensureCacheDir();
  
  $content = serialize($value);
  return @file_put_contents($file, $content) !== false;
}

/**
 * Delete cached value
 * @param string $key Cache key
 * @return bool Success
 */
function deleteCache($key) {
  $file = getCacheFilePath($key);
  if (file_exists($file)) {
    return @unlink($file);
  }
  return true;
}

/**
 * Clear all cache
 * @return int Number of files deleted
 */
function clearCache() {
  ensureCacheDir();
  $count = 0;
  $files = glob(CACHE_DIR . '/*.cache');
  foreach ($files as $file) {
    if (@unlink($file)) {
      $count++;
    }
  }
  return $count;
}

/**
 * Invalidate cache by pattern (e.g., 'training_master_*')
 * @param string $pattern Pattern to match
 * @return int Number of files deleted
 */
function invalidateCachePattern($pattern) {
  ensureCacheDir();
  $count = 0;
  $files = glob(CACHE_DIR . '/*.cache');
  
  foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content !== false) {
      $data = @unserialize($content);
      // Check if cache key matches pattern
      if (is_array($data) && isset($data['_cache_key']) && fnmatch($pattern, $data['_cache_key'])) {
        if (@unlink($file)) {
          $count++;
        }
      }
    }
  }
  
  return $count;
}

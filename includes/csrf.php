<?php
/**
 * CSRF Protection Helper
 * Generates and validates CSRF tokens
 */

// Prevent multiple includes
if (defined('CSRF_INCLUDED')) {
  return;
}
define('CSRF_INCLUDED', true);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Generate CSRF token and store in session
 */
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken() {
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
}

/**
 * Get current CSRF token
 */
if (!function_exists('getCSRFToken')) {
function getCSRFToken() {
  if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
  }
  return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
  if (!isset($_SESSION['csrf_token'])) {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}
}

/**
 * Generate CSRF token HTML input field
 */
if (!function_exists('csrfField')) {
function csrfField() {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}

/**
 * Require CSRF token validation (for API endpoints)
 * Handles POST, PUT, DELETE, PATCH methods
 */
function requireCSRF() {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  
  // Only validate for state-changing methods
  if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    // Get token from POST data or headers (for AJAX)
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($token) || !validateCSRFToken($token)) {
      http_response_code(403);
      die('Invalid CSRF token. Please refresh the page and try again.');
    }
  }
}
}

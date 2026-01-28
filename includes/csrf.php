<?php
/**
 * CSRF Protection Helper
 * Generates and validates CSRF tokens
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Generate CSRF token and store in session
 */
function generateCSRFToken() {
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 */
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

/**
 * Generate CSRF token HTML input field
 */
function csrfField() {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}

/**
 * Require CSRF token validation (for API endpoints)
 */
function requireCSRF() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
      http_response_code(403);
      die('Invalid CSRF token. Please refresh the page and try again.');
    }
  }
}

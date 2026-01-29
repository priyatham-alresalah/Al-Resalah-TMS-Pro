<?php
/**
 * HTTP Security Headers Helper
 * Sets production-safe security headers
 */

// Prevent multiple includes
if (defined('SECURITY_HEADERS_INCLUDED')) {
  return;
}
define('SECURITY_HEADERS_INCLUDED', true);

/**
 * Set security headers
 * Should be called before any output
 */
if (!function_exists('setSecurityHeaders')) {
function setSecurityHeaders() {
  // Only set headers if not already sent
  if (headers_sent()) {
    return;
  }
  
  // Content Security Policy (safe default)
  // Allow same-origin, inline scripts/styles (for existing code), and data URIs for images
  header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://qqmzkqsbvsmteqdtparn.supabase.co; frame-ancestors 'none';");
  
  // Prevent clickjacking
  header("X-Frame-Options: DENY");
  
  // Prevent MIME type sniffing
  header("X-Content-Type-Options: nosniff");
  
  // Referrer policy
  header("Referrer-Policy: strict-origin-when-cross-origin");
  
  // XSS Protection (legacy, but harmless)
  header("X-XSS-Protection: 1; mode=block");
  
  // Permissions Policy (restrictive)
  header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
}

// Set headers automatically when included (only if not already set and headers not sent)
if (!defined('SECURITY_HEADERS_SET') && !headers_sent()) {
  setSecurityHeaders();
  define('SECURITY_HEADERS_SET', true);
}

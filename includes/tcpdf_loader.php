<?php
/**
 * TCPDF Library Loader for Certificate Generation
 * Loads TCPDF library for Unicode + RTL + Arabic support
 */

// Prevent multiple includes
if (defined('TCPDF_LOADED')) {
  return;
}
define('TCPDF_LOADED', true);

/**
 * Load TCPDF library
 * Tries multiple locations
 */
if (!function_exists('loadTCPDFLibrary')) {
  function loadTCPDFLibrary(): bool {
    // Check if TCPDF is already loaded
    if (class_exists('TCPDF')) {
      return true;
    }

    // Try Composer autoload FIRST (TCPDF installed via composer)
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
      require_once $autoloadPath;
      // Give autoload a moment to register classes
      if (class_exists('TCPDF')) {
        return true;
      }
    }
    
    // Try direct TCPDF path (composer installs to vendor/tecnickcom/tcpdf/)
    $tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdfPath)) {
      // Set TCPDF paths before loading
      if (!defined('K_PATH_MAIN')) {
        define('K_PATH_MAIN', __DIR__ . '/../vendor/tecnickcom/tcpdf/');
      }
      if (!defined('K_PATH_FONTS')) {
        define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
      }
      if (!defined('K_PATH_CACHE')) {
        define('K_PATH_CACHE', sys_get_temp_dir() . '/');
      }
      if (!defined('K_PATH_URL')) {
        define('K_PATH_URL', '');
      }
      if (!defined('K_BLANK_IMAGE')) {
        define('K_BLANK_IMAGE', '_blank.png');
      }
      
      require_once $tcpdfPath;
      if (class_exists('TCPDF')) {
        return true;
      }
    }

    // Try includes/tcpdf/
    if (file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
      require_once __DIR__ . '/tcpdf/tcpdf.php';
      if (class_exists('TCPDF')) {
        return true;
      }
    }

    // Try vendor/tecnickcom/tcpdf/
    if (file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php')) {
      require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
      if (class_exists('TCPDF')) {
        return true;
      }
    }

    return false;
  }
}

// Attempt to load TCPDF
$tcpdfLoaded = loadTCPDFLibrary();

if (!$tcpdfLoaded) {
  error_log("TCPDF library not found. Certificate PDF generation requires TCPDF.");
  error_log("Install via: composer require tecnickcom/tcpdf");
  error_log("Or download from: https://github.com/tecnickcom/TCPDF");
}

/**
 * Check if TCPDF is available
 */
if (!function_exists('isTCPDFAvailable')) {
  function isTCPDFAvailable(): bool {
    return class_exists('TCPDF');
  }
}

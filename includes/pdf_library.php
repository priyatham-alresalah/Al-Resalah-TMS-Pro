<?php
/**
 * Centralized PDF Library Loader
 * Handles loading of FPDF library for all PDF generation modules
 */

// Prevent multiple includes
if (defined('PDF_LIBRARY_LOADED')) {
  return;
}
define('PDF_LIBRARY_LOADED', true);

/**
 * Load FPDF library
 * Tries multiple locations and methods
 */
if (!function_exists('loadFPDFLibrary')) {
  function loadFPDFLibrary(): bool {
    // Check if FPDF is already loaded
    if (class_exists('FPDF')) {
      return true;
    }

    // Try Composer autoload (if using composer)
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
      require_once __DIR__ . '/../vendor/autoload.php';
      if (class_exists('FPDF')) {
        return true;
      }
    }

    // Try FPDF 1.86 in includes/fpdf186/
    if (file_exists(__DIR__ . '/fpdf186/fpdf.php')) {
      require_once __DIR__ . '/fpdf186/fpdf.php';
      if (class_exists('FPDF')) {
        return true;
      }
    }

    // Try FPDF.php in includes/
    if (file_exists(__DIR__ . '/FPDF.php')) {
      require_once __DIR__ . '/FPDF.php';
      if (class_exists('FPDF')) {
        return true;
      }
    }

    // Try setasign/fpdf via Composer
    if (file_exists(__DIR__ . '/../vendor/setasign/fpdf/fpdf.php')) {
      require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';
      if (class_exists('FPDF')) {
        return true;
      }
    }

    return false;
  }
}

// Attempt to load FPDF
$fpdfLoaded = loadFPDFLibrary();

if (!$fpdfLoaded) {
  error_log("FPDF library not found. PDF generation features will be disabled.");
  error_log("To install FPDF:");
  error_log("1. Download from https://www.fpdf.org/en/download.php");
  error_log("2. Extract to includes/fpdf186/");
  error_log("3. Or run: composer require setasign/fpdf");
}

/**
 * Check if FPDF is available
 */
if (!function_exists('isFPDFAvailable')) {
  function isFPDFAvailable(): bool {
    return class_exists('FPDF');
  }
}

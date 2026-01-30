<?php
/**
 * Arabic Font Loader for FPDF
 * 
 * Loads Arabic Unicode font for FPDF certificate generation.
 * This file handles font registration without changing certificate layout.
 */

// Prevent multiple includes
if (defined('ARABIC_FONT_LOADED')) {
  return;
}
define('ARABIC_FONT_LOADED', true);

/**
 * Register Arabic font in FPDF instance
 * 
 * @param FPDF $pdf FPDF instance
 * @return string|false Font name if registered, false if not available
 */
function registerArabicFont($pdf) {
  if (!isFPDFAvailable()) {
    return false;
  }
  
  $fontsDir = __DIR__ . '/fonts';
  $fontName = 'Arabic';
  
  // Try to find Arabic font files
  $fontFiles = [
    'arabic.php' => 'arabic',
    'Amiri-Regular.php' => 'Amiri-Regular',
    'Scheherazade-Regular.php' => 'Scheherazade-Regular',
    'NotoNaskhArabic-Regular.php' => 'NotoNaskhArabic-Regular',
    'DejaVuSans.php' => 'DejaVuSans'
  ];
  
  foreach ($fontFiles as $phpFile => $baseName) {
    $phpPath = "$fontsDir/$phpFile";
    $zPath = "$fontsDir/$baseName.z";
    
    if (file_exists($phpPath) && file_exists($zPath)) {
      try {
        // Register font with FPDF
        $pdf->AddFont($baseName, '', $phpPath);
        return $baseName;
      } catch (Exception $e) {
        error_log("Failed to register Arabic font $baseName: " . $e->getMessage());
        continue;
      }
    }
  }
  
  // If no font found, return false (will fallback to Arial)
  return false;
}

/**
 * Check if Arabic font is available
 * 
 * @return bool True if Arabic font files exist
 */
function isArabicFontAvailable() {
  $fontsDir = __DIR__ . '/fonts';
  $fontFiles = [
    'arabic.php',
    'Amiri-Regular.php',
    'Scheherazade-Regular.php',
    'NotoNaskhArabic-Regular.php',
    'DejaVuSans.php'
  ];
  
  foreach ($fontFiles as $file) {
    if (file_exists("$fontsDir/$file")) {
      return true;
    }
  }
  
  return false;
}

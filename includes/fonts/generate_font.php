<?php
/**
 * Font Generator Helper for Arabic Font
 * 
 * Usage:
 * php includes/fonts/generate_font.php Amiri-Regular.ttf
 * 
 * This will generate the .php and .z files needed by FPDF
 */

if (PHP_SAPI !== 'cli') {
    die("This script must be run from command line.\n");
}

if ($argc < 2) {
    echo "Usage: php generate_font.php <font-file.ttf>\n";
    echo "Example: php generate_font.php Amiri-Regular.ttf\n";
    exit(1);
}

$fontFile = $argv[1];
$fontPath = __DIR__ . '/' . $fontFile;

if (!file_exists($fontPath)) {
    echo "Error: Font file not found: $fontPath\n";
    exit(1);
}

// Load FPDF makefont utility
$makefontPath = __DIR__ . '/../vendor/setasign/fpdf/makefont/makefont.php';
if (!file_exists($makefontPath)) {
    echo "Error: FPDF makefont.php not found at: $makefontPath\n";
    exit(1);
}

require_once $makefontPath;

// Generate font files
echo "Generating font files for: $fontFile\n";
echo "Output directory: " . __DIR__ . "\n";

// Change to fonts directory for output
chdir(__DIR__);

// Run makefont (it will output files in current directory)
// Note: makefont.php expects to be run from its own directory
$originalDir = getcwd();
chdir(dirname($makefontPath));

// Include and execute makefont logic
$_SERVER['argv'] = ['makefont.php', $fontPath];
include 'makefont.php';

chdir($originalDir);

echo "\nFont generation complete!\n";
echo "Generated files should be in: " . __DIR__ . "\n";

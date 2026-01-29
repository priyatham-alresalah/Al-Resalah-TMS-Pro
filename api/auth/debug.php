<?php
// Debug script to identify the exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Debug Test</h1>";
echo "<pre>";

try {
    echo "1. Testing config.php...\n";
    require '../../includes/config.php';
    echo "   ✓ Config loaded\n";
    echo "   BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "\n";
    
    echo "\n2. Testing security_headers.php...\n";
    require '../../includes/security_headers.php';
    echo "   ✓ Security headers loaded\n";
    
    echo "\n3. Testing maintenance.php...\n";
    require '../../includes/maintenance.php';
    echo "   ✓ Maintenance loaded\n";
    
    echo "\n4. Testing rate_limit.php...\n";
    require '../../includes/rate_limit.php';
    echo "   ✓ Rate limit loaded\n";
    
    echo "\n5. Testing api_middleware.php...\n";
    require '../../includes/api_middleware.php';
    echo "   ✓ API middleware loaded\n";
    
    echo "\n6. Testing initAPIMiddleware...\n";
    $_SERVER['REQUEST_METHOD'] = 'POST';
    initAPIMiddleware('/auth/login');
    echo "   ✓ Middleware initialized\n";
    
    echo "\n✅ ALL TESTS PASSED!\n";
    
} catch (Throwable $e) {
    echo "\n❌ ERROR CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";

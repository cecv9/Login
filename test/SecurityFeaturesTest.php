<?php
/**
 * Security Features Test
 * Tests the critical security improvements:
 * 1. Rate limiting functionality
 * 2. Security headers
 * 3. CSRF validation
 * 4. Error handling
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

echo "=== Security Features Test ===\n\n";

// Test 1: Rate Limiting Methods Exist
echo "Test 1: Rate Limiting Methods in UsuarioRepository\n";
try {
    $reflection = new ReflectionClass('Enoc\Login\Repository\UsuarioRepository');
    
    $requiredMethods = [
        'recordFailedAttempt',
        'isRateLimited',
        'getRemainingAttempts',
        'clearFailedAttempts',
        'cleanupOldAttempts'
    ];
    
    $allMethodsExist = true;
    foreach ($requiredMethods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "  ✓ Method '$method' exists\n";
        } else {
            echo "  ✗ Method '$method' NOT FOUND\n";
            $allMethodsExist = false;
        }
    }
    
    if ($allMethodsExist) {
        echo "  Result: PASS - All rate limiting methods exist\n\n";
    } else {
        echo "  Result: FAIL - Some methods missing\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: AuthController has getClientIp method
echo "Test 2: AuthController Helper Methods\n";
try {
    $reflection = new ReflectionClass('Enoc\Login\Controllers\AuthController');
    
    // Check private methods using reflection
    $methods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);
    $hasGetClientIp = false;
    
    foreach ($methods as $method) {
        if ($method->getName() === 'getClientIp') {
            $hasGetClientIp = true;
            break;
        }
    }
    
    if ($hasGetClientIp) {
        echo "  ✓ Method 'getClientIp' exists\n";
        echo "  Result: PASS - Helper method exists\n\n";
    } else {
        echo "  ✗ Method 'getClientIp' NOT FOUND\n";
        echo "  Result: FAIL\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Security Headers Function Exists
echo "Test 3: Security Headers Function\n";
try {
    $indexContent = file_get_contents(__DIR__ . '/../public/index.php');
    
    $securityFeatures = [
        'function setSecurityHeaders' => 'setSecurityHeaders() function',
        'X-Frame-Options: DENY' => 'X-Frame-Options header',
        'X-Content-Type-Options: nosniff' => 'X-Content-Type-Options header',
        'Referrer-Policy: no-referrer' => 'Referrer-Policy header',
        'Content-Security-Policy:' => 'Content-Security-Policy header',
        'Strict-Transport-Security:' => 'HSTS header',
        'setSecurityHeaders()' => 'setSecurityHeaders() call'
    ];
    
    $allFeaturesExist = true;
    foreach ($securityFeatures as $pattern => $description) {
        if (strpos($indexContent, $pattern) !== false) {
            echo "  ✓ $description found\n";
        } else {
            echo "  ✗ $description NOT FOUND\n";
            $allFeaturesExist = false;
        }
    }
    
    if ($allFeaturesExist) {
        echo "  Result: PASS - All security headers implemented\n\n";
    } else {
        echo "  Result: FAIL - Some headers missing\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 4: CSRF Validation in Logout (No commented code)
echo "Test 4: CSRF Validation in Logout Method\n";
try {
    $authControllerContent = file_get_contents(__DIR__ . '/../app/Controllers/AuthController.php');
    
    // Extract logout method
    $logoutStart = strpos($authControllerContent, 'public function logout()');
    $logoutEnd = strpos($authControllerContent, 'public function showRegister()', $logoutStart);
    
    if ($logoutStart === false) {
        echo "  ✗ logout() method not found\n";
        echo "  Result: FAIL\n\n";
        exit(1);
    }
    
    $logoutMethod = substr($authControllerContent, $logoutStart, $logoutEnd - $logoutStart);
    
    // Check for commented code patterns
    $hasCommentedCode = (
        strpos($logoutMethod, '// $submittedToken') !== false ||
        strpos($logoutMethod, '// $sessionToken') !== false ||
        strpos($logoutMethod, '//if (!is_string($sessionToken)') !== false
    );
    
    // Check for proper CSRF validation (uses validateCsrf which internally uses hash_equals)
    $hasProperValidation = strpos($logoutMethod, 'validateCsrf') !== false;
    
    if (!$hasCommentedCode) {
        echo "  ✓ No commented code in logout method\n";
    } else {
        echo "  ✗ Commented code still present\n";
    }
    
    if ($hasProperValidation) {
        echo "  ✓ Proper CSRF validation (via validateCsrf)\n";
    } else {
        echo "  ✗ CSRF validation issue\n";
    }
    
    if (!$hasCommentedCode && $hasProperValidation) {
        echo "  Result: PASS - Logout method properly cleaned and secured\n\n";
    } else {
        echo "  Result: FAIL\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Production Error Handling
echo "Test 5: Production Error Handling\n";
try {
    $indexContent = file_get_contents(__DIR__ . '/../public/index.php');
    
    $errorFeatures = [
        'ini_set(\'log_errors\', \'1\')' => 'Error logging enabled',
        'ini_set(\'display_errors\', \'0\')' => 'Display errors disabled in production',
        'LogManager::logError' => 'LogManager error logging',
        '$appDebug' => 'Debug mode check'
    ];
    
    $allFeaturesExist = true;
    foreach ($errorFeatures as $pattern => $description) {
        if (strpos($indexContent, $pattern) !== false) {
            echo "  ✓ $description found\n";
        } else {
            echo "  ✗ $description NOT FOUND\n";
            $allFeaturesExist = false;
        }
    }
    
    // Check that old debug code is removed from AuthController
    $authContent = file_get_contents(__DIR__ . '/../app/Controllers/AuthController.php');
    $hasOldDebug = (
        strpos($authContent, 'echo "<pre style=') !== false ||
        strpos($authContent, 'var_dump($email') !== false ||
        strpos($authContent, 'DEBUG TEMPORAL') !== false
    );
    
    if (!$hasOldDebug) {
        echo "  ✓ Debug code removed from AuthController\n";
    } else {
        echo "  ✗ Debug code still present in AuthController\n";
        $allFeaturesExist = false;
    }
    
    if ($allFeaturesExist) {
        echo "  Result: PASS - Production error handling properly configured\n\n";
    } else {
        echo "  Result: FAIL - Some features missing\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 6: Migration File Exists
echo "Test 6: Database Migration File\n";
try {
    $migrationFile = __DIR__ . '/../database/migrations/001_create_login_attempts_table.sql';
    
    if (file_exists($migrationFile)) {
        echo "  ✓ Migration file exists\n";
        
        $migrationContent = file_get_contents($migrationFile);
        $requiredElements = [
            'CREATE TABLE IF NOT EXISTS login_attempts' => 'Table creation',
            'identifier VARCHAR(255)' => 'Identifier column',
            'attempt_type ENUM(\'login\', \'register\')' => 'Attempt type column',
            'attempted_at TIMESTAMP' => 'Timestamp column',
            'ip_address VARCHAR(45)' => 'IP address column',
            'INDEX idx_identifier_type' => 'Identifier index',
            'INDEX idx_attempted_at' => 'Timestamp index'
        ];
        
        $allElementsExist = true;
        foreach ($requiredElements as $pattern => $description) {
            if (strpos($migrationContent, $pattern) !== false) {
                echo "  ✓ $description found\n";
            } else {
                echo "  ✗ $description NOT FOUND\n";
                $allElementsExist = false;
            }
        }
        
        if ($allElementsExist) {
            echo "  Result: PASS - Migration file properly structured\n\n";
        } else {
            echo "  Result: FAIL - Migration incomplete\n\n";
            exit(1);
        }
    } else {
        echo "  ✗ Migration file not found\n";
        echo "  Result: FAIL\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "=== All Security Tests Passed! ===\n";
echo "\nSummary:\n";
echo "✓ Rate limiting methods implemented in repository\n";
echo "✓ Security headers function created and applied globally\n";
echo "✓ CSRF validation in logout properly implemented\n";
echo "✓ Production error handling configured\n";
echo "✓ Database migration created\n";
echo "✓ Debug code removed from production code\n";
echo "\nAll critical security improvements have been successfully implemented!\n";

exit(0);

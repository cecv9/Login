# Security Improvements Documentation

This document describes the critical security enhancements implemented in the Login application following the security audit.

## Overview

All changes follow:
- **OWASP Security Best Practices**
- **Single Responsibility Principle (SRP)**
- **Dependency Injection Pattern**
- **Backward Compatibility**

## 1. Rate Limiting for Login and Registration

### Purpose
Prevent brute force attacks by limiting the number of failed authentication attempts.

### Implementation

#### Database Table
New table `login_attempts` tracks failed attempts:
- Migration: `database/migrations/001_create_login_attempts_table.sql`
- Indexes on identifier, attempt_type, and timestamp for performance

#### Repository Methods (UsuarioRepository)
```php
// Record a failed attempt
public function recordFailedAttempt(string $identifier, string $attemptType, string $ipAddress): bool

// Check if rate limited (returns true if blocked)
public function isRateLimited(string $identifier, string $attemptType, int $maxAttempts = 5, int $windowMinutes = 15): bool

// Get remaining attempts before rate limit
public function getRemainingAttempts(string $identifier, string $attemptType, int $maxAttempts = 5, int $windowMinutes = 15): int

// Clear attempts after successful login/registration
public function clearFailedAttempts(string $identifier, string $attemptType): bool

// Cleanup old attempts (for periodic maintenance)
public function cleanupOldAttempts(int $olderThanHours = 24): int
```

#### Rate Limits
- **Login**: 5 attempts per email, 10 attempts per IP in 15 minutes
- **Register**: 5 attempts per email, 10 attempts per IP in 15 minutes

#### Integration
Rate limiting is checked in:
- `AuthController::processLogin()` - Before authentication
- `AuthController::processRegister()` - Before validation

Failed attempts are recorded for both email and IP address, providing defense in depth.

### Maintenance
Old attempts should be cleaned periodically:
```php
// In a cron job or scheduled task
$repository->cleanupOldAttempts(24); // Remove attempts older than 24 hours
```

## 2. Global Security Headers

### Purpose
Protect against common web vulnerabilities (XSS, clickjacking, MIME sniffing).

### Implementation
New function `setSecurityHeaders()` in `public/index.php` applies security headers to **all responses**, not just errors.

#### Headers Applied
```php
X-Frame-Options: DENY                    // Prevent clickjacking
X-Content-Type-Options: nosniff          // Prevent MIME sniffing
Referrer-Policy: no-referrer             // Privacy protection
Content-Security-Policy: ...             // XSS protection
X-XSS-Protection: 1; mode=block          // Legacy XSS protection
Strict-Transport-Security: ...           // Force HTTPS (only over HTTPS)
Permissions-Policy: ...                  // Disable unnecessary browser features
```

#### CSP Policy
```
default-src 'self';
style-src 'self' 'unsafe-inline';  // Allows inline styles for compatibility
script-src 'self';
img-src 'self' data:;
font-src 'self';
object-src 'none';
frame-ancestors 'none';
```

#### HSTS
Strict-Transport-Security header is only sent over HTTPS to comply with browser requirements:
- `max-age=31536000` (1 year)
- `includeSubDomains` directive included

### Changes
- Headers applied once globally via `setSecurityHeaders()` call
- Duplicate headers removed from error handling code
- Function respects CLI mode (no headers in command-line context)

## 3. CSRF Validation in Logout

### Purpose
Ensure logout requests are legitimate and prevent CSRF attacks.

### Implementation
Cleaned up `AuthController::logout()` method:
- Removed all commented/debug code
- Uses `validateCsrf()` method which internally uses `hash_equals()` for timing-safe comparison
- Returns proper HTTP 400 error on invalid token

#### Code
```php
public function logout(): void
{
    // Check HTTP method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }

    // Validate CSRF token (uses hash_equals internally)
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    // Destroy session securely
    // ...
}
```

### Security Notes
- Token validation uses timing-safe comparison (`hash_equals()`)
- No information leakage in error messages
- Session properly destroyed including cookies

## 4. Production Error Handling

### Purpose
Hide sensitive information in production while ensuring errors are logged for debugging.

### Implementation

#### Error Configuration (index.php)
```php
// Development mode
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}
// Production mode
else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');        // Hide from users
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');            // Log internally
}
```

#### Error Display
- **Development**: Full error details, stack trace, file/line
- **Production**: Generic error message, no sensitive information

#### Logging
All errors are logged via `LogManager::logError()` regardless of display mode:
```php
LogManager::logError('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
```

#### Debug Code Removal
- Removed commented debug code from `AuthController::processLogin()`
- Removed `var_dump()`, `echo`, and temporary debugging statements
- Cleaned up error messages (e.g., "Credenciales incorrectas xd" → "Credenciales incorrectas")

## Testing

Run the security test suite:
```bash
php test/SecurityFeaturesTest.php
```

Tests verify:
1. Rate limiting methods exist and are properly implemented
2. Security headers function exists and is called
3. CSRF validation in logout is clean and secure
4. Production error handling is configured correctly
5. Database migration file is valid
6. Debug code has been removed

## Migration Steps

### 1. Apply Database Migration
```bash
mysql -u username -p database_name < database/migrations/001_create_login_attempts_table.sql
```

Or use your preferred database management tool.

### 2. Verify Configuration
Ensure `.env` file has proper settings:
```env
APP_DEBUG=false  # In production
APP_TZ=UTC
```

### 3. Test Rate Limiting
Try multiple failed login attempts to verify rate limiting works:
- After 5 failed attempts with same email, should be blocked
- After 10 failed attempts from same IP, should be blocked
- Wait 15 minutes or clear attempts to reset

### 4. Verify Security Headers
Check response headers using browser DevTools or curl:
```bash
curl -I https://your-domain.com/
```

Should see all security headers applied.

## Maintenance Tasks

### Regular Cleanup
Add to cron job (daily recommended):
```php
<?php
require 'vendor/autoload.php';

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Repository\UsuarioRepository;

$connection = new PdoConnection($dbConfig);
$repository = new UsuarioRepository($connection);

// Clean up attempts older than 24 hours
$deleted = $repository->cleanupOldAttempts(24);
echo "Cleaned up $deleted old login attempts\n";
```

### Monitor Rate Limiting
Query login attempts to detect potential attacks:
```sql
SELECT identifier, attempt_type, COUNT(*) as attempts, MAX(attempted_at) as last_attempt
FROM login_attempts
WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY identifier, attempt_type
HAVING attempts > 5
ORDER BY attempts DESC;
```

## Security Considerations

1. **Rate Limiting**
   - Tracks both email and IP to prevent circumvention
   - Fails open on database errors (doesn't block legitimate users)
   - Configurable limits per authentication type

2. **Security Headers**
   - Applied globally to all responses
   - CSP allows inline styles for compatibility (adjust if needed)
   - HSTS only sent over HTTPS (browser requirement)

3. **CSRF Protection**
   - Timing-safe comparison prevents timing attacks
   - Token regenerated after authentication
   - Validated on all state-changing operations

4. **Error Handling**
   - Sensitive information never exposed in production
   - All errors logged for debugging
   - Generic error messages prevent information disclosure

## Backward Compatibility

All changes maintain backward compatibility:
- Existing API unchanged
- New methods added to repository (no breaking changes)
- Security headers don't affect functionality
- Error handling respects debug mode
- Rate limiting gracefully handles database errors

## References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [CSP Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [HSTS Preload](https://hstspreload.org/)

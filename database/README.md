# Database Migrations

This directory contains SQL migration files for the Login application.

## Running Migrations

### Option 1: Using the Migration Script (Recommended)

The easiest way to apply migrations:

```bash
# Apply all pending migrations
php database/migrate.php

# Apply a specific migration
php database/migrate.php 001

# List available migrations
php database/migrate.php --list
```

The script will:
- Track which migrations have been applied
- Skip already-applied migrations
- Show clear progress and errors
- Create a `migrations` table to track state

### Option 2: Manual SQL Execution

```bash
# Using mysql command line
mysql -u your_username -p your_database < migrations/001_create_login_attempts_table.sql
```

Or import via your database management tool (phpMyAdmin, MySQL Workbench, etc.).

## Available Migrations

### 001_create_login_attempts_table.sql
Creates the `login_attempts` table for rate limiting functionality.

**Purpose:** Track failed login and registration attempts to prevent brute force attacks.

**Features:**
- Tracks attempts by email or IP address
- Supports both 'login' and 'register' attempt types
- Includes timestamp indexing for efficient queries
- Can store user agent information for audit purposes

**Rate Limiting Configuration:**
- Default: 5 attempts per email in 15 minutes
- Default: 10 attempts per IP in 15 minutes

**Maintenance:**
Old attempts (older than 24 hours) can be cleaned up using the repository method:
```php
$repository->cleanupOldAttempts(24); // Remove attempts older than 24 hours
```

## Schema

```sql
login_attempts
├── id (INT, PRIMARY KEY, AUTO_INCREMENT)
├── identifier (VARCHAR(255), NOT NULL) - Email or IP address
├── attempt_type (ENUM('login', 'register'), NOT NULL)
├── attempted_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
├── ip_address (VARCHAR(45), NOT NULL) - IPv4 or IPv6
└── user_agent (VARCHAR(500), NULL)

Indexes:
- idx_identifier_type (identifier, attempt_type)
- idx_attempted_at (attempted_at)
- idx_ip_address (ip_address)
```

#!/usr/bin/env php
<?php
/**
 * Database Migration Script
 * Simple script to apply migrations to the database
 * 
 * Usage:
 *   php database/migrate.php                    # Apply all pending migrations
 *   php database/migrate.php 001                # Apply specific migration
 *   php database/migrate.php --list             # List available migrations
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Enoc\Login\Core\DatabaseConnectionException;

// Load environment variables
$rootPath = dirname(__DIR__);
Dotenv::createImmutable($rootPath)->safeLoad();

// Parse command line arguments
$command = $argv[1] ?? 'apply';

// Get database configuration
$dbConfig = [
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_NAME'] ?? '',
    'user'     => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

// Validate configuration
if (empty($dbConfig['database'])) {
    echo "Error: DB_NAME not configured in .env file\n";
    exit(1);
}

// List available migrations
$migrationsDir = __DIR__ . '/migrations';
$migrations = glob($migrationsDir . '/*.sql');
sort($migrations);

if ($command === '--list' || $command === 'list') {
    echo "Available migrations:\n";
    foreach ($migrations as $migration) {
        echo "  - " . basename($migration) . "\n";
    }
    exit(0);
}

// Connect to database
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "Connected to database: {$dbConfig['database']}\n\n";
} catch (PDOException $e) {
    echo "Error connecting to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Create migrations tracking table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    echo "Error creating migrations table: " . $e->getMessage() . "\n";
    exit(1);
}

// Get already applied migrations
$stmt = $pdo->query("SELECT migration FROM migrations");
$appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Apply migrations
$applied = 0;
$skipped = 0;

foreach ($migrations as $migrationFile) {
    $migrationName = basename($migrationFile);
    
    // Check if specific migration requested
    if ($command !== 'apply' && strpos($migrationName, $command) === false) {
        continue;
    }
    
    // Skip if already applied
    if (in_array($migrationName, $appliedMigrations)) {
        echo "⏭️  Skipping (already applied): $migrationName\n";
        $skipped++;
        continue;
    }
    
    // Read migration file
    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        echo "❌ Error reading migration file: $migrationName\n";
        continue;
    }
    
    // Apply migration
    try {
        echo "🔄 Applying: $migrationName\n";
        
        // Execute the migration SQL
        $pdo->exec($sql);
        
        // Record that migration was applied
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
        $stmt->execute(['migration' => $migrationName]);
        
        echo "✅ Applied successfully: $migrationName\n\n";
        $applied++;
    } catch (PDOException $e) {
        echo "❌ Error applying migration: " . $e->getMessage() . "\n\n";
        // Continue with next migration instead of failing completely
    }
}

// Summary
echo "─────────────────────────────────\n";
echo "Migration Summary:\n";
echo "  Applied: $applied\n";
echo "  Skipped: $skipped\n";
echo "  Total migrations: " . count($migrations) . "\n";

if ($applied > 0) {
    echo "\n✨ Database migrations completed successfully!\n";
    exit(0);
} else {
    echo "\nℹ️  No new migrations to apply.\n";
    exit(0);
}

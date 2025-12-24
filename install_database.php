<?php
/**
 * Database Installation Script
 * Run this ONCE to create database and tables
 * 
 * Usage: php install_database.php
 */

// Step 1: Create database and user
echo "=== CRM Database Installation ===\n\n";

echo "Step 1: Creating database and user...\n";
echo "Run these commands in MySQL as root:\n\n";

echo "mysql -u root -p\n\n";

echo "CREATE DATABASE IF NOT EXISTS crm_coldwell_banker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "CREATE USER IF NOT EXISTS 'crm_user'@'localhost' IDENTIFIED BY 'CRM_cb2025!Secure';\n";
echo "GRANT ALL PRIVILEGES ON crm_coldwell_banker.* TO 'crm_user'@'localhost';\n";
echo "FLUSH PRIVILEGES;\n";
echo "EXIT;\n\n";

echo "After running the above commands, press ENTER to continue...\n";
if (php_sapi_name() === 'cli') {
    fgets(STDIN);
}

// Step 2: Import schema
echo "\nStep 2: Importing schema...\n";

try {
    require_once __DIR__ . '/config/database.php';
    
    $pdo = getDB();
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    $pdo->commit();
    
    echo "✅ Database created successfully!\n";
    echo "✅ Tables created successfully!\n";
    echo "✅ Initial services data inserted!\n\n";
    
    // Verify
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Created tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    echo "\n✅ Installation complete!\n";
    echo "\nDatabase: crm_coldwell_banker\n";
    echo "User: crm_user\n";
    echo "Tables: " . count($tables) . "\n";
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

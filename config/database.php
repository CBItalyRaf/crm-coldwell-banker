<?php
/**
 * Database Configuration
 * CRM Coldwell Banker Italy
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_coldwell_banker');
define('DB_USER', 'crm_user');
define('DB_PASS', 'CRM_cb2025!Secure');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO Database Connection
 * 
 * @return PDO
 * @throws PDOException
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Errore connessione database");
        }
    }
    
    return $pdo;
}

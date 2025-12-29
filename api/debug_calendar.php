<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../check_auth.php';
require_once '../config/database.php';
require_once '../helpers/scadenze.php';

$pdo = getDB();

echo "<h1>DEBUG API CALENDARIO</h1>";

// Test 1: Servizi
echo "<h2>1. SERVIZI</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM agency_services ags
        WHERE ags.is_active = 1 AND ags.expiration_date IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✓ Servizi con scadenza: " . $result['total'] . "<br>";
} catch (Exception $e) {
    echo "✗ ERRORE SERVIZI: " . $e->getMessage() . "<br>";
}

// Test 2: Contratti
echo "<h2>2. CONTRATTI</h2>";
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'agency_contracts'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabella agency_contracts esiste<br>";
        
        $stmt = $pdo->prepare("DESCRIBE agency_contracts");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        echo "<strong>Colonne disponibili:</strong><br>";
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})<br>";
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agency_contracts");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<br>Totale contratti: " . $result['total'] . "<br>";
        
    } else {
        echo "✗ Tabella agency_contracts NON ESISTE<br>";
    }
} catch (Exception $e) {
    echo "✗ ERRORE CONTRATTI: " . $e->getMessage() . "<br>";
}

// Test 3: Query completa
echo "<h2>3. TEST API COMPLETA</h2>";
try {
    include '../api/scadenze_calendar.php';
} catch (Exception $e) {
    echo "✗ ERRORE API: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

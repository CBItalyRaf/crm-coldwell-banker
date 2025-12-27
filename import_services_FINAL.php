<?php
/**
 * Import servizi agenzie da CSV PULITO a database
 * USA SOLO CSV generato da clean_notion_csv_DEFINITIVO.py
 * Esegui: php import_services_FINAL.php /root/agenzie_clean.csv
 */

require_once 'config/database.php';

echo "=== IMPORT SERVIZI AGENZIE ===\n\n";

// Path CSV
if (isset($argv[1])) {
    $csv_file = $argv[1];
} else {
    $csv_file = readline("Inserisci il path del CSV agenzie PULITO: ");
}

if (!file_exists($csv_file)) {
    die("❌ File non trovato: $csv_file\n");
}

echo "✅ File trovato\n";
echo "📖 Lettura CSV...\n\n";

$pdo = getDB();

// Leggi CSV
$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle); // Prima riga = headers

echo "📋 Colonne servizi trovate:\n";
$service_columns = ['cb_suite_status', 'canva_status', 'regold_activation', 'james_status', 'docudrop_activation', 'unique_status'];
foreach ($headers as $idx => $header) {
    if (in_array($header, $service_columns)) {
        echo "  [$idx] $header\n";
    }
}

echo "\n";
$confirm = readline("Vuoi procedere con l'import? (si/no): ");

if (strtolower($confirm) !== 'si') {
    die("❌ Import annullato\n");
}

// Helper functions
function parseDate($value) {
    if (empty($value) || $value === 'Empty') return null;
    
    // Formato DD/MM/YYYY
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    // Formato YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    
    return null;
}

function isActive($value) {
    if (empty($value) || $value === 'Empty') return 0;
    $value = strtolower(trim($value));
    return in_array($value, ['attivo', 'active', 'si', 'yes', '1', 'true']) ? 1 : 0;
}

function getColumnValue($row, $headers, $columnName) {
    $index = array_search($columnName, $headers);
    return $index !== false ? trim($row[$index]) : '';
}

// Import
$imported = 0;
$errors = 0;
$row_num = 0;

while (($row = fgetcsv($handle)) !== false) {
    $row_num++;
    
    $code = getColumnValue($row, $headers, 'code');
    
    if (empty($code)) {
        continue;
    }
    
    // Trova agency_id
    $stmt = $pdo->prepare("SELECT id FROM agencies WHERE code = ?");
    $stmt->execute([$code]);
    $agency = $stmt->fetch();
    
    if (!$agency) {
        echo "⚠️  Riga $row_num: Agenzia $code non trovata nel database\n";
        $errors++;
        continue;
    }
    
    $agency_id = $agency['id'];
    
    // CB SUITE
    $cb_suite_status = getColumnValue($row, $headers, 'cb_suite_status');
    if (!empty($cb_suite_status) && $cb_suite_status !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date, renewal_required, invoice_reference, notes)
            VALUES (?, 'cb_suite', ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active),
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date),
                renewal_required = VALUES(renewal_required),
                invoice_reference = VALUES(invoice_reference),
                notes = VALUES(notes)
        ");
        $stmt->execute([
            $agency_id,
            isActive($cb_suite_status),
            parseDate(getColumnValue($row, $headers, 'cb_suite_start')),
            parseDate(getColumnValue($row, $headers, 'cb_suite_end')),
            getColumnValue($row, $headers, 'cb_suite_renewal'),
            getColumnValue($row, $headers, 'cb_suite_invoice'),
            getColumnValue($row, $headers, 'cb_suite_notes')
        ]);
        $imported++;
    }
    
    // CANVA
    $canva_status = getColumnValue($row, $headers, 'canva_status');
    if (!empty($canva_status) && $canva_status !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active)
            VALUES (?, 'canva', ?)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)
        ");
        $stmt->execute([$agency_id, isActive($canva_status)]);
        $imported++;
    }
    
    // REGOLD
    $regold_activation = getColumnValue($row, $headers, 'regold_activation');
    if (!empty($regold_activation) && $regold_activation !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date, invoice_reference)
            VALUES (?, 'regold', 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date),
                invoice_reference = VALUES(invoice_reference)
        ");
        $stmt->execute([
            $agency_id,
            parseDate($regold_activation),
            parseDate(getColumnValue($row, $headers, 'regold_expiration')),
            getColumnValue($row, $headers, 'regold_invoice')
        ]);
        $imported++;
    }
    
    // JAMES EDITION
    $james_status = getColumnValue($row, $headers, 'james_status');
    if (!empty($james_status) && $james_status !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active, expiration_date)
            VALUES (?, 'james_edition', ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active),
                expiration_date = VALUES(expiration_date)
        ");
        $stmt->execute([
            $agency_id,
            isActive($james_status),
            parseDate(getColumnValue($row, $headers, 'james_expiration'))
        ]);
        $imported++;
    }
    
    // DOCUDROP
    $docudrop_activation = getColumnValue($row, $headers, 'docudrop_activation');
    if (!empty($docudrop_activation) && $docudrop_activation !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date)
            VALUES (?, 'docudrop', 1, ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                activation_date = VALUES(activation_date),
                expiration_date = VALUES(expiration_date)
        ");
        $stmt->execute([
            $agency_id,
            parseDate($docudrop_activation),
            parseDate(getColumnValue($row, $headers, 'docudrop_expiration'))
        ]);
        $imported++;
    }
    
    // UNIQUE
    $unique_status = getColumnValue($row, $headers, 'unique_status');
    if (!empty($unique_status) && $unique_status !== 'Empty') {
        $stmt = $pdo->prepare("
            INSERT INTO agency_services (agency_id, service_name, is_active)
            VALUES (?, 'unique', ?)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)
        ");
        $stmt->execute([$agency_id, isActive($unique_status)]);
        $imported++;
    }
}

fclose($handle);

echo "\n✅ IMPORT COMPLETATO!\n";
echo "   Servizi importati: $imported\n";
echo "   Errori: $errors\n";

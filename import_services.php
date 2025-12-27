<?php
/**
 * Import servizi agenzie da CSV a database
 * Esegui: php import_services.php
 */

require_once 'config/database.php';

echo "=== IMPORT SERVIZI AGENZIE ===\n\n";

// Path CSV
$csv_file = readline("Inserisci il path del CSV agenzie: ");

if (!file_exists($csv_file)) {
    die("❌ File non trovato: $csv_file\n");
}

echo "✅ File trovato\n";
echo "📖 Lettura CSV...\n\n";

$pdo = getDB();

// Leggi CSV
$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle); // Prima riga = headers

echo "📋 Colonne trovate nel CSV:\n";
foreach ($headers as $idx => $header) {
    if (stripos($header, 'suite') !== false || 
        stripos($header, 'canva') !== false || 
        stripos($header, 'regold') !== false || 
        stripos($header, 'james') !== false || 
        stripos($header, 'docudrop') !== false || 
        stripos($header, 'unique') !== false) {
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
    try {
        $date = new DateTime($value);
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function isActive($value) {
    if (empty($value) || $value === 'Empty') return 0;
    $value = strtolower(trim($value));
    return in_array($value, ['attivo', 'active', 'si', 'yes', '1', 'true']) ? 1 : 0;
}

function getColumnValue($row, $headers, $columnName) {
    $index = array_search($columnName, $headers);
    return $index !== false ? $row[$index] : '';
}

// Import
$imported = 0;
$errors = 0;
$row_num = 0;

while (($row = fgetcsv($handle)) !== false) {
    $row_num++;
    
    $code = getColumnValue($row, $headers, 'Codice') ?: getColumnValue($row, $headers, 'code');
    
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
    $cb_suite_status = getColumnValue($row, $headers, 'CB Suite (EuroMq/iRealtors)') ?: getColumnValue($row, $headers, 'CB Suite');
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
            parseDate(getColumnValue($row, $headers, 'CB Suite dal')),
            parseDate(getColumnValue($row, $headers, 'CB Suite al')),
            getColumnValue($row, $headers, 'Obbligo rinnovo?'),
            getColumnValue($row, $headers, 'Fattura CB Suite'),
            getColumnValue($row, $headers, 'NOTE')
        ]);
        $imported++;
    }
    
    // CANVA
    $canva_status = getColumnValue($row, $headers, 'CANVA');
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
    $regold_activation = getColumnValue($row, $headers, 'ATTIVAZIONE REGOLD');
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
            parseDate(getColumnValue($row, $headers, 'SCADENZA REGOLD')),
            getColumnValue($row, $headers, 'Fattura Regold')
        ]);
        $imported++;
    }
    
    // JAMES EDITION
    $james_status = getColumnValue($row, $headers, 'JamesEdition');
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
            parseDate(getColumnValue($row, $headers, 'SCADENZA JAMESEDITION'))
        ]);
        $imported++;
    }
    
    // DOCUDROP
    $docudrop_activation = getColumnValue($row, $headers, 'ATTIVAZIONE DOCUDROP');
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
            parseDate(getColumnValue($row, $headers, 'SCADENZA DOCUDROP'))
        ]);
        $imported++;
    }
    
    // UNIQUE
    $unique_status = getColumnValue($row, $headers, 'Attivazione Unique');
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

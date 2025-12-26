<?php
/**
 * Script Import CSV nel Database CRM
 * VERSIONE RELAZIONALE CORRETTA
 * - Usa agency_id (INT) con lookup da agency_code
 * - Foreign key constraint mantenuta
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "================================================================================\n";
echo "IMPORT CSV NEL DATABASE CRM COLDWELL BANKER\n";
echo "Con lookup agency_code → agency_id\n";
echo "================================================================================\n\n";

// Configurazione
$agenzieFile = 'agenzie_clean.csv';
$agentiFile = 'agenti_clean.csv';

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'crm_coldwell_banker',
    'username' => 'crm_user',
    'password' => 'CRM_cb2025!Secure',
    'charset' => 'utf8mb4'
];

// Connessione Database
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "✅ Connessione database riuscita!\n\n";
} catch (PDOException $e) {
    die("❌ ERRORE Connessione Database: " . $e->getMessage() . "\n");
}

// Funzioni Helper
function csvToArray($filename) {
    if (!file_exists($filename)) {
        throw new Exception("File non trovato: $filename");
    }
    
    $rows = [];
    $handle = fopen($filename, 'r');
    $header = fgetcsv($handle);
    
    while (($data = fgetcsv($handle)) !== false) {
        $row = array_combine($header, $data);
        $rows[] = $row;
    }
    
    fclose($handle);
    return $rows;
}

function emptyToNull($value) {
    return ($value === '' || $value === 'NULL' || $value === 'nan') ? null : $value;
}

function boolToInt($value) {
    if ($value === 'True' || $value === 'TRUE' || $value === '1' || $value === 1 || $value === true) {
        return 1;
    }
    if ($value === 'False' || $value === 'FALSE' || $value === '0' || $value === 0 || $value === false) {
        return 0;
    }
    return null;
}

// ============================================================================
// IMPORT AGENZIE
// ============================================================================

function importAgenzie($pdo, $filename) {
    echo "=== IMPORT AGENZIE ===\n";
    echo "Caricamento $filename...\n";
    
    $rows = csvToArray($filename);
    echo "Trovate " . count($rows) . " agenzie nel CSV\n\n";
    
    $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];
    
    $insertSql = "INSERT INTO agencies (
        code, name, type, status, agency_size,
        broker_manager, broker_mobile, legal_representative,
        address, legal_address, city, province, zip_code,
        email, phone, pec, website,
        vat_number, tax_code, company_name, rea, sdi_code,
        sold_date, activation_date, closed_date, 
        contract_duration_years, contract_expiry, renewals, tech_fee,
        notes, tech_fee_notes, contract_notes,
        data_incomplete, created_at, updated_at
    ) VALUES (
        :code, :name, :type, :status, :agency_size,
        :broker_manager, :broker_mobile, :legal_representative,
        :address, :legal_address, :city, :province, :zip_code,
        :email, :phone, :pec, :website,
        :vat_number, :tax_code, :company_name, :rea, :sdi_code,
        :sold_date, :activation_date, :closed_date,
        :contract_duration_years, :contract_expiry, :renewals, :tech_fee,
        :notes, :tech_fee_notes, :contract_notes,
        :data_incomplete, NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        type = VALUES(type),
        status = VALUES(status),
        agency_size = VALUES(agency_size),
        broker_manager = VALUES(broker_manager),
        broker_mobile = VALUES(broker_mobile),
        legal_representative = VALUES(legal_representative),
        address = VALUES(address),
        legal_address = VALUES(legal_address),
        city = VALUES(city),
        province = VALUES(province),
        zip_code = VALUES(zip_code),
        email = VALUES(email),
        phone = VALUES(phone),
        pec = VALUES(pec),
        website = VALUES(website),
        vat_number = VALUES(vat_number),
        tax_code = VALUES(tax_code),
        company_name = VALUES(company_name),
        rea = VALUES(rea),
        sdi_code = VALUES(sdi_code),
        sold_date = VALUES(sold_date),
        activation_date = VALUES(activation_date),
        closed_date = VALUES(closed_date),
        contract_duration_years = VALUES(contract_duration_years),
        contract_expiry = VALUES(contract_expiry),
        renewals = VALUES(renewals),
        tech_fee = VALUES(tech_fee),
        notes = VALUES(notes),
        tech_fee_notes = VALUES(tech_fee_notes),
        contract_notes = VALUES(contract_notes),
        data_incomplete = VALUES(data_incomplete),
        updated_at = NOW()";
    
    $stmt = $pdo->prepare($insertSql);
    
    foreach ($rows as $i => $row) {
        try {
            if (empty($row['code'])) {
                $stats['skipped']++;
                continue;
            }
            
            $checkStmt = $pdo->prepare("SELECT id FROM agencies WHERE code = ?");
            $checkStmt->execute([$row['code']]);
            $exists = $checkStmt->fetch();
            
            $data = [
                'code' => $row['code'],
                'name' => emptyToNull($row['name']),
                'type' => emptyToNull($row['type']) ?: 'Standard',
                'status' => emptyToNull($row['status']) ?: 'Active',
                'agency_size' => emptyToNull($row['agency_size']),
                'broker_manager' => emptyToNull($row['broker_manager']),
                'broker_mobile' => emptyToNull($row['broker_mobile']),
                'legal_representative' => emptyToNull($row['legal_representative']),
                'address' => emptyToNull($row['address']),
                'legal_address' => emptyToNull($row['legal_address']),
                'city' => emptyToNull($row['city']),
                'province' => emptyToNull($row['province']),
                'zip_code' => emptyToNull($row['zip_code']),
                'email' => emptyToNull($row['email']),
                'phone' => emptyToNull($row['phone']),
                'pec' => emptyToNull($row['pec']),
                'website' => emptyToNull($row['website']),
                'vat_number' => emptyToNull($row['vat_number']),
                'tax_code' => emptyToNull($row['tax_code']),
                'company_name' => emptyToNull($row['company_name']),
                'rea' => emptyToNull($row['rea']),
                'sdi_code' => emptyToNull($row['sdi_code']),
                'sold_date' => emptyToNull($row['sold_date']),
                'activation_date' => emptyToNull($row['activation_date']),
                'closed_date' => emptyToNull($row['closed_date']),
                'contract_duration_years' => emptyToNull($row['contract_duration_years']),
                'contract_expiry' => emptyToNull($row['contract_expiry']),
                'renewals' => emptyToNull($row['renewals']),
                'tech_fee' => emptyToNull($row['tech_fee']),
                'notes' => emptyToNull($row['notes']),
                'tech_fee_notes' => emptyToNull($row['tech_fee_notes']),
                'contract_notes' => emptyToNull($row['contract_notes']),
                'data_incomplete' => boolToInt($row['data_incomplete']) ?? 0,
            ];
            
            $stmt->execute($data);
            
            if ($exists) {
                echo "🔄 UPDATE: {$row['code']} - {$row['name']}\n";
                $stats['updated']++;
            } else {
                echo "✅ INSERT: {$row['code']} - {$row['name']}\n";
                $stats['inserted']++;
            }
            
        } catch (PDOException $e) {
            echo "❌ ERRORE Riga " . ($i + 2) . " ({$row['code']}): " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n--- REPORT AGENZIE ---\n";
    echo "✅ Inserite:   {$stats['inserted']}\n";
    echo "🔄 Aggiornate: {$stats['updated']}\n";
    echo "⚠️  Saltate:    {$stats['skipped']}\n";
    echo "❌ Errori:     {$stats['errors']}\n";
    echo "TOTALE:       " . count($rows) . "\n\n";
    
    return $stats;
}

// ============================================================================
// IMPORT AGENTI - CON LOOKUP agency_code → agency_id
// ============================================================================

function importAgenti($pdo, $filename) {
    echo "=== IMPORT AGENTI ===\n";
    echo "Caricamento $filename...\n";
    
    $rows = csvToArray($filename);
    echo "Trovati " . count($rows) . " agenti nel CSV\n\n";
    
    $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'agency_not_found' => 0];
    
    // Cache per lookup agency_code → agency_id
    echo "Caricamento cache agenzie...\n";
    $agencyCache = [];
    $cacheStmt = $pdo->query("SELECT id, code FROM agencies");
    while ($row = $cacheStmt->fetch()) {
        $agencyCache[$row['code']] = $row['id'];
    }
    echo "Cache caricata: " . count($agencyCache) . " agenzie\n\n";
    
    $insertSql = "INSERT INTO agents (
        agency_id, first_name, last_name, mobile,
        email_corporate, email_personal, m365_plan,
        email_activation_date, email_expiry_date,
        role, status, inserted_at, notes, data_incomplete,
        created_at, updated_at
    ) VALUES (
        :agency_id, :first_name, :last_name, :mobile,
        :email_corporate, :email_personal, :m365_plan,
        :email_activation_date, :email_expiry_date,
        :role, :status, :inserted_at, :notes, :data_incomplete,
        NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        agency_id = VALUES(agency_id),
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        mobile = VALUES(mobile),
        email_corporate = VALUES(email_corporate),
        email_personal = VALUES(email_personal),
        m365_plan = VALUES(m365_plan),
        email_activation_date = VALUES(email_activation_date),
        email_expiry_date = VALUES(email_expiry_date),
        role = VALUES(role),
        status = VALUES(status),
        inserted_at = VALUES(inserted_at),
        notes = VALUES(notes),
        data_incomplete = VALUES(data_incomplete),
        updated_at = NOW()";
    
    $stmt = $pdo->prepare($insertSql);
    
    foreach ($rows as $i => $row) {
        try {
            if (empty($row['last_name'])) {
                $stats['skipped']++;
                continue;
            }
            
            // LOOKUP: agency_code → agency_id
            $agencyCode = emptyToNull($row['agency_code']);
            $agencyId = null;
            
            if ($agencyCode && isset($agencyCache[$agencyCode])) {
                $agencyId = $agencyCache[$agencyCode];
            } else {
                if ($agencyCode) {
                    echo "⚠️  Agenzia non trovata: {$agencyCode} per {$row['first_name']} {$row['last_name']}\n";
                    $stats['agency_not_found']++;
                }
                // Continua senza agency_id (sarà NULL)
            }
            
            $exists = false;
            if (!empty($row['email_corporate'])) {
                $checkStmt = $pdo->prepare("SELECT id FROM agents WHERE email_corporate = ?");
                $checkStmt->execute([$row['email_corporate']]);
                $exists = $checkStmt->fetch();
            }
            
            $data = [
                'agency_id' => $agencyId,
                'first_name' => emptyToNull($row['first_name']),
                'last_name' => emptyToNull($row['last_name']),
                'mobile' => emptyToNull($row['mobile']),
                'email_corporate' => emptyToNull($row['email_corporate']),
                'email_personal' => emptyToNull($row['email_personal']),
                'm365_plan' => emptyToNull($row['m365_plan']),
                'email_activation_date' => emptyToNull($row['email_activation_date']),
                'email_expiry_date' => emptyToNull($row['email_expiry_date']),
                'role' => emptyToNull($row['role']) ?: 'Agent',
                'status' => emptyToNull($row['status']) ?: 'Active',
                'inserted_at' => emptyToNull($row['inserted_at']),
                'notes' => emptyToNull($row['notes']),
                'data_incomplete' => boolToInt($row['data_incomplete']) ?? 0,
            ];
            
            $stmt->execute($data);
            
            $name = trim(($row['first_name'] ?? '') . ' ' . $row['last_name']);
            
            if ($exists) {
                echo "🔄 UPDATE: $name ({$agencyCode})\n";
                $stats['updated']++;
            } else {
                echo "✅ INSERT: $name ({$agencyCode})\n";
                $stats['inserted']++;
            }
            
        } catch (PDOException $e) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            echo "❌ ERRORE Riga " . ($i + 2) . " ($name): " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n--- REPORT AGENTI ---\n";
    echo "✅ Inseriti:   {$stats['inserted']}\n";
    echo "🔄 Aggiornati: {$stats['updated']}\n";
    echo "⚠️  Saltati:    {$stats['skipped']}\n";
    echo "⚠️  Agenzia non trovata: {$stats['agency_not_found']}\n";
    echo "❌ Errori:     {$stats['errors']}\n";
    echo "TOTALE:       " . count($rows) . "\n\n";
    
    return $stats;
}

// ============================================================================
// MAIN
// ============================================================================

try {
    if (!file_exists($agenzieFile)) {
        throw new Exception("File non trovato: $agenzieFile");
    }
    if (!file_exists($agentiFile)) {
        throw new Exception("File non trovato: $agentiFile");
    }
    
    $statsAgenzie = importAgenzie($pdo, $agenzieFile);
    $statsAgenti = importAgenti($pdo, $agentiFile);
    
    echo "================================================================================\n";
    echo "✅ IMPORT COMPLETATO CON SUCCESSO!\n";
    echo "================================================================================\n\n";
    
    echo "RIEPILOGO FINALE:\n";
    echo "AGENZIE - Inserite: {$statsAgenzie['inserted']}, Aggiornate: {$statsAgenzie['updated']}, Errori: {$statsAgenzie['errors']}\n";
    echo "AGENTI  - Inseriti: {$statsAgenti['inserted']}, Aggiornati: {$statsAgenti['updated']}, Errori: {$statsAgenti['errors']}\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRORE FATALE: " . $e->getMessage() . "\n";
    exit(1);
}

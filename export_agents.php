<?php
/**
 * Export Agenti in CSV
 * Esporta tutti gli agenti attivi in formato CSV
 */

require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Query agenti attivi con info agenzia
$stmt = $pdo->query("
    SELECT 
        a.first_name,
        a.last_name,
        a.email_personal,
        a.mobile,
        a.role,
        ag.code as agency_code,
        ag.name as agency_name,
        ag.city as agency_city,
        a.status,
        a.created_at
    FROM agents a
    LEFT JOIN agencies ag ON a.agency_id = ag.id
    WHERE a.status = 'Active'
    ORDER BY a.last_name ASC, a.first_name ASC
");
$agents = $stmt->fetchAll();

// Headers per download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agenti_attivi_' . date('Y-m-d') . '.csv"');

// Output CSV
$output = fopen('php://output', 'w');

// BOM per Excel UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header CSV
fputcsv($output, ['Nome', 'Cognome', 'Email', 'Cellulare', 'Ruolo', 'Codice Agenzia', 'Nome Agenzia', 'Città Agenzia', 'Status', 'Data Creazione']);

// Dati
foreach ($agents as $agent) {
    fputcsv($output, [
        $agent['first_name'],
        $agent['last_name'],
        $agent['email_personal'],
        $agent['mobile'],
        $agent['role'],
        $agent['agency_code'],
        $agent['agency_name'],
        $agent['agency_city'],
        $agent['status'],
        $agent['created_at']
    ]);
}

fclose($output);
exit;

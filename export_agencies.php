<?php
/**
 * Export Agenzie in CSV
 * Esporta tutte le agenzie attive in formato CSV
 */

require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Query agenzie attive
$stmt = $pdo->query("
    SELECT code, name, city, province, email, phone, broker_manager, status, created_at
    FROM agencies
    WHERE status = 'Active'
    ORDER BY name ASC
");
$agencies = $stmt->fetchAll();

// Headers per download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agenzie_attive_' . date('Y-m-d') . '.csv"');

// Output CSV
$output = fopen('php://output', 'w');

// BOM per Excel UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header CSV
fputcsv($output, ['Codice', 'Nome', 'Città', 'Provincia', 'Email', 'Telefono', 'Broker Manager', 'Status', 'Data Creazione']);

// Dati
foreach ($agencies as $agency) {
    fputcsv($output, [
        $agency['code'],
        $agency['name'],
        $agency['city'],
        $agency['province'],
        $agency['email'],
        $agency['phone'],
        $agency['broker_manager'],
        $agency['status'],
        $agency['created_at']
    ]);
}

fclose($output);
exit;

<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Campi selezionati
$exportFields = $_POST['export'] ?? [];
$statusFilter = $_POST['status_filter'] ?? 'Active';
$search = $_POST['search'] ?? '';

if (empty($exportFields)) {
    header('Location: agenzie.php?error=no_fields');
    exit;
}

// Mappa campi DB
$fieldMap = [
    'code' => 'code',
    'name' => 'name',
    'city' => 'city',
    'province' => 'province',
    'email' => 'email',
    'phone' => 'phone',
    'broker' => 'broker_manager',
    'tech_fee' => 'tech_fee',
    'contract_expiry' => 'contract_expiry',
    'activation_date' => 'activation_date',
    // Servizi - TODO: mappare con nomi esatti DB
    'cb_suite' => 'cb_suite_status',
    'canva' => 'canva_active',
    'regold' => 'regold_status',
    'james' => 'james_edition_status',
    'docudrop' => 'docudrop_status',
    'unique' => 'unique_active'
];

// Costruisci SELECT
$selectFields = [];
foreach ($exportFields as $field) {
    if (isset($fieldMap[$field])) {
        $selectFields[] = $fieldMap[$field];
    }
}

if (empty($selectFields)) {
    header('Location: agenzie.php?error=invalid_fields');
    exit;
}

// Query
$sql = "SELECT " . implode(', ', $selectFields) . " FROM agencies WHERE status != 'Prospect'";

if ($statusFilter !== 'all') {
    $sql .= " AND status = :status";
}

if ($search) {
    $sql .= " AND (name LIKE :search OR code LIKE :search OR city LIKE :search)";
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);

if ($statusFilter !== 'all') {
    $stmt->bindValue(':status', $statusFilter);
}
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}

$stmt->execute();
$agencies = $stmt->fetchAll();

// Headers CSV
$headerLabels = [
    'code' => 'Codice',
    'name' => 'Nome',
    'city' => 'Città',
    'province' => 'Provincia',
    'email' => 'Email',
    'phone' => 'Telefono',
    'broker' => 'Broker Manager',
    'tech_fee' => 'Tech Fee',
    'contract_expiry' => 'Scadenza Contratto',
    'activation_date' => 'Data Attivazione',
    'cb_suite' => 'CB Suite',
    'canva' => 'Canva',
    'regold' => 'Regold',
    'james' => 'James Edition',
    'docudrop' => 'Docudrop',
    'unique' => 'Unique'
];

$headers = [];
foreach ($exportFields as $field) {
    $headers[] = $headerLabels[$field] ?? $field;
}

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agenzie_export_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

fputcsv($output, $headers);

foreach ($agencies as $agency) {
    $row = [];
    foreach ($exportFields as $field) {
        $dbField = $fieldMap[$field] ?? $field;
        $value = $agency[$dbField] ?? '';
        
        // Formattazione speciale
        if ($field === 'tech_fee' && $value) {
            $value = '€ ' . number_format($value, 2, ',', '.');
        } elseif (in_array($field, ['activation_date', 'contract_expiry']) && $value) {
            $value = date('d/m/Y', strtotime($value));
        }
        
        $row[] = $value;
    }
    fputcsv($output, $row);
}

fclose($output);
exit;

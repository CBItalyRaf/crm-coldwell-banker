<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Prendi filtri dalla ricerca
$statusFilter = $_POST['status_filter'] ?? 'Active';
$search = $_POST['search'] ?? '';

// Campi da esportare
$fields = $_POST['export'] ?? [];

if (empty($fields)) {
    header('Location: agenzie.php?error=no_fields');
    exit;
}

// Mappa campi form -> colonne DB
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
    // Servizi - per ora non implementati
    'cb_suite' => null,
    'canva' => null,
    'regold' => null,
    'james' => null,
    'docudrop' => null,
    'unique' => null
];

// Costruisci SELECT
$selectFields = [];
foreach ($fields as $field) {
    if (isset($fieldMap[$field]) && $fieldMap[$field] !== null) {
        $selectFields[] = $fieldMap[$field];
    }
}

if (empty($selectFields)) {
    header('Location: agenzie.php?error=no_valid_fields');
    exit;
}

// Query (stessa logica di agenzie.php)
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
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agenzie_export_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');

// BOM UTF-8 per Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header CSV (nomi leggibili)
$headerNames = [
    'code' => 'Codice',
    'name' => 'Nome',
    'city' => 'Città',
    'province' => 'Provincia',
    'email' => 'Email',
    'phone' => 'Telefono',
    'broker_manager' => 'Broker Manager',
    'tech_fee' => 'Tech Fee',
    'contract_expiry' => 'Scadenza Contratto',
    'activation_date' => 'Data Attivazione'
];

$headers = [];
foreach ($selectFields as $field) {
    $headers[] = $headerNames[$field] ?? $field;
}

fputcsv($output, $headers);

// Dati
foreach ($agencies as $agency) {
    $row = [];
    foreach ($selectFields as $field) {
        $row[] = $agency[$field] ?? '';
    }
    fputcsv($output, $row);
}

fclose($output);
exit;

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

// Separa campi agenzia da servizi
$agencyFields = ['code', 'name', 'city', 'province', 'email', 'phone', 'broker', 'tech_fee', 'contract_expiry', 'activation_date'];
$serviceFields = ['cb_suite', 'canva', 'regold', 'james', 'docudrop', 'unique'];

$requestedAgencyFields = array_intersect($exportFields, $agencyFields);
$requestedServiceFields = array_intersect($exportFields, $serviceFields);

// Mappa campi DB agenzie
$fieldMap = [
    'code' => 'a.code',
    'name' => 'a.name',
    'city' => 'a.city',
    'province' => 'a.province',
    'email' => 'a.email',
    'phone' => 'a.phone',
    'broker' => 'a.broker_manager',
    'tech_fee' => 'a.tech_fee',
    'contract_expiry' => 'a.contract_expiry',
    'activation_date' => 'a.activation_date'
];

// Costruisci SELECT per campi agenzia
$selectFields = ['a.id'];
foreach ($requestedAgencyFields as $field) {
    if (isset($fieldMap[$field])) {
        $selectFields[] = $fieldMap[$field];
    }
}

// Aggiungi JOIN per servizi richiesti
$joins = '';
$serviceSelects = [];

if (in_array('cb_suite', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_cb ON a.id = svc_cb.agency_id AND svc_cb.service_name = 'cb_suite'";
    $serviceSelects[] = "IF(svc_cb.is_active, 'Attivo', 'Non attivo') as cb_suite_status";
}
if (in_array('canva', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_canva ON a.id = svc_canva.agency_id AND svc_canva.service_name = 'canva'";
    $serviceSelects[] = "IF(svc_canva.is_active, 'Attivo', 'Non attivo') as canva_status";
}
if (in_array('regold', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_regold ON a.id = svc_regold.agency_id AND svc_regold.service_name = 'regold'";
    $serviceSelects[] = "IF(svc_regold.is_active, 'Attivo', 'Non attivo') as regold_status";
}
if (in_array('james', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_james ON a.id = svc_james.agency_id AND svc_james.service_name = 'james_edition'";
    $serviceSelects[] = "IF(svc_james.is_active, 'Attivo', 'Non attivo') as james_status";
}
if (in_array('docudrop', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_docudrop ON a.id = svc_docudrop.agency_id AND svc_docudrop.service_name = 'docudrop'";
    $serviceSelects[] = "IF(svc_docudrop.is_active, 'Attivo', 'Non attivo') as docudrop_status";
}
if (in_array('unique', $requestedServiceFields)) {
    $joins .= " LEFT JOIN agency_services svc_unique ON a.id = svc_unique.agency_id AND svc_unique.service_name = 'unique'";
    $serviceSelects[] = "IF(svc_unique.is_active, 'Attivo', 'Non attivo') as unique_status";
}

$allSelects = array_merge($selectFields, $serviceSelects);

// Query
$sql = "SELECT " . implode(', ', $allSelects) . " FROM agencies a" . $joins . " WHERE a.status != 'Prospect'";

if ($statusFilter !== 'all') {
    $sql .= " AND a.status = :status";
}

if ($search) {
    $sql .= " AND (a.name LIKE :search OR a.code LIKE :search OR a.city LIKE :search)";
}

$sql .= " ORDER BY a.name ASC";

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
        if (in_array($field, $agencyFields)) {
            // Campo agenzia
            $dbField = str_replace('a.', '', $fieldMap[$field] ?? $field);
            $value = $agency[$dbField] ?? '';
            
            // Formattazione speciale
            if ($field === 'tech_fee' && $value) {
                $value = '€ ' . number_format($value, 2, ',', '.');
            } elseif (in_array($field, ['activation_date', 'contract_expiry']) && $value) {
                $value = date('d/m/Y', strtotime($value));
            }
        } else {
            // Campo servizio
            $value = $agency[$field . '_status'] ?? 'Non attivo';
        }
        
        $row[] = $value;
    }
    fputcsv($output, $row);
}

fclose($output);
exit;

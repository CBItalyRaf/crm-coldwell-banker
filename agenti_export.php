<?php
require_once 'check_auth.php';
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agenti.php');
    exit;
}

$pdo = getDB();

// Recupera filtri
$statusFilters = isset($_POST['status_filter']) && is_array($_POST['status_filter']) ? $_POST['status_filter'] : ['Active'];
$validStatuses = ['Active', 'Inactive'];
$statusFilters = array_intersect($statusFilters, $validStatuses);
if (empty($statusFilters)) {
    $statusFilters = ['Active'];
}

$search = $_POST['search'] ?? '';
$searchType = $_POST['search_type'] ?? 'all';
$exportFields = $_POST['export'] ?? [];

// Valida searchType
$validSearchTypes = ['all', 'name', 'email', 'agency', 'phone'];
if (!in_array($searchType, $validSearchTypes)) {
    $searchType = 'all';
}

if (empty($exportFields)) {
    header('Location: agenti.php?error=' . urlencode('Seleziona almeno un campo da esportare'));
    exit;
}

// Mappa campi
$fieldMap = [
    'full_name' => 'Nome Completo',
    'agency_name' => 'Agenzia',
    'agency_code' => 'Codice Agenzia',
    'role' => 'Ruoli',
    'email_corporate' => 'Email Corporate',
    'email_personal' => 'Email Personale',
    'mobile' => 'Telefono',
    'phone' => 'Telefono Fisso',
    'fiscal_code' => 'Codice Fiscale',
    'birth_date' => 'Data Nascita',
    'birth_place' => 'Luogo Nascita',
    'address' => 'Indirizzo',
    'm365_account_type' => 'Tipo Account M365',
    'm365_plan' => 'Piano M365',
    'status' => 'Status',
    'created_at' => 'Data Creazione'
];

// Query agenti
$sql = "SELECT a.*, ag.name as agency_name, ag.code as agency_code 
        FROM agents a 
        LEFT JOIN agencies ag ON a.agency_id = ag.id 
        WHERE a.status IN (" . implode(',', array_fill(0, count($statusFilters), '?')) . ")";

$params = $statusFilters;

if ($search) {
    if ($searchType === 'name') {
        $sql .= " AND a.full_name LIKE ?";
        $params[] = "%$search%";
    } elseif ($searchType === 'email') {
        $sql .= " AND (a.email_corporate LIKE ? OR a.email_personal LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } elseif ($searchType === 'agency') {
        $sql .= " AND ag.name LIKE ?";
        $params[] = "%$search%";
    } elseif ($searchType === 'phone') {
        $sql .= " AND (a.mobile LIKE ? OR a.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        // all - cerca in tutto
        $sql .= " AND (a.full_name LIKE ? OR a.email_corporate LIKE ? OR a.mobile LIKE ? OR ag.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$sql .= " ORDER BY a.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Genera CSV
$filename = 'agenti_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 per Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header CSV
$headers = [];
foreach ($exportFields as $field) {
    $headers[] = $fieldMap[$field] ?? $field;
}
fputcsv($output, $headers, ';');

// Mappa ruoli human-readable
$roleLabels = [
    'broker' => 'Broker',
    'broker_manager' => 'Broker Manager',
    'legale_rappresentante' => 'Legale Rappresentante',
    'preposto' => 'Preposto',
    'global_luxury' => 'Global Luxury'
];

// Dati
foreach ($agents as $agent) {
    $row = [];
    
    foreach ($exportFields as $field) {
        if ($field === 'role') {
            // Gestione ruoli JSON
            $rolesJson = $agent['role'];
            $roles = $rolesJson ? json_decode($rolesJson, true) : [];
            $roleNames = [];
            foreach ($roles as $role) {
                $roleNames[] = $roleLabels[$role] ?? $role;
            }
            $row[] = !empty($roleNames) ? implode(', ', $roleNames) : '-';
        } elseif ($field === 'm365_account_type') {
            // Gestione tipo account M365
            $accountTypes = [
                'agente' => 'Agente',
                'agenzia' => 'Agenzia',
                'servizio' => 'Servizio',
                'master' => 'Master'
            ];
            $accountType = $agent[$field] ?? 'agente';
            $row[] = $accountTypes[$accountType] ?? 'Agente';
        } elseif ($field === 'birth_date' && $agent[$field]) {
            // Formatta data
            $row[] = date('d/m/Y', strtotime($agent[$field]));
        } elseif ($field === 'created_at' && $agent[$field]) {
            // Formatta data/ora
            $row[] = date('d/m/Y H:i', strtotime($agent[$field]));
        } elseif ($field === 'address') {
            // Combina indirizzo completo
            $address = trim($agent['address'] ?? '');
            $city = trim($agent['city'] ?? '');
            $province = trim($agent['province'] ?? '');
            $zip = trim($agent['zip'] ?? '');
            
            $parts = array_filter([$address, $zip, $city, $province]);
            $row[] = !empty($parts) ? implode(', ', $parts) : '-';
        } else {
            $row[] = $agent[$field] ?? '-';
        }
    }
    
    fputcsv($output, $row, ';');
}

fclose($output);
exit;

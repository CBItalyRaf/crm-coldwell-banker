<?php
/**
 * Search API - Autocomplete
 * Cerca agenzie e agenti attivi e restituisce risultati formattati
 */

session_start();

// Verifica sessione
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

require_once 'config/database.php';
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();
$results = [];
$searchParam = "%$query%";

// Cerca agenzie ATTIVE
$stmt = $pdo->prepare("
    SELECT code, name, city, province, address 
    FROM agencies 
    WHERE status = 'Active' 
    AND (name LIKE ? OR code LIKE ? OR city LIKE ? OR province LIKE ?) 
    ORDER BY name ASC
    LIMIT 5
");
$stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
$agencies = $stmt->fetchAll();

foreach ($agencies as $agency) {
    // Limita indirizzo a 40 caratteri
    $addressPart = '';
    if ($agency['address']) {
        $addressPart = strlen($agency['address']) > 40 
            ? substr($agency['address'], 0, 40) . '...' 
            : $agency['address'];
    }
    
    // Title: CBI001 - Nome Agenzia
    $title = $agency['code'] . ' - ' . $agency['name'];
    
    // Meta: Città, Provincia - Via XYZ
    $meta = $agency['city'];
    if ($agency['province']) {
        $meta .= ', ' . $agency['province'];
    }
    if ($addressPart) {
        $meta .= ' - ' . $addressPart;
    }
    
    $results[] = [
        'type' => 'agency',
        'title' => $title,
        'meta' => $meta,
        'url' => "agenzia_detail.php?code={$agency['code']}"
    ];
}

// Cerca agenti ATTIVI
$stmt = $pdo->prepare("
    SELECT a.id, a.first_name, a.last_name, ag.code, ag.name as agency_name, ag.city
    FROM agents a
    LEFT JOIN agencies ag ON a.agency_id = ag.id
    WHERE a.status = 'Active'
    AND (a.first_name LIKE ? OR a.last_name LIKE ?)
    ORDER BY a.last_name ASC, a.first_name ASC
    LIMIT 5
");
$stmt->execute([$searchParam, $searchParam]);
$agents = $stmt->fetchAll();

foreach ($agents as $agent) {
    // Title: Nome Cognome
    $title = $agent['first_name'] . ' ' . $agent['last_name'];
    
    // Meta: Agenzia (CODE) - Città
    $meta = '';
    if ($agent['agency_name']) {
        $meta = $agent['agency_name'];
        if ($agent['code']) {
            $meta .= ' (' . $agent['code'] . ')';
        }
        if ($agent['city']) {
            $meta .= ' - ' . $agent['city'];
        }
    } else {
        $meta = 'Senza agenzia';
    }
    
    $results[] = [
        'type' => 'agent',
        'title' => $title,
        'meta' => $meta,
        'url' => "agente_detail.php?id={$agent['id']}"
    ];
}

echo json_encode($results);

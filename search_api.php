<?php
/**
 * Search API - Autocomplete - FIXED
/**
 * Search API - Autocomplete
 * Cerca agenzie e agenti attivi e restituisce risultati formattati
 */

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
    SELECT code, name, city 
    FROM agencies 
    WHERE status = 'Active' 
    AND (name LIKE ? OR code LIKE ? OR city LIKE ?) 
    LIMIT 5
");
$stmt->execute([$searchParam, $searchParam, $searchParam]);
$agencies = $stmt->fetchAll();

foreach ($agencies as $agency) {
    $results[] = [
        'type' => 'agency',
        'title' => strtoupper($agency['name']),
        'meta' => "({$agency['code']}, {$agency['city']})",
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
    LIMIT 5
");
$stmt->execute([$searchParam, $searchParam]);
$agents = $stmt->fetchAll();

foreach ($agents as $agent) {
    $results[] = [
        'type' => 'agent',
        'title' => strtoupper($agent['first_name'] . ' ' . $agent['last_name']),
        'meta' => "({$agent['code']}, {$agent['agency_name']}, {$agent['city']})",
        'url' => "agente_detail.php?id={$agent['id']}"
    ];
}

echo json_encode($results);

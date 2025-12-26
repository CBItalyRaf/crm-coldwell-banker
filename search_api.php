<?php
/**
 * Search API - Autocomplete
 * Cerca agenzie e agenti e restituisce risultati formattati
 */

require_once 'check_auth.php';
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

// Cerca agenzie ATTIVE
$stmt = $pdo->prepare("
    SELECT 
        agency_code,
        name,
        city
    FROM agencies 
    WHERE status = 'Active'
    AND (
        name LIKE :query 
        OR agency_code LIKE :query
        OR city LIKE :query
    )
    LIMIT 5
");
$stmt->execute(['query' => "%$query%"]);
$agencies = $stmt->fetchAll();

foreach ($agencies as $agency) {
    $results[] = [
        'type' => 'agency',
        'title' => strtoupper($agency['name']),
        'meta' => "({$agency['agency_code']}, {$agency['city']})",
        'url' => "agenzie.php?id={$agency['agency_code']}"
    ];
}

// Cerca agenti ATTIVI
$stmt = $pdo->prepare("
    SELECT 
        a.agent_id,
        a.first_name,
        a.last_name,
        a.agency_code,
        ag.name as agency_name,
        ag.city
    FROM agents a
    LEFT JOIN agencies ag ON a.agency_code = ag.agency_code
    WHERE a.status = 'Active'
    AND (
        a.first_name LIKE :query 
        OR a.last_name LIKE :query
        OR a.agency_code LIKE :query
    )
    LIMIT 5
");
$stmt->execute(['query' => "%$query%"]);
$agents = $stmt->fetchAll();

foreach ($agents as $agent) {
    $results[] = [
        'type' => 'agent',
        'title' => strtoupper($agent['first_name'] . ' ' . $agent['last_name']),
        'meta' => "({$agent['agency_code']}, {$agent['agency_name']}, {$agent['city']})",
        'url' => "agenti.php?id={$agent['agent_id']}"
    ];
}

echo json_encode($results);

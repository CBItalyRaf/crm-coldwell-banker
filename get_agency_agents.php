<?php
require_once 'check_auth.php';
require_once 'config/database.php';

header('Content-Type: application/json');

$agencyId = $_GET['agency_id'] ?? null;

if (!$agencyId) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name,
           CONCAT(first_name, ' ', last_name) as full_name
    FROM agents 
    WHERE agency_id = ? AND status = 'Active'
    ORDER BY first_name, last_name
");

$stmt->execute([$agencyId]);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($agents);

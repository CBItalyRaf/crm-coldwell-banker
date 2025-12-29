<?php
require_once 'config/database.php';

$code = $_GET['code'] ?? 'CBI162';
$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM agencies WHERE code = :code");
$stmt->execute(['code' => $code]);
$agency = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM agency_services WHERE agency_id = :id AND is_active = 1");
$stmt->execute(['id' => $agency['id']]);
$services = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Agency Services</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #012169; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Servizi ATTIVI in agency_services per <?= $code ?></h1>
    
    <table>
        <tr>
            <th>ID</th>
            <th>service_name</th>
            <th>is_active</th>
            <th>activation_date</th>
            <th>expiration_date</th>
        </tr>
        <?php foreach($services as $s): ?>
        <tr>
            <td><?= $s['id'] ?></td>
            <td><?= htmlspecialchars($s['service_name']) ?></td>
            <td><?= $s['is_active'] ? '✓' : '✗' ?></td>
            <td><?= $s['activation_date'] ?? '-' ?></td>
            <td><?= $s['expiration_date'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <p>Totale: <?= count($services) ?> servizi attivi</p>
</body>
</html>

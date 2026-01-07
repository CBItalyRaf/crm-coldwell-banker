<?php
require_once '/var/www/admin.mycb.it/check_auth.php';
require_once '/var/www/admin.mycb.it/config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0}
pre{background:#000;padding:10px;border:1px solid #0f0}
.error{color:#f00}
.success{color:#0f0}
.warning{color:#ff0}
</style>
</head>
<body>
<h1>Import Ruoli Multipli da CSV</h1>
<pre><?php

$pdo = getDB();

// Mappa ruoli
$roleMap = [
    'Broker' => 'broker',
    'Broker Manager' => 'broker_manager',
    'Legale Rappresentante' => 'legale_rappresentante',
    'Preposto' => 'preposto',
    'GL Specialist' => 'global_luxury'
];

echo "Opening CSV...\n";
$file = fopen('/var/www/admin.mycb.it/agenti.csv', 'r');
if (!$file) die("ERROR: Cannot open CSV\n");

$header = fgetcsv($file); // Skip header
echo "CSV opened, processing...\n\n";

$updated = 0;
$skipped = 0;
$total = 0;

while (($row = fgetcsv($file)) !== false) {
    $total++;
    
    $agencyCode = $row[0] ?? '';
    $firstName = $row[5] ?? '';
    $lastName = $row[6] ?? '';
    $rolesRaw = $row[9] ?? '';
    
    if (empty($firstName) || empty($lastName)) {
        $skipped++;
        continue;
    }
    
    // Parse roles
    $rolesArray = array_map('trim', explode(',', $rolesRaw));
    $officialRoles = [];
    
    foreach ($rolesArray as $role) {
        if (isset($roleMap[$role])) {
            $officialRoles[] = $roleMap[$role];
        }
    }
    
    $rolesJson = !empty($officialRoles) ? json_encode($officialRoles) : null;
    
    // Find agent
    $stmt = $pdo->prepare("
        SELECT a.id 
        FROM agents a
        JOIN agencies ag ON a.agency_id = ag.id
        WHERE a.first_name = ? AND a.last_name = ? AND ag.code = ?
    ");
    $stmt->execute([$firstName, $lastName, $agencyCode]);
    $agent = $stmt->fetch();
    
    if (!$agent) {
        $skipped++;
        continue;
    }
    
    // Update
    $stmt = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
    $stmt->execute([$rolesJson, $agent['id']]);
    $updated++;
    
    if ($updated <= 10 || $updated % 100 == 0) {
        echo "✓ #$updated: $firstName $lastName => " . ($rolesJson ?: 'NULL') . "\n";
        flush();
    }
}

fclose($file);

echo "\n=== RISULTATO ===\n";
echo "Righe totali: $total\n";
echo "Aggiornati: $updated\n";
echo "Saltati: $skipped\n";

?></pre>
</body>
</html>

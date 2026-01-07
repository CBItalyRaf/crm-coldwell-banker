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
.success{color:#0f0}
.warning{color:#ff0}
</style>
</head>
<body>
<h1>Aggiungi Ruolo Broker Manager</h1>
<pre><?php

$pdo = getDB();

echo "Caricamento agenzie con broker_manager...\n\n";

$stmt = $pdo->query("SELECT id, code, broker_manager FROM agencies WHERE broker_manager IS NOT NULL AND broker_manager != ''");
$agencies = $stmt->fetchAll();

echo "Trovate " . count($agencies) . " agenzie con broker_manager\n\n";

$updated = 0;
$notFound = 0;

foreach ($agencies as $agency) {
    $names = preg_split('/[,\/]/', $agency['broker_manager']);
    
    foreach ($names as $name) {
        $name = trim($name);
        
        // Rimuovi note tra parentesi
        if (strpos($name, '(') !== false) {
            $name = trim(preg_replace('/\(.*?\)/', '', $name));
        }
        
        if (empty($name)) continue;
        
        // Trova agente
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM agents WHERE agency_id = ? AND CONCAT(first_name, ' ', last_name) = ?");
        $stmt->execute([$agency['id'], $name]);
        $agent = $stmt->fetch();
        
        if ($agent) {
            // Decodifica ruoli esistenti
            $currentRoles = $agent['role'] ? json_decode($agent['role'], true) : [];
            
            // Aggiungi broker_manager se non c'è
            if (!in_array('broker_manager', $currentRoles)) {
                $currentRoles[] = 'broker_manager';
                $newRole = json_encode($currentRoles);
                
                $upd = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
                $upd->execute([$newRole, $agent['id']]);
                
                echo "✓ {$agent['first_name']} {$agent['last_name']} ({$agency['code']}): " . implode(', ', $currentRoles) . "\n";
                $updated++;
            } else {
                echo "- {$agent['first_name']} {$agent['last_name']} ({$agency['code']}): già broker_manager\n";
            }
        } else {
            echo "<span class='warning'>⚠ '$name' non trovato in {$agency['code']}</span>\n";
            $notFound++;
        }
    }
}

echo "\n=== RISULTATO ===\n";
echo "Ruoli aggiunti: $updated\n";
echo "Non trovati: $notFound\n";

?></pre>
</body>
</html>

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
<h1>Sync Ruoli da Agencies a Agents</h1>
<pre><?php

$pdo = getDB();

echo "Caricamento agenzie con ruoli...\n\n";

// Carica tutte le agenzie con broker_manager, legal_representative, preposto
$stmt = $pdo->query("
    SELECT id, code, broker_manager, legal_representative, preposto 
    FROM agencies 
    WHERE broker_manager IS NOT NULL 
       OR legal_representative IS NOT NULL 
       OR preposto IS NOT NULL
");
$agencies = $stmt->fetchAll();

echo "Trovate " . count($agencies) . " agenzie con ruoli definiti\n\n";

$updated = 0;
$notFound = 0;
$errors = [];

foreach ($agencies as $agency) {
    $agencyCode = $agency['code'];
    
    // Broker Manager
    if (!empty($agency['broker_manager'])) {
        $names = preg_split('/[,\/]/', $agency['broker_manager']); // Split per virgola o slash
        
        foreach ($names as $name) {
            $name = trim($name);
            
            // Salta note tra parentesi
            if (strpos($name, '(') !== false) {
                $name = trim(preg_replace('/\(.*?\)/', '', $name));
            }
            
            if (empty($name)) continue;
            
            // Prova a trovare agente
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, role 
                FROM agents 
                WHERE agency_id = ? 
                AND (
                    CONCAT(first_name, ' ', last_name) = ?
                    OR (first_name = ? AND last_name = ?)
                )
            ");
            $stmt->execute([$agency['id'], $name, $firstName, $lastName]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                // Aggiungi ruolo broker_manager
                $currentRoles = $agent['role'] ? json_decode($agent['role'], true) : [];
                if (!in_array('broker_manager', $currentRoles)) {
                    $currentRoles[] = 'broker_manager';
                    $newRole = json_encode($currentRoles);
                    
                    $upd = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
                    $upd->execute([$newRole, $agent['id']]);
                    
                    echo "✓ Broker Manager: {$agent['first_name']} {$agent['last_name']} ({$agencyCode})\n";
                    $updated++;
                }
            } else {
                echo "<span class='warning'>⚠ Broker Manager '$name' non trovato in $agencyCode</span>\n";
                $notFound++;
            }
        }
    }
    
    // Legale Rappresentante
    if (!empty($agency['legal_representative'])) {
        $names = preg_split('/[,\/]/', $agency['legal_representative']);
        
        foreach ($names as $name) {
            $name = trim($name);
            if (strpos($name, '(') !== false) {
                $name = trim(preg_replace('/\(.*?\)/', '', $name));
            }
            if (empty($name)) continue;
            
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, role 
                FROM agents 
                WHERE agency_id = ? 
                AND (
                    CONCAT(first_name, ' ', last_name) = ?
                    OR (first_name = ? AND last_name = ?)
                )
            ");
            $stmt->execute([$agency['id'], $name, $firstName, $lastName]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                $currentRoles = $agent['role'] ? json_decode($agent['role'], true) : [];
                if (!in_array('legale_rappresentante', $currentRoles)) {
                    $currentRoles[] = 'legale_rappresentante';
                    $newRole = json_encode($currentRoles);
                    
                    $upd = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
                    $upd->execute([$newRole, $agent['id']]);
                    
                    echo "✓ Legale Rapp: {$agent['first_name']} {$agent['last_name']} ({$agencyCode})\n";
                    $updated++;
                }
            } else {
                echo "<span class='warning'>⚠ Legale Rapp '$name' non trovato in $agencyCode</span>\n";
                $notFound++;
            }
        }
    }
    
    // Preposto
    if (!empty($agency['preposto'])) {
        $names = preg_split('/[,\/]/', $agency['preposto']);
        
        foreach ($names as $name) {
            $name = trim($name);
            if (strpos($name, '(') !== false) {
                $name = trim(preg_replace('/\(.*?\)/', '', $name));
            }
            if (empty($name)) continue;
            
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, role 
                FROM agents 
                WHERE agency_id = ? 
                AND (
                    CONCAT(first_name, ' ', last_name) = ?
                    OR (first_name = ? AND last_name = ?)
                )
            ");
            $stmt->execute([$agency['id'], $name, $firstName, $lastName]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                $currentRoles = $agent['role'] ? json_decode($agent['role'], true) : [];
                if (!in_array('preposto', $currentRoles)) {
                    $currentRoles[] = 'preposto';
                    $newRole = json_encode($currentRoles);
                    
                    $upd = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
                    $upd->execute([$newRole, $agent['id']]);
                    
                    echo "✓ Preposto: {$agent['first_name']} {$agent['last_name']} ({$agencyCode})\n";
                    $updated++;
                }
            } else {
                echo "<span class='warning'>⚠ Preposto '$name' non trovato in $agencyCode</span>\n";
                $notFound++;
            }
        }
    }
}

echo "\n=== RISULTATO ===\n";
echo "Ruoli aggiunti: $updated\n";
echo "Non trovati: $notFound\n";

?></pre>
</body>
</html>

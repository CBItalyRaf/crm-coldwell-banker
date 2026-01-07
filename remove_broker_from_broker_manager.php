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
</style>
</head>
<body>
<h1>Rimuovi "broker" da chi ha "broker_manager"</h1>
<pre><?php

$pdo = getDB();

echo "Cerco agenti con entrambi i ruoli...\n\n";

$stmt = $pdo->query("SELECT id, first_name, last_name, role FROM agents WHERE role IS NOT NULL");
$agents = $stmt->fetchAll();

$updated = 0;

foreach ($agents as $agent) {
    $roles = json_decode($agent['role'], true);
    
    if (!$roles || !is_array($roles)) continue;
    
    // Se ha broker_manager E broker
    if (in_array('broker_manager', $roles) && in_array('broker', $roles)) {
        // Rimuovi broker
        $roles = array_values(array_filter($roles, fn($r) => $r !== 'broker'));
        
        $newRole = json_encode($roles);
        
        $upd = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
        $upd->execute([$newRole, $agent['id']]);
        
        echo "✓ {$agent['first_name']} {$agent['last_name']}: " . implode(', ', $roles) . "\n";
        $updated++;
    }
}

echo "\n=== RISULTATO ===\n";
echo "Agenti aggiornati: $updated\n";

?></pre>
</body>
</html>

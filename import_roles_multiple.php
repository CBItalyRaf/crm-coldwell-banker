<?php
require_once '/var/www/admin.mycb.it/config/database.php';

$pdo = getDB();

// Mappa ruoli CSV → DB
$roleMap = [
    'Broker' => 'broker',
    'Broker Manager' => 'broker_manager',
    'Legale Rappresentante' => 'legale_rappresentante',
    'Preposto' => 'preposto',
    'GL Specialist' => 'global_luxury'
];

// Tag geografiche e funzionali da salvare separatamente
$mailingTags = ['agentinord', 'agenticentro', 'agentisud', 'agentiroma', 'agenzia', 'servizio', 'master'];

$file = fopen('/mnt/user-data/uploads/agenti.csv', 'r');
$header = fgetcsv($file); // Skip header

$updated = 0;
$errors = [];

while (($row = fgetcsv($file)) !== false) {
    $agencyCode = $row[0] ?? ''; // ID_Agenzia
    $firstName = $row[5] ?? ''; // Nome
    $lastName = $row[6] ?? ''; // Cognome
    $rolesRaw = $row[9] ?? ''; // Ruolo
    
    if (empty($firstName) || empty($lastName) || empty($agencyCode)) continue;
    
    // Splitta ruoli multipli (separati da virgola)
    $rolesArray = array_map('trim', explode(',', $rolesRaw));
    
    $officialRoles = [];
    $tags = [];
    
    foreach ($rolesArray as $role) {
        if (isset($roleMap[$role])) {
            $officialRoles[] = $roleMap[$role];
        } elseif (in_array(strtolower($role), $mailingTags)) {
            $tags[] = strtolower($role);
        }
    }
    
    // Converti in JSON
    $rolesJson = !empty($officialRoles) ? json_encode($officialRoles) : null;
    $tagsJson = !empty($tags) ? json_encode($tags) : null;
    
    try {
        // Trova agente per nome e cognome e agenzia
        $stmt = $pdo->prepare("
            SELECT a.id, ag.code 
            FROM agents a
            JOIN agencies ag ON a.agency_id = ag.id
            WHERE a.first_name = ? AND a.last_name = ? AND ag.code = ?
        ");
        $stmt->execute([$firstName, $lastName, $agencyCode]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            $errors[] = "$firstName $lastName ($agencyCode) - Non trovato nel DB";
            continue;
        }
        
        // Aggiorna ruoli
        $stmt = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
        $stmt->execute([$rolesJson, $agent['id']]);
        
        $updated++;
        echo "✓ $firstName $lastName ($agencyCode): " . json_encode($officialRoles) . "\n";
        
    } catch (Exception $e) {
        $errors[] = "$firstName $lastName: " . $e->getMessage();
    }
}

fclose($file);

echo "\n=== RISULTATO ===\n";
echo "Agenti aggiornati: $updated\n";
echo "Errori: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nERRORI:\n";
    foreach (array_slice($errors, 0, 20) as $error) {
        echo "- $error\n";
    }
    if (count($errors) > 20) {
        echo "... e altri " . (count($errors) - 20) . " errori\n";
    }
}

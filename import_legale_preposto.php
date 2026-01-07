<?php
require_once '/var/www/admin.mycb.it/config/database.php';

$pdo = getDB();

// Leggi CSV
$file = fopen('/mnt/user-data/uploads/agenzie.csv', 'r');

// Salta header
$header = fgetcsv($file);

$updated = 0;
$errors = [];

while (($row = fgetcsv($file)) !== false) {
    $code = $row[0] ?? ''; // CBI
    $legalRepCF = $row[13] ?? ''; // Codice fiscale Legale Rappresentante (colonna 14)
    $legalRep = $row[46] ?? ''; // Rollup Legale Rappresentante (colonna 47)
    $preposto = $row[47] ?? ''; // Rollup Preposto (colonna 48)
    
    if (empty($code)) continue;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE agencies 
            SET legal_representative = ?, legal_representative_cf = ?, preposto = ? 
            WHERE code = ?
        ");
        $stmt->execute([
            !empty($legalRep) ? $legalRep : null,
            !empty($legalRepCF) ? $legalRepCF : null,
            !empty($preposto) ? $preposto : null,
            $code
        ]);
        
        if ($stmt->rowCount() > 0) {
            $updated++;
            echo "✓ $code: Legale=$legalRep (CF=$legalRepCF), Preposto=$preposto\n";
        }
    } catch (Exception $e) {
        $errors[] = "$code: " . $e->getMessage();
    }
}

fclose($file);

echo "\n=== RISULTATO ===\n";
echo "Agenzie aggiornate: $updated\n";
echo "Errori: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nERRORI:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

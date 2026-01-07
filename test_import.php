<?php
require_once '/var/www/admin.mycb.it/check_auth.php';

header('Content-Type: text/html; charset=utf-8');
echo "START<br>";

try {
    echo "1. Checking database...<br>";
    require_once '/var/www/admin.mycb.it/config/database.php';
    $pdo = getDB();
    echo "✓ Database OK<br>";
    
    echo "2. Checking CSV file...<br>";
    $csvPath = '/var/www/admin.mycb.it/agenti.csv';
    if (!file_exists($csvPath)) {
        die("ERROR: File not found: $csvPath<br>");
    }
    echo "✓ File exists: " . filesize($csvPath) . " bytes<br>";
    
    echo "3. Opening CSV...<br>";
    $file = fopen($csvPath, 'r');
    if (!$file) {
        die("ERROR: Cannot open file<br>");
    }
    echo "✓ File opened<br>";
    
    echo "4. Reading header...<br>";
    $header = fgetcsv($file);
    echo "✓ Header: " . count($header) . " columns<br>";
    echo "First 10 cols: " . implode(', ', array_slice($header, 0, 10)) . "<br>";
    
    echo "5. Reading first data row...<br>";
    $row = fgetcsv($file);
    echo "✓ Row data: " . count($row) . " columns<br>";
    echo "Agency: {$row[0]}, Name: {$row[5]} {$row[6]}, Role: {$row[9]}<br>";
    
    fclose($file);
    echo "<br>SUCCESS! Script working!";
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

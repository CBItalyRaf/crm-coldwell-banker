<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: documenti.php');
    exit;
}

$pdo = getDB();
$user = $_SESSION['crm_user'];

try {
    $folder_name = trim($_POST['folder_name'] ?? '', '/');
    $type = $_POST['type'] ?? 'common';
    
    if (empty($folder_name)) {
        throw new Exception('Nome cartella obbligatorio.');
    }
    
    // Sanitize folder name
    $folder_name = preg_replace('/[^a-zA-Z0-9\/_-]/', '_', $folder_name);
    $folder_name = trim($folder_name, '/') . '/';
    
    // Crea directory fisica
    $basePath = __DIR__ . '/documents';
    
    if ($type === 'common') {
        $destDir = $basePath . '/common/' . $folder_name;
    } else {
        // Per agency, chiedi quale
        $agency_code = $_POST['agency_code'] ?? '';
        if (empty($agency_code)) {
            throw new Exception('Seleziona un\'agenzia.');
        }
        $destDir = $basePath . '/agencies/' . $agency_code . '/public/' . $folder_name;
    }
    
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            throw new Exception('Impossibile creare la directory.');
        }
    }
    
    // Inserisci nella tabella folders
    $insertFolder = $pdo->prepare("
        INSERT IGNORE INTO folders (folder_path, type, agency_code, created_by) 
        VALUES (?, ?, ?, ?)
    ");
    $insertFolder->execute([
        $folder_name,
        $type,
        $type === 'agency' ? $agency_code : null,
        $user['name']
    ]);
    
    // Log
    error_log("Folder created: {$folder_name} by {$user['name']}");
    
    header('Location: documenti.php?folder_created=1');
    exit;
    
} catch (Exception $e) {
    error_log("Folder creation error: " . $e->getMessage());
    header('Location: documenti.php?error=' . urlencode($e->getMessage()));
    exit;
}

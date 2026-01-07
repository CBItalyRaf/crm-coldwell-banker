<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

header('Content-Type: application/json');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'rename') {
        $path = $data['path'] ?? '';
        $newName = $data['newName'] ?? '';
        
        if (empty($path) || empty($newName)) {
            echo json_encode(['success' => false, 'error' => 'Path e nuovo nome richiesti']);
            exit;
        }
        
        // Valida nuovo nome (no caratteri speciali)
        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $newName)) {
            echo json_encode(['success' => false, 'error' => 'Nome non valido. Usa solo lettere, numeri, spazi, trattini e underscore.']);
            exit;
        }
        
        // Costruisci vecchio e nuovo path
        $documentsDir = __DIR__ . '/uploads/documents/';
        $oldFullPath = $documentsDir . $path;
        
        // Calcola nuovo path
        $pathParts = explode('/', $path);
        $pathParts[count($pathParts) - 1] = $newName;
        $newPath = implode('/', $pathParts);
        $newFullPath = $documentsDir . $newPath;
        
        // Verifica che cartella esista
        if (!is_dir($oldFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata']);
            exit;
        }
        
        // Verifica che nuovo nome non esista già
        if (file_exists($newFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Esiste già una cartella con questo nome']);
            exit;
        }
        
        // Rinomina
        if (!rename($oldFullPath, $newFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante rinomina']);
            exit;
        }
        
        // Aggiorna DB: cambia folder_path per tutti i documenti in questa cartella
        $stmt = $pdo->prepare("UPDATE documents SET folder_path = REPLACE(folder_path, ?, ?) WHERE folder_path LIKE ?");
        $stmt->execute([$path, $newPath, $path . '%']);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete') {
        $path = $data['path'] ?? '';
        
        if (empty($path)) {
            echo json_encode(['success' => false, 'error' => 'Path richiesto']);
            exit;
        }
        
        $documentsDir = __DIR__ . '/uploads/documents/';
        $fullPath = $documentsDir . $path;
        
        // Verifica che cartella esista
        if (!is_dir($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata']);
            exit;
        }
        
        // Verifica che sia vuota
        $files = array_diff(scandir($fullPath), ['.', '..']);
        if (count($files) > 0) {
            echo json_encode(['success' => false, 'error' => 'La cartella contiene ancora file']);
            exit;
        }
        
        // Elimina
        if (!rmdir($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante eliminazione']);
            exit;
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'create') {
        $parentPath = $data['parentPath'] ?? '';
        $name = $data['name'] ?? '';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nome richiesto']);
            exit;
        }
        
        // Valida nome
        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => 'Nome non valido. Usa solo lettere, numeri, spazi, trattini e underscore.']);
            exit;
        }
        
        $documentsDir = __DIR__ . '/uploads/documents/';
        $newPath = $parentPath ? $parentPath . '/' . $name : $name;
        $fullPath = $documentsDir . $newPath;
        
        // Verifica che non esista già
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Esiste già una cartella con questo nome']);
            exit;
        }
        
        // Crea cartella
        if (!mkdir($fullPath, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante creazione cartella']);
            exit;
        }
        
        echo json_encode(['success' => true, 'path' => $newPath]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    
} catch (Exception $e) {
    error_log("API Folders Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

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
        $path = rtrim($data['path'] ?? '', '/'); // Rimuovi trailing slash
        $newName = $data['newName'] ?? '';
        
        error_log("Rename folder: path='$path', newName='$newName'");
        
        if (empty($path) || empty($newName)) {
            echo json_encode(['success' => false, 'error' => 'Path e nuovo nome richiesti']);
            exit;
        }
        
        // Valida nuovo nome (no slash)
        if (strpos($newName, '/') !== false || strpos($newName, '\\') !== false) {
            echo json_encode(['success' => false, 'error' => 'Nome non valido: non può contenere / o \\']);
            exit;
        }
        
        if (empty(trim($newName))) {
            echo json_encode(['success' => false, 'error' => 'Nome non può essere vuoto']);
            exit;
        }
        
        // Cerca nella tabella folders
        $stmt = $pdo->prepare("SELECT type, agency_code FROM folders WHERE folder_path = ?");
        $stmt->execute([$path . '/']);
        $folderInfo = $stmt->fetch();
        
        if (!$folderInfo) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata nel database']);
            exit;
        }
        
        // Ricostruisci path completo
        $documentsDir = __DIR__ . '/documents/';
        
        if ($folderInfo['type'] === 'common') {
            $oldFullPath = $documentsDir . 'common/' . $path;
            $newFullPath = $documentsDir . 'common/' . $newName;
        } else if ($folderInfo['type'] === 'agency') {
            $basePath = $documentsDir . 'agencies/' . $folderInfo['agency_code'] . '/public/';
            $oldFullPath = $basePath . $path;
            $newFullPath = $basePath . $newName;
        } else {
            echo json_encode(['success' => false, 'error' => 'Tipo cartella non valido']);
            exit;
        }
        
        // Verifica che cartella esista
        if (!is_dir($oldFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata nel filesystem']);
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
        $path = rtrim($data['path'] ?? '', '/') . '/'; // Assicura trailing slash come nel DB
        
        error_log("Delete folder: path='$path'");
        
        if (empty($path)) {
            echo json_encode(['success' => false, 'error' => 'Path richiesto']);
            exit;
        }
        
        // Trova UN file dentro questa cartella per determinare il path fisico
        $stmt = $pdo->prepare("SELECT filepath, type, category_id FROM documents WHERE folder_path = ? LIMIT 1");
        $stmt->execute([$path]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            // Usa il filepath del documento per trovare la cartella
            $fullPath = __DIR__ . '/' . dirname($doc['filepath']);
        } else {
            // Nessun file - ricostruisci path usando logica upload
            $stmt = $pdo->prepare("SELECT type, agency_code FROM folders WHERE folder_path = ?");
            $stmt->execute([$path]);
            $folderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$folderInfo) {
                echo json_encode(['success' => false, 'error' => 'Cartella non trovata nel DB']);
                exit;
            }
            
            // LOGICA IDENTICA A documenti_upload.php righe 56-67
            $basePath = __DIR__ . '/documents';
            
            if ($folderInfo['type'] === 'common') {
                // Per common, cerca nella prima categoria disponibile (fallback)
                // In realtà le cartelle common sono sotto /documents/common/{slug}/
                // Ma senza file non sappiamo lo slug, quindi proviamo a cercare
                $fullPath = $basePath . '/common/' . rtrim($path, '/');
            } else {
                // Agency
                $fullPath = $basePath . '/agencies/' . $folderInfo['agency_code'] . '/public/' . rtrim($path, '/');
            }
        }
        
        error_log("Delete folder fullPath: '$fullPath'");
        error_log("Delete folder exists: " . (is_dir($fullPath) ? 'YES' : 'NO'));
        
        // Verifica che cartella esista
        if (!is_dir($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata: ' . $fullPath]);
            exit;
        }
        
        // Verifica che sia vuota
        $files = array_diff(scandir($fullPath), ['.', '..']);
        if (count($files) > 0) {
            echo json_encode(['success' => false, 'error' => 'La cartella contiene ancora ' . count($files) . ' file']);
            exit;
        }
        
        // Elimina dal filesystem
        if (!rmdir($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante eliminazione']);
            exit;
        }
        
        // Elimina dal DB
        $stmtDel = $pdo->prepare("DELETE FROM folders WHERE folder_path = ?");
        $stmtDel->execute([$path]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'create') {
        $parentPath = $data['parentPath'] ?? '';
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'common'; // Default common
        $agencyCode = $data['agencyCode'] ?? null;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nome richiesto']);
            exit;
        }
        
        // Valida nome (no slash)
        if (strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            echo json_encode(['success' => false, 'error' => 'Nome non valido: non può contenere / o \\']);
            exit;
        }
        
        if (empty(trim($name))) {
            echo json_encode(['success' => false, 'error' => 'Nome non può essere vuoto']);
            exit;
        }
        
        $documentsDir = __DIR__ . '/documents/';
        
        // Determina path fisico basato su type
        if ($type === 'common') {
            if ($parentPath) {
                // Dentro una sottocartella - trova il path reale
                $stmt = $pdo->prepare("SELECT filepath FROM documents WHERE folder_path = ? LIMIT 1");
                $stmt->execute([$parentPath]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc) {
                    $parentFullPath = __DIR__ . '/' . dirname($doc['filepath']);
                    $fullPath = $parentFullPath . '/' . $name;
                } else {
                    $fullPath = $documentsDir . 'common/' . $parentPath . '/' . $name;
                }
            } else {
                // Root common
                $fullPath = $documentsDir . 'common/' . $name;
            }
            $folderPath = $name . '/';
        } else {
            // Agency
            if (!$agencyCode) {
                echo json_encode(['success' => false, 'error' => 'Agency code richiesto']);
                exit;
            }
            $fullPath = $documentsDir . 'agencies/' . $agencyCode . '/public/' . $name;
            $folderPath = $name . '/';
        }
        
        // Verifica che non esista già
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Esiste già una cartella con questo nome']);
            exit;
        }
        
        // Crea cartella
        if (!mkdir($fullPath, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante creazione cartella: ' . $fullPath]);
            exit;
        }
        
        // Inserisci in DB
        $stmt = $pdo->prepare("INSERT INTO folders (folder_path, type, agency_code) VALUES (?, ?, ?)");
        $stmt->execute([$folderPath, $type, $agencyCode]);
        
        echo json_encode(['success' => true, 'path' => $folderPath]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    
} catch (Exception $e) {
    error_log("API Folders Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

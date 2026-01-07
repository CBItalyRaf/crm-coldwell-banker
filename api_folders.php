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
        $path = rtrim($data['path'] ?? '', '/') . '/'; // Assicura trailing slash
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
        
        // Trova filepath di un file per determinare path fisico (STESSA LOGICA DELETE)
        $stmt = $pdo->prepare("SELECT filepath FROM documents WHERE folder_path = ? LIMIT 1");
        $stmt->execute([$path]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            // Usa filepath del documento
            $oldFullPath = __DIR__ . '/' . dirname($doc['filepath']);
            $parentPath = dirname($oldFullPath);
            $newFullPath = $parentPath . '/' . $newName;
        } else {
            // Cartella vuota - ricostruisci
            $stmt = $pdo->prepare("SELECT type, agency_code FROM folders WHERE folder_path = ?");
            $stmt->execute([$path]);
            $folderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$folderInfo) {
                echo json_encode(['success' => false, 'error' => 'Cartella non trovata nel DB']);
                exit;
            }
            
            $basePath = __DIR__ . '/documents';
            
            if ($folderInfo['type'] === 'common') {
                $oldFullPath = $basePath . '/common/' . rtrim($path, '/');
                $newFullPath = $basePath . '/common/' . $newName;
            } else {
                $oldFullPath = $basePath . '/agencies/' . $folderInfo['agency_code'] . '/public/' . rtrim($path, '/');
                $newFullPath = $basePath . '/agencies/' . $folderInfo['agency_code'] . '/public/' . $newName;
            }
        }
        
        error_log("Rename: oldFullPath='$oldFullPath', newFullPath='$newFullPath'");
        
        // Verifica che cartella esista
        if (!is_dir($oldFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella non trovata: ' . $oldFullPath]);
            exit;
        }
        
        // Verifica che nuovo nome non esista
        if (file_exists($newFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Esiste già una cartella con questo nome']);
            exit;
        }
        
        // Rinomina filesystem
        if (!rename($oldFullPath, $newFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Errore durante rinomina filesystem']);
            exit;
        }
        
        // Aggiorna DB
        $newPath = $newName . '/';
        $stmt = $pdo->prepare("UPDATE folders SET folder_path = ? WHERE folder_path = ?");
        $stmt->execute([$newPath, $path]);
        
        $stmt = $pdo->prepare("UPDATE documents SET folder_path = ? WHERE folder_path = ?");
        $stmt->execute([$newPath, $path]);
        
        // IMPORTANTE: Aggiorna anche filepath nei documenti!
        $oldPathSegment = rtrim($path, '/');
        $newPathSegment = $newName;
        $stmt = $pdo->prepare("UPDATE documents SET filepath = REPLACE(filepath, ?, ?) WHERE folder_path = ?");
        $stmt->execute(['/' . $oldPathSegment . '/', '/' . $newPathSegment . '/', $newPath]);
        
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

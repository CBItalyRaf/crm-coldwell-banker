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
    if ($action === 'delete_multiple') {
        $ids = $data['ids'] ?? [];
        
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'error' => 'IDs richiesti']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        $deleted = 0;
        foreach ($ids as $id) {
            // Recupera filepath per eliminare file fisico
            $stmt = $pdo->prepare("SELECT filepath FROM documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                $fullPath = __DIR__ . '/' . $doc['filepath'];
                
                // Elimina file fisico
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                // Elimina da DB
                $stmtDel = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                $stmtDel->execute([$id]);
                
                // Elimina relazioni agenzie
                $stmtDelRel = $pdo->prepare("DELETE FROM document_agencies WHERE document_id = ?");
                $stmtDelRel->execute([$id]);
                
                $deleted++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    }
    
    if ($action === 'delete_folder_files') {
        $folder = $data['folder'] ?? '';
        
        if (empty($folder)) {
            echo json_encode(['success' => false, 'error' => 'Cartella richiesta']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Recupera tutti i file nella cartella
        $stmt = $pdo->prepare("SELECT id, filepath FROM documents WHERE folder_path = ?");
        $stmt->execute([$folder]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deleted = 0;
        foreach ($docs as $doc) {
            $fullPath = __DIR__ . '/' . $doc['filepath'];
            
            // Elimina file fisico
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Elimina da DB
            $stmtDel = $pdo->prepare("DELETE FROM documents WHERE id = ?");
            $stmtDel->execute([$doc['id']]);
            
            // Elimina relazioni agenzie
            $stmtDelRel = $pdo->prepare("DELETE FROM document_agencies WHERE document_id = ?");
            $stmtDelRel->execute([$doc['id']]);
            
            $deleted++;
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API Documents Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

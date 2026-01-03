<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header('Location: documenti.php');
    exit;
}

$pdo = getDB();
$user = $_SESSION['crm_user'];
$documentId = (int)$_GET['id'];

try {
    // Recupera info documento
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();
    
    if (!$document) {
        throw new Exception('Documento non trovato.');
    }
    
    // Rimuovi file fisico
    $filePath = __DIR__ . '/' . $document['filepath'];
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            error_log("Warning: Could not delete file: {$filePath}");
        }
    }
    
    // Elimina da database (CASCADE eliminerà anche document_agencies)
    $deleteStmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
    $deleteStmt->execute([$documentId]);
    
    // Log attività
    error_log("Document deleted: {$document['original_filename']} by {$user['name']}");
    
    header('Location: documenti.php?deleted=1');
    exit;
    
} catch (Exception $e) {
    error_log("Document delete error: " . $e->getMessage());
    header('Location: documenti.php?error=' . urlencode($e->getMessage()));
    exit;
}

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
    // Validazione
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore durante l\'upload del file.');
    }
    
    $type = $_POST['type'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    $description = $_POST['description'] ?? '';
    
    if (!in_array($type, ['common', 'group', 'single'])) {
        throw new Exception('Tipo documento non valido.');
    }
    
    if (empty($category_id)) {
        throw new Exception('Categoria obbligatoria.');
    }
    
    // Validazione agenzie
    $agencies = [];
    if ($type === 'single') {
        if (empty($_POST['single_agency'])) {
            throw new Exception('Seleziona un\'agenzia.');
        }
        $agencies = [$_POST['single_agency']];
    } elseif ($type === 'group') {
        if (empty($_POST['group_agencies']) || !is_array($_POST['group_agencies'])) {
            throw new Exception('Seleziona almeno un\'agenzia per il gruppo.');
        }
        $agencies = $_POST['group_agencies'];
    }
    
    // Info file
    $file = $_FILES['file'];
    $originalFilename = basename($file['name']);
    $fileSize = $file['size'];
    $mimeType = $file['type'];
    
    // Genera nome file univoco
    $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '_' . time() . '.' . $extension;
    
    // Determina percorso basato su tipo
    $basePath = __DIR__ . '/documents';
    
    if ($type === 'common') {
        // Documenti comuni
        $categorySlug = $pdo->prepare("SELECT slug FROM document_categories WHERE id = ?");
        $categorySlug->execute([$category_id]);
        $catSlug = $categorySlug->fetchColumn();
        
        $destDir = $basePath . '/common/' . $catSlug;
    } else {
        // Documenti agenzia (singola o gruppo - salviamo in prima agenzia per semplicità)
        $firstAgency = $agencies[0];
        $destDir = $basePath . '/agencies/' . $firstAgency . '/' . $visibility;
    }
    
    // Crea directory se non esiste
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            throw new Exception('Impossibile creare la directory di destinazione.');
        }
    }
    
    $destPath = $destDir . '/' . $filename;
    $relativePath = str_replace(__DIR__ . '/', '', $destPath);
    
    // Sposta file
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Errore durante il salvataggio del file.');
    }
    
    // Inserisci in database
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO documents (
            filename, original_filename, filepath, file_size, mime_type,
            type, category_id, visibility, description, uploaded_by, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $filename,
        $originalFilename,
        $relativePath,
        $fileSize,
        $mimeType,
        $type,
        $category_id,
        $visibility,
        $description,
        $user['name']
    ]);
    
    $documentId = $pdo->lastInsertId();
    
    // Inserisci relazioni agenzie (se singola o gruppo)
    if ($type !== 'common' && !empty($agencies)) {
        $stmtAgency = $pdo->prepare("
            INSERT INTO document_agencies (document_id, agency_code) 
            VALUES (?, ?)
        ");
        
        foreach ($agencies as $agencyCode) {
            $stmtAgency->execute([$documentId, $agencyCode]);
        }
    }
    
    $pdo->commit();
    
    // Log attività
    error_log("Document uploaded: {$originalFilename} by {$user['name']} (type: {$type})");
    
    // Redirect con successo
    header('Location: documenti.php?success=1');
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Rimuovi file se già caricato
    if (isset($destPath) && file_exists($destPath)) {
        unlink($destPath);
    }
    
    error_log("Document upload error: " . $e->getMessage());
    
    // Redirect con errore
    header('Location: documenti.php?error=' . urlencode($e->getMessage()));
    exit;
}

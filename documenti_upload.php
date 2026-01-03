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
    $filesUploaded = $_FILES['files'] ?? null;
    
    if (!$filesUploaded || empty($filesUploaded['name'][0])) {
        throw new Exception('Nessun file caricato.');
    }
    
    $type = $_POST['type'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    $description = $_POST['description'] ?? '';
    $folder_path = !empty($_POST['folder_path']) ? trim($_POST['folder_path'], '/') . '/' : '';
    
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
    
    // Determina percorso base
    $basePath = __DIR__ . '/documents';
    
    if ($type === 'common') {
        $categorySlug = $pdo->prepare("SELECT slug FROM document_categories WHERE id = ?");
        $categorySlug->execute([$category_id]);
        $catSlug = $categorySlug->fetchColumn();
        $destDir = $basePath . '/common/' . $catSlug;
        if ($folder_path) {
            $destDir .= '/' . $folder_path;
        }
    } else {
        $firstAgency = $agencies[0];
        $destDir = $basePath . '/agencies/' . $firstAgency . '/' . $visibility;
        if ($folder_path) {
            $destDir .= '/' . $folder_path;
        }
    }
    
    // Crea directory se non esiste
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            throw new Exception('Impossibile creare la directory di destinazione.');
        }
    }
    
    // Inserisci cartella nella tabella folders se specificata
    if ($folder_path) {
        $insertFolder = $pdo->prepare("
            INSERT IGNORE INTO folders (folder_path, type, created_by) 
            VALUES (?, ?, ?)
        ");
        $insertFolder->execute([
            $folder_path,
            $type === 'common' ? 'common' : 'agency',
            $user['name']
        ]);
    }
    
    $pdo->beginTransaction();
    $uploadedCount = 0;
    
    // Processa ogni file
    $fileCount = count($filesUploaded['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($filesUploaded['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $originalFilename = basename($filesUploaded['name'][$i]);
        $fileSize = $filesUploaded['size'][$i];
        $mimeType = $filesUploaded['type'][$i];
        $tmpName = $filesUploaded['tmp_name'][$i];
        
        // Genera nome file univoco
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = uniqid('doc_') . '_' . time() . '_' . $i . '.' . $extension;
        
        $destPath = $destDir . '/' . $filename;
        $relativePath = str_replace(__DIR__ . '/', '', $destPath);
        
        // Sposta file
        if (!move_uploaded_file($tmpName, $destPath)) {
            continue;
        }
        
        // Inserisci in database
        $stmt = $pdo->prepare("
            INSERT INTO documents (
                filename, original_filename, filepath, file_size, mime_type,
                folder_path, type, category_id, visibility, description, uploaded_by, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $filename,
            $originalFilename,
            $relativePath,
            $fileSize,
            $mimeType,
            $folder_path ?: null,
            $type,
            $category_id,
            $visibility,
            $description,
            $user['name']
        ]);
        
        $documentId = $pdo->lastInsertId();
        
        // Inserisci relazioni agenzie
        if ($type !== 'common' && !empty($agencies)) {
            $stmtAgency = $pdo->prepare("
                INSERT INTO document_agencies (document_id, agency_code) 
                VALUES (?, ?)
            ");
            
            foreach ($agencies as $agencyCode) {
                $stmtAgency->execute([$documentId, $agencyCode]);
            }
        }
        
        $uploadedCount++;
    }
    
    $pdo->commit();
    
    if ($uploadedCount === 0) {
        throw new Exception('Nessun file è stato caricato correttamente.');
    }
    
    // Log attività
    error_log("Documents uploaded: {$uploadedCount} files by {$user['name']} (type: {$type})");
    
    // Redirect con successo
    header('Location: documenti.php?success=' . $uploadedCount);
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

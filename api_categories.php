<?php
require_once 'check_auth.php';
require_once 'config/database.php';

header('Content-Type: application/json');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM document_categories ORDER BY sort_order ASC, name ASC");
        $categories = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'add') {
        $name = $data['name'] ?? '';
        $icon = $data['icon'] ?? '📄';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nome richiesto']);
            exit;
        }
        
        // Trova ultimo sort_order
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM document_categories");
        $maxOrder = $stmt->fetch()['max_order'] ?? 0;
        
        $stmt = $pdo->prepare("INSERT INTO document_categories (name, icon, sort_order, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $icon, $maxOrder + 1]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }
    
    if ($action === 'edit') {
        $id = $data['id'] ?? 0;
        $name = $data['name'] ?? '';
        $icon = $data['icon'] ?? '📄';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nome richiesto']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE document_categories SET name = ?, icon = ? WHERE id = ?");
        $stmt->execute([$name, $icon, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete') {
        $id = $data['id'] ?? 0;
        
        // Verifica se ha documenti associati
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM documents WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'error' => 'Impossibile eliminare: ci sono ' . $count . ' documenti associati']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM document_categories WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'toggle') {
        $id = $data['id'] ?? 0;
        
        $stmt = $pdo->prepare("UPDATE document_categories SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Azione non valida']);

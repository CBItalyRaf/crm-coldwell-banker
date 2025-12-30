<?php
// api/delete_leave.php
// Cancella richiesta ferie (solo Raf e Sara)

require_once '../check_auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$pdo = getDB();
$user = $_SESSION['crm_user'];

// Solo Raf e Sara possono cancellare ferie
$canDelete = in_array($user['email'], [
    'raffaella.pace@cbitaly.it',
    'sara.mazoni@cbitaly.it'
]);

if (!$canDelete) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$leave_id = $data['leave_id'] ?? null;

if (!$leave_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'leave_id mancante']);
    exit;
}

// Cancella richiesta
try {
    $stmt = $pdo->prepare("DELETE FROM team_leaves WHERE id = :id");
    $stmt->execute(['id' => $leave_id]);
    
    echo json_encode(['success' => true, 'message' => 'Richiesta cancellata']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}

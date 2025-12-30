<?php
// api/request_leave.php
// Endpoint per richiedere ferie/assenze

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

// Leggi dati POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

// Validazione
$required = ['leave_type', 'start_date', 'end_date'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Campo obbligatorio mancante: $field"]);
        exit;
    }
}

// Validazione date
if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La data fine deve essere successiva alla data inizio']);
    exit;
}

// Inserisci richiesta
try {
    $stmt = $pdo->prepare("
        INSERT INTO team_leaves 
        (user_email, user_name, leave_type, start_date, end_date, notes, status)
        VALUES 
        (:user_email, :user_name, :leave_type, :start_date, :end_date, :notes, 'pending')
    ");
    
    $stmt->execute([
        'user_email' => $user['email'],
        'user_name' => $user['name'],
        'leave_type' => $data['leave_type'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'],
        'notes' => $data['notes'] ?? null
    ]);
    
    $leave_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'leave_id' => $leave_id,
        'message' => 'Richiesta inviata con successo'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}

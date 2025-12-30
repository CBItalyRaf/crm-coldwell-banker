<?php
// api/delete_event.php
// Cancella evento calendario (solo se creato dall'utente loggato)

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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$event_id = $data['event_id'] ?? null;

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'event_id mancante']);
    exit;
}

// Verifica che l'evento esista
$stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id = :id");
$stmt->execute(['id' => $event_id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Evento non trovato']);
    exit;
}

// BLOCCA cancellazione eventi da Booking
if ($event['created_by'] === 'Booking API') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Gli eventi da Booking non possono essere cancellati']);
    exit;
}

// Eventi NON da Booking → TUTTI possono cancellare

// Cancella evento
try {
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = :id");
    $stmt->execute(['id' => $event_id]);
    
    echo json_encode(['success' => true, 'message' => 'Evento cancellato']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}

<?php
// api/calendar_events.php
// Endpoint per creare/gestire eventi calendario pubblico
// Supporta autenticazione via:
// - Sessione (utenti loggati via Dashboard)
// - API Key (chiamate server-to-server, header X-API-Key)

require_once '../config/database.php';

header('Content-Type: application/json');

$pdo = getDB();
$authenticated = false;
$auth_source = null;
$created_by = 'system';

// METODO 1: Verifica API Key (prioritario per server-to-server)
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;

if ($api_key) {
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = :key AND is_active = 1");
    $stmt->execute(['key' => $api_key]);
    $key_data = $stmt->fetch();
    
    if ($key_data) {
        $authenticated = true;
        $auth_source = 'api_key';
        $created_by = $key_data['name'];
        
        // Aggiorna last_used_at
        $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = :id")
            ->execute(['id' => $key_data['id']]);
    }
}

// METODO 2: Verifica Sessione (fallback per utenti web)
if (!$authenticated) {
    session_start();
    if (isset($_SESSION['crm_user'])) {
        $authenticated = true;
        $auth_source = 'session';
        $created_by = $_SESSION['crm_user']['email'];
    }
}

// Se non autenticato → 401
if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Solo POST per creare eventi
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Leggi dati POST (JSON o form-data)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Se non è JSON, prova form-data
if (!$data) {
    $data = $_POST;
}

// Validazione campi obbligatori
$required = ['title', 'start_datetime', 'end_datetime'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Campo obbligatorio mancante: $field"]);
        exit;
    }
}

// Prepara dati
$title = $data['title'];
$description = $data['description'] ?? null;
$start_datetime = $data['start_datetime'];
$end_datetime = $data['end_datetime'];
$location = $data['location'] ?? null;
$event_type = $data['event_type'] ?? 'generic';
$color = $data['color'] ?? '#012169';

// Validazione datetime
try {
    new DateTime($start_datetime);
    new DateTime($end_datetime);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato datetime non valido. Usa: YYYY-MM-DD HH:MM:SS']);
    exit;
}

// Inserisci evento
try {
    $stmt = $pdo->prepare("
        INSERT INTO calendar_events 
        (title, description, start_datetime, end_datetime, location, event_type, color, created_by)
        VALUES 
        (:title, :description, :start_datetime, :end_datetime, :location, :event_type, :color, :created_by)
    ");
    
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime,
        'location' => $location,
        'event_type' => $event_type,
        'color' => $color,
        'created_by' => $created_by
    ]);
    
    $event_id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'event_id' => $event_id,
        'message' => 'Evento creato con successo',
        'auth_method' => $auth_source
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}

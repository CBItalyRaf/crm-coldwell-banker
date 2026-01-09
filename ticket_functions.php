<?php
// Helper functions per sistema ticket

/**
 * Genera prossimo numero ticket
 */
function generateTicketNumber($pdo) {
    $year = date('Y');
    
    // Lock per evitare race condition
    $pdo->exec("LOCK TABLES tickets WRITE");
    
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)), 0) + 1 as next_num
            FROM tickets 
            WHERE numero LIKE ?
        ");
        $stmt->execute(["TCK-$year-%"]);
        $num = $stmt->fetchColumn();
        
        $numero = sprintf('TCK-%s-%04d', $year, $num);
        
        $pdo->exec("UNLOCK TABLES");
        
        return $numero;
    } catch (Exception $e) {
        $pdo->exec("UNLOCK TABLES");
        throw $e;
    }
}

/**
 * Conta ticket non letti per utente
 */
function countUnreadTickets($pdo, $userEmail, $isStaff = true) {
    if ($isStaff) {
        // Staff: conta ticket con nuovi messaggi
        $sql = "SELECT COUNT(DISTINCT t.id) 
                FROM tickets t
                INNER JOIN ticket_messages tm ON t.id = tm.ticket_id
                LEFT JOIN ticket_reads tr ON t.id = tr.ticket_id AND tr.staff_email = ?
                WHERE tm.mittente_tipo = 'broker'
                AND (tr.last_read_message_id IS NULL OR tm.id > tr.last_read_message_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userEmail]);
    } else {
        // Broker: conta ticket con risposte staff non lette
        $sql = "SELECT COUNT(DISTINCT t.id) 
                FROM tickets t
                INNER JOIN ticket_messages tm ON t.id = tm.ticket_id
                LEFT JOIN ticket_reads tr ON t.id = tr.ticket_id AND tr.portal_user_id = ?
                WHERE tm.mittente_tipo = 'staff'
                AND (tr.last_read_message_id IS NULL OR tm.id > tr.last_read_message_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userEmail]);
    }
    
    return (int)$stmt->fetchColumn();
}

/**
 * Marca ticket come letto
 */
function markTicketAsRead($pdo, $ticketId, $userEmailOrId, $isStaff = true) {
    // Trova ultimo messaggio
    $stmt = $pdo->prepare("SELECT id FROM ticket_messages WHERE ticket_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$ticketId]);
    $lastMessageId = $stmt->fetchColumn();
    
    if (!$lastMessageId) return;
    
    if ($isStaff) {
        $sql = "INSERT INTO ticket_reads (ticket_id, staff_email, last_read_message_id) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE last_read_message_id = ?, read_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticketId, $userEmailOrId, $lastMessageId, $lastMessageId]);
    } else {
        $sql = "INSERT INTO ticket_reads (ticket_id, portal_user_id, last_read_message_id) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE last_read_message_id = ?, read_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticketId, $userEmailOrId, $lastMessageId, $lastMessageId]);
    }
}

/**
 * Verifica se utente può vedere ticket
 */
function canViewTicket($ticket, $userId, $isStaff = true, $agencyId = null) {
    if ($isStaff) {
        // Staff vede tutto
        return true;
    }
    
    // Broker
    if ($ticket['agenzia_id'] != $agencyId) {
        return false; // Non della sua agenzia
    }
    
    if (!$ticket['is_privato']) {
        return true; // Pubblico, tutti dell'agenzia vedono
    }
    
    // Privato: solo destinatario
    return $ticket['destinatario_portal_user_id'] == $userId;
}

/**
 * Invia email notifica nuovo ticket
 */
function sendTicketNotification($pdo, $ticketId, $type = 'new') {
    // Carica ticket
    $stmt = $pdo->prepare("SELECT * FROM v_tickets_complete WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) return false;
    
    $ticketUrl = 'https://admin.mycb.it/ticket_detail.php?id=' . $ticketId;
    $portalUrl = 'https://mycb.it/ticket_detail.php?id=' . $ticketId;
    
    if ($type === 'new' && $ticket['creato_da_tipo'] === 'broker') {
        // Nuovo ticket da broker → notifica staff
        notifyStaff($pdo, $ticket, $ticketUrl);
    } elseif ($type === 'new' && $ticket['creato_da_tipo'] === 'staff') {
        // Nuovo ticket da staff → notifica broker
        notifyBroker($pdo, $ticket, $portalUrl);
    } elseif ($type === 'reply') {
        // Risposta → notifica destinatario
        // TODO: implementare
    }
    
    return true;
}

function notifyStaff($pdo, $ticket, $url) {
    // Trova staff con notifiche attive
    $stmt = $pdo->query("SELECT user_email as email FROM user_preferences 
                         WHERE notify_ticket_email = 1");
    $recipients = $stmt->fetchAll();
    
    foreach ($recipients as $user) {
        $subject = "🎫 Nuovo Ticket: " . $ticket['titolo'];
        $body = "
        <h2>Nuovo Ticket da {$ticket['agenzia_name']}</h2>
        <p><strong>Numero:</strong> {$ticket['numero']}</p>
        <p><strong>Categoria:</strong> {$ticket['categoria_nome']}</p>
        <p><strong>Priorità:</strong> {$ticket['priorita']}</p>
        <p><strong>Descrizione:</strong></p>
        <p>" . nl2br(htmlspecialchars($ticket['descrizione'])) . "</p>
        <p><a href='$url' style='background:#1F69FF;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block'>👉 Visualizza Ticket</a></p>
        ";
        
        // TODO: Invia email (integrare con sistema email esistente)
        // sendEmail($user['email'], $subject, $body);
    }
}

function notifyBroker($pdo, $ticket, $url) {
    // Trova email destinatario
    $email = null;
    if ($ticket['destinatario_portal_user_id']) {
        $stmt = $pdo->prepare("SELECT email FROM portal_users WHERE id = ?");
        $stmt->execute([$ticket['destinatario_portal_user_id']]);
        $email = $stmt->fetchColumn();
    }
    
    if (!$email) return;
    
    $subject = "📩 Nuova Comunicazione da Coldwell Banker Italy";
    $body = "
    <h2>Nuova Comunicazione</h2>
    <p><strong>Oggetto:</strong> {$ticket['titolo']}</p>
    <p><strong>Messaggio:</strong></p>
    <p>" . nl2br(htmlspecialchars($ticket['descrizione'])) . "</p>
    <p><a href='$url' style='background:#1F69FF;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block'>👉 Visualizza e Rispondi</a></p>
    ";
    
    // TODO: Invia email
    // sendEmail($email, $subject, $body);
}

/**
 * Upload allegato ticket
 */
function uploadTicketAttachment($file, $ticketId, $messageId = null, $userId = null, $portalUserId = null) {
    $uploadDir = '/var/www/admin.mycb.it/ticket_attachments/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('ticket_' . $ticketId . '_') . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }
    
    return [
        'filename' => $filename,
        'original_filename' => $file['name'],
        'filepath' => $filepath,
        'filesize' => $file['size'],
        'mime_type' => $file['type']
    ];
}

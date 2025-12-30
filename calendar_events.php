<?php
// helpers/calendar_events.php
// Funzioni per gestire eventi calendario pubblico

/**
 * Recupera eventi in un range di date
 * @param PDO $pdo
 * @param string|null $start_date - Data inizio range (YYYY-MM-DD)
 * @param string|null $end_date - Data fine range (YYYY-MM-DD)
 * @return array Eventi
 */
function getCalendarEvents($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT * FROM calendar_events WHERE 1=1";
    $params = [];
    
    if ($start_date) {
        $sql .= " AND start_datetime >= :start_date";
        $params['start_date'] = $start_date;
    }
    
    if ($end_date) {
        $sql .= " AND end_datetime <= :end_date";
        $params['end_date'] = $end_date;
    }
    
    $sql .= " ORDER BY start_datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Recupera eventi prossimi N giorni
 * @param PDO $pdo
 * @param int $days - Numero giorni (default 30)
 * @return array Eventi
 */
function getUpcomingEvents($pdo, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT * FROM calendar_events 
        WHERE start_datetime >= CURDATE() 
        AND start_datetime <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
        ORDER BY start_datetime ASC
    ");
    $stmt->execute(['days' => $days]);
    return $stmt->fetchAll();
}

/**
 * Recupera singolo evento
 * @param PDO $pdo
 * @param int $event_id
 * @return array|false Evento o false
 */
function getEventById($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id = :id");
    $stmt->execute(['id' => $event_id]);
    return $stmt->fetch();
}

/**
 * Elimina evento
 * @param PDO $pdo
 * @param int $event_id
 * @return bool
 */
function deleteEvent($pdo, $event_id) {
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = :id");
    return $stmt->execute(['id' => $event_id]);
}

/**
 * Formatta eventi per FullCalendar
 * @param array $events - Eventi da database
 * @return array Eventi in formato FullCalendar
 */
function formatEventsForCalendar($events) {
    $formatted = [];
    
    foreach ($events as $event) {
        $formatted[] = [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['start_datetime'],
            'end' => $event['end_datetime'],
            'color' => $event['color'],
            'extendedProps' => [
                'description' => $event['description'],
                'location' => $event['location'],
                'event_type' => $event['event_type'],
                'created_by' => $event['created_by']
            ]
        ];
    }
    
    return $formatted;
}

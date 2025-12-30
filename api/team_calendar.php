<?php
// api/team_calendar.php
// Restituisce eventi calendario + ferie team in formato FullCalendar

require_once '../config/database.php';

header('Content-Type: application/json');

$pdo = getDB();
$events = [];

// ========================================
// 1. EVENTI DA CALENDAR_EVENTS (Booking, etc)
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        id,
        title,
        description,
        start_datetime,
        end_datetime,
        location,
        event_type,
        color,
        created_by
    FROM calendar_events
    ORDER BY start_datetime ASC
");

$stmt->execute();
$calendarEvents = $stmt->fetchAll();

foreach ($calendarEvents as $evt) {
    $events[] = [
        'id' => 'event_' . $evt['id'],
        'title' => $evt['title'],
        'start' => $evt['start_datetime'],
        'end' => $evt['end_datetime'],
        'color' => $evt['color'],
        'extendedProps' => [
            'type' => 'event',
            'description' => $evt['description'],
            'location' => $evt['location'],
            'event_type' => $evt['event_type'],
            'created_by' => $evt['created_by']
        ]
    ];
}

// ========================================
// 2. FERIE DA TEAM_LEAVES
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        id,
        user_email,
        user_name,
        leave_type,
        start_date,
        end_date,
        notes,
        status
    FROM team_leaves
    WHERE status = 'approved'
    ORDER BY start_date ASC
");

$stmt->execute();
$leaves = $stmt->fetchAll();

foreach ($leaves as $leave) {
    // Colori in base al tipo
    $colors = [
        'ferie' => '#10B981',        // Verde
        'malattia' => '#EF4444',     // Rosso
        'permesso' => '#F59E0B',     // Giallo
        'smartworking' => '#3B82F6', // Blu
        'altro' => '#6B7280'         // Grigio
    ];
    
    $color = $colors[$leave['leave_type']] ?? '#6B7280';
    
    // Icone per tipo
    $icons = [
        'ferie' => '🏖️',
        'malattia' => '🤒',
        'permesso' => '📋',
        'smartworking' => '🏠',
        'altro' => '📅'
    ];
    
    $icon = $icons[$leave['leave_type']] ?? '📅';
    
    $events[] = [
        'id' => 'leave_' . $leave['id'],
        'title' => $icon . ' ' . $leave['user_name'],
        'start' => $leave['start_date'],
        'end' => date('Y-m-d', strtotime($leave['end_date'] . ' +1 day')), // FullCalendar end is exclusive
        'color' => $color,
        'allDay' => true,
        'extendedProps' => [
            'type' => 'leave',
            'leave_type' => $leave['leave_type'],
            'user_email' => $leave['user_email'],
            'user_name' => $leave['user_name'],
            'notes' => $leave['notes'],
            'status' => $leave['status']
        ]
    ];
}

echo json_encode($events);

<?php
require_once '../check_auth.php';
require_once '../config/database.php';
require_once '../helpers/scadenze.php';

header('Content-Type: application/json');

$pdo = getDB();

// Recupera tutte le scadenze (non solo prossimi 30 giorni)
$stmt = $pdo->prepare("
    SELECT 
        ags.id,
        ags.agency_id,
        ags.service_name,
        ags.expiration_date,
        sm.service_name as display_name,
        a.name as agency_name,
        a.code as agency_code,
        DATEDIFF(ags.expiration_date, CURDATE()) as days_remaining
    FROM agency_services ags
    JOIN agencies a ON ags.agency_id = a.id
    JOIN services_master sm ON sm.service_name = (
        CASE ags.service_name
            WHEN 'cb_suite' THEN 'CB Suite'
            WHEN 'canva' THEN 'Canva Pro'
            WHEN 'regold' THEN 'Regold'
            WHEN 'james_edition' THEN 'James Edition'
            WHEN 'docudrop' THEN 'Docudrop'
            WHEN 'unique' THEN 'Unique Estates'
            WHEN 'casella_mail_agenzia' THEN 'Casella Mail Agenzia'
            WHEN 'euromq' THEN 'EuroMq'
            WHEN 'gestim' THEN 'Gestim'
        END
    )
    WHERE ags.is_active = 1
    AND ags.expiration_date IS NOT NULL
    ORDER BY ags.expiration_date ASC
");

$stmt->execute();
$scadenze = $stmt->fetchAll();

// Converti in formato FullCalendar
$events = [];
foreach ($scadenze as $scad) {
    $urgency = getScadenzaUrgency($scad['days_remaining']);
    
    // Colori basati su urgenza
    $color = '#F59E0B'; // Giallo default
    if ($urgency === 'critical') {
        $color = '#EF4444'; // Rosso
    } elseif ($urgency === 'warning') {
        $color = '#F97316'; // Arancione
    }
    
    $events[] = [
        'title' => $scad['display_name'] . ' - ' . $scad['agency_name'],
        'start' => $scad['expiration_date'],
        'color' => $color,
        'url' => '../agenzia_detail.php?code=' . urlencode($scad['agency_code']),
        'extendedProps' => [
            'agency_code' => $scad['agency_code'],
            'service_name' => $scad['display_name'],
            'days_remaining' => $scad['days_remaining']
        ]
    ];
}

echo json_encode($events);

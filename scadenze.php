<?php
// helpers/scadenze.php
// Funzioni per gestire scadenze servizi

function getScadenzeImminenti($pdo, $days = 30) {
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
        AND ags.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
        ORDER BY ags.expiration_date ASC
    ");
    
    $stmt->execute(['days' => $days]);
    return $stmt->fetchAll();
}

function getScadenzeCount($pdo, $days = 30) {
    $scadenze = getScadenzeImminenti($pdo, $days);
    return count($scadenze);
}

function getScadenzaUrgency($days_remaining) {
    if ($days_remaining <= 7) return 'critical'; // Rosso
    if ($days_remaining <= 14) return 'warning'; // Arancione
    return 'info'; // Giallo
}

function getScadenzaIcon($urgency) {
    switch($urgency) {
        case 'critical': return '🔴';
        case 'warning': return '🟠';
        default: return '🟡';
    }
}

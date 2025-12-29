<?php
require_once '../check_auth.php';
require_once '../config/database.php';
require_once '../helpers/scadenze.php';

header('Content-Type: application/json');

$pdo = getDB();

$events = [];

// ========================================
// 1. SCADENZE SERVIZI (Rosso/Arancione/Giallo)
// ========================================
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

foreach ($scadenze as $scad) {
    $urgency = getScadenzaUrgency($scad['days_remaining']);
    
    $color = '#F59E0B'; // Giallo
    if ($urgency === 'critical') {
        $color = '#EF4444'; // Rosso
    } elseif ($urgency === 'warning') {
        $color = '#F97316'; // Arancione
    }
    
    $events[] = [
        'title' => '⚙️ ' . $scad['display_name'] . ' - ' . $scad['agency_name'],
        'start' => $scad['expiration_date'],
        'color' => $color,
        'url' => '../agenzia_detail.php?code=' . urlencode($scad['agency_code']),
        'extendedProps' => [
            'type' => 'servizio',
            'agency_code' => $scad['agency_code'],
            'service_name' => $scad['display_name'],
            'days_remaining' => $scad['days_remaining']
        ]
    ];
}

// ========================================
// 2. AVVISI RINNOVO CONTRATTI (Blu - 4 eventi per contratto)
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        ac.id,
        ac.agency_id,
        ac.contract_end_date,
        ac.contract_start_date,
        a.name as agency_name,
        a.code as agency_code
    FROM agency_contracts ac
    JOIN agencies a ON ac.agency_id = a.id
    WHERE ac.contract_end_date IS NOT NULL
    AND ac.status = 'active'
");

$stmt->execute();
$contratti = $stmt->fetchAll();

foreach ($contratti as $contratto) {
    $endDate = new DateTime($contratto['contract_end_date']);
    
    // 4 avvisi di rinnovo
    $avvisi = [
        ['mesi' => 12, 'label' => '1 anno', 'color' => '#3B82F6'],   // Blu chiaro
        ['mesi' => 6,  'label' => '6 mesi', 'color' => '#2563EB'],   // Blu medio
        ['mesi' => 3,  'label' => '3 mesi', 'color' => '#1D4ED8'],   // Blu scuro
        ['mesi' => 1,  'label' => '1 mese', 'color' => '#1E40AF']    // Blu molto scuro
    ];
    
    foreach ($avvisi as $avviso) {
        $avvisoDate = clone $endDate;
        $avvisoDate->modify("-{$avviso['mesi']} months");
        
        // Solo se la data avviso è futura o recente (ultimi 3 mesi)
        $now = new DateTime();
        $diff = $now->diff($avvisoDate)->days;
        
        if ($avvisoDate >= $now->modify('-3 months')) {
            $events[] = [
                'title' => '📄 Rinnovo Contratto - ' . $contratto['agency_name'] . ' (tra ' . $avviso['label'] . ')',
                'start' => $avvisoDate->format('Y-m-d'),
                'color' => $avviso['color'],
                'url' => '../agenzia_detail.php?code=' . urlencode($contratto['agency_code']),
                'extendedProps' => [
                    'type' => 'rinnovo_contratto',
                    'agency_code' => $contratto['agency_code'],
                    'contract_end' => $contratto['contract_end_date'],
                    'mesi_rimanenti' => $avviso['mesi']
                ]
            ];
        }
    }
}

// ========================================
// 3. ANNIVERSARI CONTRATTI (Verde - 1° anno + ogni 5 anni)
// ========================================
foreach ($contratti as $contratto) {
    if (empty($contratto['contract_start_date'])) continue;
    
    $startDate = new DateTime($contratto['contract_start_date']);
    $now = new DateTime();
    
    // Calcola anni trascorsi
    $anniTrascorsi = $now->diff($startDate)->y;
    
    // Genera anniversari fino a +5 anni nel futuro
    for ($anno = 1; $anno <= $anniTrascorsi + 5; $anno++) {
        // Solo 1° anno + multipli di 5
        if ($anno == 1 || $anno % 5 == 0) {
            $anniversarioDate = clone $startDate;
            $anniversarioDate->modify("+{$anno} years");
            
            $events[] = [
                'title' => '🎉 ' . $anno . '° Anniversario - ' . $contratto['agency_name'],
                'start' => $anniversarioDate->format('Y-m-d'),
                'color' => '#10B981', // Verde
                'url' => '../agenzia_detail.php?code=' . urlencode($contratto['agency_code']),
                'extendedProps' => [
                    'type' => 'anniversario',
                    'agency_code' => $contratto['agency_code'],
                    'anni' => $anno,
                    'data_inizio' => $contratto['contract_start_date']
                ]
            ];
        }
    }
}

echo json_encode($events);

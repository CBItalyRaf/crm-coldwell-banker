<?php
// Widget Scadenze Imminenti
// Da includere in index.php

require_once 'helpers/scadenze.php';
require_once 'helpers/user_preferences.php';

$userPrefs = getUserPreferences($pdo, $user['id']);
$scadenze = [];

if ($userPrefs['notify_scadenze_dashboard']) {
    $scadenze = getScadenzeImminenti($pdo, 30);
}
?>

<?php if($userPrefs['notify_scadenze_dashboard'] && !empty($scadenze)): ?>
<div style="background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:1.5rem;margin-bottom:2rem;border-left:6px solid #F59E0B">
    <h2 style="font-size:1.25rem;font-weight:600;color:var(--cb-midnight);margin:0 0 1rem 0;display:flex;align-items:center;gap:.5rem">
        ⚠️ Scadenze Imminenti (prossimi 30 giorni)
        <span style="background:#F59E0B;color:white;font-size:.75rem;padding:.25rem .5rem;border-radius:999px;font-weight:700"><?= count($scadenze) ?></span>
    </h2>
    
    <div style="display:grid;gap:.75rem">
        <?php foreach($scadenze as $scad): 
            $urgency = getScadenzaUrgency($scad['days_remaining']);
            $icon = getScadenzaIcon($urgency);
            $bgColor = $urgency === 'critical' ? '#FEE2E2' : ($urgency === 'warning' ? '#FED7AA' : '#FEF3C7');
            $borderColor = $urgency === 'critical' ? '#EF4444' : ($urgency === 'warning' ? '#F97316' : '#F59E0B');
        ?>
        <div style="background:<?= $bgColor ?>;border-left:4px solid <?= $borderColor ?>;padding:1rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
            <div style="flex:1">
                <div style="font-weight:600;color:var(--cb-midnight);margin-bottom:.25rem">
                    <?= $icon ?> <?= htmlspecialchars($scad['display_name']) ?> - <?= htmlspecialchars($scad['agency_name']) ?>
                </div>
                <div style="font-size:.85rem;color:var(--cb-gray)">
                    Scadenza: <?= date('d/m/Y', strtotime($scad['expiration_date'])) ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1rem">
                <div style="font-weight:700;font-size:1.1rem;color:<?= $borderColor ?>">
                    <?= $scad['days_remaining'] ?> giorni
                </div>
                <a href="agenzia_detail.php?code=<?= urlencode($scad['agency_code']) ?>" 
                   style="background:var(--cb-blue);color:white;padding:.5rem 1rem;border-radius:6px;text-decoration:none;font-size:.85rem;white-space:nowrap">
                    Vai →
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

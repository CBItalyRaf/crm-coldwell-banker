<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Offboarding - CRM Coldwell Banker";
$pdo = getDB();

// Carica offboarding attivi
$stmt = $pdo->query("
    SELECT o.*, a.name as agency_name, a.code as agency_code, a.city,
    (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id) as total_tasks,
    (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id AND is_completed = 1) as completed_tasks
    FROM offboardings o
    JOIN agencies a ON o.agency_id = a.id
    WHERE o.status = 'active'
    ORDER BY o.started_at DESC
");
$active_offboardings = $stmt->fetchAll();

// Carica offboarding completati (ultimi 10)
$stmt = $pdo->query("
    SELECT o.*, a.name as agency_name, a.code as agency_code, a.city
    FROM offboardings o
    JOIN agencies a ON o.agency_id = a.id
    WHERE o.status = 'completed'
    ORDER BY o.completed_at DESC
    LIMIT 10
");
$completed_offboardings = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.page-title{font-size:1.75rem;font-weight:600;margin-bottom:.5rem}
.page-subtitle{color:var(--cb-gray);font-size:.95rem}
.section{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.section-title{font-size:1.25rem;font-weight:600;color:#EF4444;margin-bottom:1.5rem;text-transform:uppercase;letter-spacing:.05em}
.offboarding-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem}
.offboarding-card{border:2px solid #FEE2E2;border-radius:12px;padding:1.5rem;transition:all .2s;cursor:pointer;position:relative}
.offboarding-card:hover{border-color:#EF4444;box-shadow:0 4px 12px rgba(239,68,68,.15)}
.offboarding-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.offboarding-name{font-size:1.1rem;font-weight:600;color:var(--cb-midnight)}
.offboarding-code{font-size:.85rem;color:var(--cb-gray);margin-top:.25rem}
.offboarding-badge{background:#FEE2E2;color:#991B1B;padding:.25rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.offboarding-meta{font-size:.85rem;color:var(--cb-gray);margin-bottom:1rem}
.offboarding-progress{margin-bottom:.75rem}
.offboarding-progress-text{font-size:.85rem;color:var(--cb-gray);margin-bottom:.5rem}
.offboarding-progress-bar{height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden}
.offboarding-progress-fill{height:100%;background:#EF4444;transition:width .3s}
.completed-list{display:flex;flex-direction:column;gap:.75rem}
.completed-item{padding:1rem;background:var(--bg);border-radius:8px;border-left:4px solid #10B981;cursor:pointer;transition:all .2s}
.completed-item:hover{background:#F3F4F6;transform:translateX(4px)}
.completed-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.completed-name{font-weight:600;color:var(--cb-midnight)}
.completed-date{font-size:.85rem;color:var(--cb-gray)}
.empty-state{text-align:center;padding:3rem;color:var(--cb-gray)}
.empty-icon{font-size:3rem;margin-bottom:1rem;opacity:.3}
</style>

<div class="page-header">
<h1 class="page-title">📤 Offboarding</h1>
<p class="page-subtitle">Gestione chiusura agenzie</p>
</div>

<!-- Offboarding Attivi -->
<div class="section">
<h2 class="section-title">⏳ Offboarding in Corso (<?= count($active_offboardings) ?>)</h2>

<?php if (empty($active_offboardings)): ?>
<div class="empty-state">
<div class="empty-icon">✅</div>
<p>Nessun offboarding attivo al momento</p>
</div>
<?php else: ?>
<div class="offboarding-grid">
<?php foreach ($active_offboardings as $off): 
$progress = $off['total_tasks'] > 0 ? round(($off['completed_tasks'] / $off['total_tasks']) * 100) : 0;
?>
<div class="offboarding-card" onclick="window.location.href='offboarding_detail.php?agency_id=<?= $off['agency_id'] ?>'">
<div class="offboarding-header">
<div>
<div class="offboarding-name"><?= htmlspecialchars($off['agency_name']) ?></div>
<div class="offboarding-code"><?= htmlspecialchars($off['agency_code']) ?> • <?= htmlspecialchars($off['city']) ?></div>
</div>
<span class="offboarding-badge">In Corso</span>
</div>

<div class="offboarding-meta">
📅 Avviato <?= date('d/m/Y', strtotime($off['started_at'])) ?>
</div>

<div class="offboarding-progress">
<div class="offboarding-progress-text">
Completamento: <?= $off['completed_tasks'] ?>/<?= $off['total_tasks'] ?> task (<?= $progress ?>%)
</div>
<div class="offboarding-progress-bar">
<div class="offboarding-progress-fill" style="width:<?= $progress ?>%"></div>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Offboarding Completati -->
<div class="section">
<h2 class="section-title">✅ Completati Recentemente</h2>

<?php if (empty($completed_offboardings)): ?>
<div class="empty-state">
<div class="empty-icon">📋</div>
<p>Nessun offboarding completato</p>
</div>
<?php else: ?>
<div class="completed-list">
<?php foreach ($completed_offboardings as $off): ?>
<div class="completed-item" onclick="window.location.href='agenzia_detail.php?code=<?= urlencode($off['agency_code']) ?>'">
<div class="completed-header">
<div class="completed-name">
✓ <?= htmlspecialchars($off['agency_name']) ?> (<?= htmlspecialchars($off['agency_code']) ?>)
</div>
<div class="completed-date">
Completato: <?= date('d/m/Y', strtotime($off['completed_at'])) ?>
</div>
</div>
<div style="font-size:.85rem;color:var(--cb-gray)">
<?= htmlspecialchars($off['city']) ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>

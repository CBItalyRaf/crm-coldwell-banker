<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Onboarding - CRM Coldwell Banker";
$pdo = getDB();

// Carica onboarding attivi
$stmt = $pdo->query("
    SELECT o.*, a.name as agency_name, a.code as agency_code, a.city,
    (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id) as total_tasks,
    (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id AND is_completed = 1) as completed_tasks
    FROM onboardings o
    JOIN agencies a ON o.agency_id = a.id
    WHERE o.status = 'active'
    ORDER BY o.started_at DESC
");
$active_onboardings = $stmt->fetchAll();

// Carica onboarding completati (ultimi 10)
$stmt = $pdo->query("
    SELECT o.*, a.name as agency_name, a.code as agency_code, a.city
    FROM onboardings o
    JOIN agencies a ON o.agency_id = a.id
    WHERE o.status = 'completed'
    ORDER BY o.completed_at DESC
    LIMIT 10
");
$completed_onboardings = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.page-title{font-size:1.75rem;font-weight:600;margin-bottom:.5rem}
.page-subtitle{color:var(--cb-gray);font-size:.95rem}
.section{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.section-title{font-size:1.25rem;font-weight:600;color:#10B981;margin-bottom:1.5rem;text-transform:uppercase;letter-spacing:.05em}
.onboarding-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem}
.onboarding-card{border:2px solid #D1FAE5;border-radius:12px;padding:1.5rem;transition:all .2s;cursor:pointer;position:relative}
.onboarding-card:hover{border-color:#10B981;box-shadow:0 4px 12px rgba(16,185,129,.15)}
.onboarding-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.onboarding-name{font-size:1.1rem;font-weight:600;color:var(--cb-midnight)}
.onboarding-code{font-size:.85rem;color:var(--cb-gray);margin-top:.25rem}
.onboarding-badge{background:#D1FAE5;color:#065F46;padding:.25rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.onboarding-meta{font-size:.85rem;color:var(--cb-gray);margin-bottom:1rem}
.onboarding-progress{margin-bottom:.75rem}
.onboarding-progress-text{font-size:.85rem;color:var(--cb-gray);margin-bottom:.5rem}
.onboarding-progress-bar{height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden}
.onboarding-progress-fill{height:100%;background:#10B981;transition:width .3s}
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
<h1 class="page-title">🚀 Onboarding</h1>
<p class="page-subtitle">Gestione apertura agenzie</p>
</div>

<!-- Onboarding Attivi -->
<div class="section">
<h2 class="section-title">⏳ Onboarding in Corso (<?= count($active_onboardings) ?>)</h2>

<?php if (empty($active_onboardings)): ?>
<div class="empty-state">
<div class="empty-icon">✅</div>
<p>Nessun onboarding attivo al momento</p>
</div>
<?php else: ?>
<div class="onboarding-grid">
<?php foreach ($active_onboardings as $on): 
$progress = $on['total_tasks'] > 0 ? round(($on['completed_tasks'] / $on['total_tasks']) * 100) : 0;
?>
<div class="onboarding-card" onclick="window.location.href='onboarding_detail.php?agency_id=<?= $on['agency_id'] ?>'">
<div class="onboarding-header">
<div>
<div class="onboarding-name"><?= htmlspecialchars($on['agency_name']) ?></div>
<div class="onboarding-code"><?= htmlspecialchars($on['agency_code']) ?> • <?= htmlspecialchars($on['city']) ?></div>
</div>
<span class="onboarding-badge">In Corso</span>
</div>

<div class="onboarding-meta">
📅 Avviato <?= date('d/m/Y', strtotime($on['started_at'])) ?>
</div>

<div class="onboarding-progress">
<div class="onboarding-progress-text">
Completamento: <?= $on['completed_tasks'] ?>/<?= $on['total_tasks'] ?> task (<?= $progress ?>%)
</div>
<div class="onboarding-progress-bar">
<div class="onboarding-progress-fill" style="width:<?= $progress ?>%"></div>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Onboarding Completati -->
<div class="section">
<h2 class="section-title">✅ Completati Recentemente</h2>

<?php if (empty($completed_onboardings)): ?>
<div class="empty-state">
<div class="empty-icon">📋</div>
<p>Nessun onboarding completato</p>
</div>
<?php else: ?>
<div class="completed-list">
<?php foreach ($completed_onboardings as $on): ?>
<div class="completed-item" onclick="window.location.href='agenzia_detail.php?code=<?= urlencode($on['agency_code']) ?>'">
<div class="completed-header">
<div class="completed-name">
✓ <?= htmlspecialchars($on['agency_name']) ?> (<?= htmlspecialchars($on['agency_code']) ?>)
</div>
<div class="completed-date">
Completato: <?= date('d/m/Y', strtotime($on['completed_at'])) ?>
</div>
</div>
<div style="font-size:.85rem;color:var(--cb-gray)">
<?= htmlspecialchars($on['city']) ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

$pageTitle = "Offboarding - CRM Coldwell Banker";
$pdo = getDB();

$agency_id = $_GET['agency_id'] ?? '';

if (!$agency_id) {
    header('Location: agenzie.php');
    exit;
}

// Carica agenzia
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE id = :id");
$stmt->execute(['id' => $agency_id]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

// Carica offboarding attivo
$stmt = $pdo->prepare("SELECT * FROM offboardings WHERE agency_id = :agency_id AND status = 'active'");
$stmt->execute(['agency_id' => $agency_id]);
$offboarding = $stmt->fetch();

if (!$offboarding) {
    header("Location: agenzia_detail.php?code=" . urlencode($agency['code']));
    exit;
}

// Gestione completamento task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'complete_task') {
        $task_id = $_POST['task_id'];
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE offboarding_tasks SET is_completed = 1, completed_by = ?, completed_at = NOW(), completion_notes = ? WHERE id = ?");
        $stmt->execute([$_SESSION['crm_user']['id'] ?? 0, $notes, $task_id]);
        
        // Log
        $stmt = $pdo->prepare("SELECT task_name FROM offboarding_tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        logAudit($pdo, $_SESSION['crm_user']['id'] ?? 0, $_SESSION['crm_user']['email'], 'offboarding_tasks', $task_id, 'UPDATE', [
            'task_name' => ['old' => $task['task_name'], 'new' => $task['task_name'] . ' - COMPLETATO']
        ]);
        
        header("Location: offboarding_detail.php?agency_id=$agency_id&success=task_completed");
        exit;
    }
    
    if ($_POST['action'] === 'uncomplete_task') {
        $task_id = $_POST['task_id'];
        
        $stmt = $pdo->prepare("UPDATE offboarding_tasks SET is_completed = 0, completed_by = NULL, completed_at = NULL, completion_notes = NULL WHERE id = ?");
        $stmt->execute([$task_id]);
        
        header("Location: offboarding_detail.php?agency_id=$agency_id");
        exit;
    }
    
    if ($_POST['action'] === 'close_offboarding' && in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
        
        // Aggiorna offboarding
        $pdo->prepare("UPDATE offboardings SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$offboarding['id']]);
        
        // Aggiorna status agenzia
        $pdo->prepare("UPDATE agencies SET status = 'Closed' WHERE id = ?")->execute([$agency_id]);
        
        // Log
        logAudit($pdo, $_SESSION['crm_user']['id'] ?? 0, $_SESSION['crm_user']['email'], 'agencies', $agency_id, 'UPDATE', [
            'status' => ['old' => 'In Offboarding', 'new' => 'Closed']
        ]);
        
        header("Location: agenzia_detail.php?code=" . urlencode($agency['code']) . "&success=offboarding_completed");
        exit;
    }
}

// Carica task
$stmt = $pdo->prepare("SELECT * FROM offboarding_tasks WHERE offboarding_id = :offboarding_id ORDER BY phase, task_order");
$stmt->execute(['offboarding_id' => $offboarding['id']]);
$all_tasks = $stmt->fetchAll();

// Raggruppa per fase
$phases = [
    1 => ['name' => 'Fase 1: Contratti & Amministrazione', 'tasks' => []],
    2 => ['name' => 'Fase 2: Strumenti Interni', 'tasks' => []],
    3 => ['name' => 'Fase 3: Strumenti Esterni', 'tasks' => []],
    4 => ['name' => 'Fase 4: Debranding', 'tasks' => []]
];

foreach ($all_tasks as $task) {
    $phases[$task['phase']]['tasks'][] = $task;
}

// Calcola progress
$total_tasks = count($all_tasks);
$completed_tasks = count(array_filter($all_tasks, fn($t) => $t['is_completed']));
$progress = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.header-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agency-info{color:var(--cb-gray);font-size:.95rem;margin-bottom:1rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.progress-section{display:flex;align-items:center;gap:1rem}
.progress-bar-container{flex:1;height:20px;background:#E5E7EB;border-radius:10px;overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,#EF4444,#DC2626);transition:width .3s}
.progress-text{font-size:.9rem;font-weight:600;color:var(--cb-midnight);min-width:80px;text-align:right}
.btn-close{background:#10B981;color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;transition:background .2s}
.btn-close:hover{background:#059669}
.btn-close:disabled{background:#D1D5DB;cursor:not-allowed}
.phase-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:2rem;overflow:hidden}
.phase-header{background:#EF4444;color:white;padding:1rem 1.5rem;font-size:1.1rem;font-weight:600;display:flex;align-items:center;gap:.5rem}
.task-list{padding:1.5rem}
.task-item{border-bottom:1px solid #F3F4F6;padding:1rem 0}
.task-item:last-child{border:none}
.task-row{display:grid;grid-template-columns:auto 1fr auto auto;gap:1rem;align-items:start}
.task-checkbox{width:24px;height:24px;cursor:pointer;margin-top:.25rem}
.task-content{flex:1}
.task-name{font-size:.95rem;color:var(--cb-midnight);margin-bottom:.25rem}
.task-name.completed{text-decoration:line-through;color:var(--cb-gray)}
.task-meta{font-size:.85rem;color:var(--cb-gray);display:flex;gap:1rem;flex-wrap:wrap}
.task-assignee{display:inline-flex;align-items:center;gap:.25rem}
.task-completed-info{color:#059669;font-weight:500}
.btn-complete{background:#10B981;color:white;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-size:.85rem;transition:background .2s}
.btn-complete:hover{background:#059669}
.btn-uncomplete{background:#6B7280;color:white;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-size:.85rem;transition:background .2s}
.btn-uncomplete:hover{background:#4B5563}
.task-notes{margin-top:.5rem;padding:.75rem;background:var(--bg);border-radius:6px;font-size:.9rem;color:var(--cb-midnight)}
.complete-form{margin-top:.75rem;padding:.75rem;background:#F0FDF4;border-radius:6px;border:1px solid #10B981}
.complete-form textarea{width:100%;padding:.5rem;border:1px solid #D1D5DB;border-radius:6px;font-size:.9rem;margin-bottom:.5rem;resize:vertical}
.complete-form-actions{display:flex;gap:.5rem}
.success-message{background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46}
</style>

<div class="page-header">
<div class="header-top">
<div>
<h1 class="page-title">📤 Offboarding in Corso</h1>
<div class="agency-info"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
</div>
<div style="display:flex;gap:1rem;align-items:center">
<?php if(in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])): ?>
<form method="POST" style="margin:0" onsubmit="return confirm('Sei sicuro di voler chiudere questo offboarding? L\'agenzia passerà a status Closed.')">
<input type="hidden" name="action" value="close_offboarding">
<button type="submit" class="btn-close" <?= $progress < 100 ? 'disabled title="Completa tutti i task prima di chiudere"' : '' ?>>✓ Chiudi Offboarding</button>
</form>
<?php endif; ?>
<a href="agenzia_detail.php?code=<?= urlencode($agency['code']) ?>" class="back-btn">← Torna</a>
</div>
</div>
<div class="progress-section">
<div class="progress-bar-container">
<div class="progress-bar" style="width:<?= $progress ?>%"></div>
</div>
<div class="progress-text"><?= $completed_tasks ?>/<?= $total_tasks ?> (<?= $progress ?>%)</div>
</div>
</div>

<?php if(isset($_GET['success']) && $_GET['success'] === 'task_completed'): ?>
<div class="success-message">✓ Task completato con successo!</div>
<?php endif; ?>

<?php foreach ($phases as $phase_num => $phase): ?>
<?php if (!empty($phase['tasks'])): ?>
<div class="phase-card">
<div class="phase-header">
<?php 
$icons = [1 => '📋', 2 => '🔧', 3 => '🔌', 4 => '🎨'];
echo $icons[$phase_num] . ' ' . $phase['name'];
?>
</div>
<div class="task-list">
<?php foreach ($phase['tasks'] as $task): ?>
<div class="task-item">
<div class="task-row">
<input type="checkbox" class="task-checkbox" <?= $task['is_completed'] ? 'checked disabled' : 'disabled' ?>>
<div class="task-content">
<div class="task-name <?= $task['is_completed'] ? 'completed' : '' ?>">
<?= htmlspecialchars($task['task_name']) ?>
</div>
<div class="task-meta">
<span class="task-assignee">👤 <?= htmlspecialchars($task['assignee'] ?: '-') ?></span>
<?php if($task['is_completed']): ?>
<span class="task-completed-info">
✓ Completato <?= $task['completed_at'] ? date('d/m/Y H:i', strtotime($task['completed_at'])) : '' ?>
</span>
<?php endif; ?>
</div>
<?php if($task['is_completed'] && $task['completion_notes']): ?>
<div class="task-notes">📝 <?= nl2br(htmlspecialchars($task['completion_notes'])) ?></div>
<?php endif; ?>
<?php if(!$task['is_completed']): ?>
<div class="complete-form" id="form-<?= $task['id'] ?>" style="display:none">
<form method="POST">
<input type="hidden" name="action" value="complete_task">
<input type="hidden" name="task_id" value="<?= $task['id'] ?>">
<textarea name="notes" placeholder="Note (opzionale)..." rows="2"></textarea>
<div class="complete-form-actions">
<button type="submit" class="btn-complete">✓ Conferma Completamento</button>
<button type="button" class="btn-uncomplete" onclick="document.getElementById('form-<?= $task['id'] ?>').style.display='none'">Annulla</button>
</div>
</form>
</div>
<?php endif; ?>
</div>
<div></div>
<div>
<?php if($task['is_completed']): ?>
<?php if(in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])): ?>
<form method="POST" style="margin:0">
<input type="hidden" name="action" value="uncomplete_task">
<input type="hidden" name="task_id" value="<?= $task['id'] ?>">
<button type="submit" class="btn-uncomplete">↻ Riapri</button>
</form>
<?php endif; ?>
<?php else: ?>
<button class="btn-complete" onclick="document.getElementById('form-<?= $task['id'] ?>').style.display='block';this.style.display='none'">✓ Completa</button>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php require_once 'footer.php'; ?>

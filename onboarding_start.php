<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

// Solo admin e editor possono avviare onboarding
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Avvia Onboarding - CRM Coldwell Banker";
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

if (!$agency || $agency['status'] !== 'Opening') {
    header('Location: agenzie.php');
    exit;
}

// Verifica se onboarding già attivo
$stmt = $pdo->prepare("SELECT id FROM onboardings WHERE agency_id = :agency_id AND status = 'active'");
$stmt->execute(['agency_id' => $agency_id]);
if ($stmt->fetch()) {
    header("Location: onboarding_detail.php?agency_id=$agency_id");
    exit;
}

// Gestione POST - avvia onboarding
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Crea onboarding
    $stmt = $pdo->prepare("INSERT INTO onboardings (agency_id, started_by) VALUES (:agency_id, :started_by)");
    $stmt->execute([
        'agency_id' => $agency_id,
        'started_by' => $_SESSION['crm_user']['id'] ?? 0
    ]);
    $onboarding_id = $pdo->lastInsertId();
    
    // Copia task dal template con responsabili modificati
    $stmt = $pdo->prepare("SELECT * FROM onboarding_template WHERE is_active = 1 ORDER BY phase, task_order");
    $stmt->execute();
    $template_tasks = $stmt->fetchAll();
    
    $insert_task = $pdo->prepare("INSERT INTO onboarding_tasks (onboarding_id, phase, task_name, task_order, assignee) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($template_tasks as $task) {
        $assignee = $_POST['assignee_' . $task['id']] ?? $task['default_assignee'];
        $insert_task->execute([
            $onboarding_id,
            $task['phase'],
            $task['task_name'],
            $task['task_order'],
            $assignee
        ]);
    }
    
    // Aggiorna status agenzia
    $pdo->prepare("UPDATE agencies SET status = 'In Onboarding' WHERE id = ?")->execute([$agency_id]);
    
    // Log
    logAudit($pdo, $_SESSION['crm_user']['id'] ?? 0, $_SESSION['crm_user']['email'], 'agencies', $agency_id, 'UPDATE', [
        'status' => ['old' => 'Opening', 'new' => 'In Onboarding']
    ]);
    
    header("Location: onboarding_detail.php?agency_id=$agency_id");
    exit;
}

// Carica template task
$stmt = $pdo->query("SELECT * FROM onboarding_template WHERE is_active = 1 ORDER BY phase, task_order");
$template_tasks = $stmt->fetchAll();

// Raggruppa per fase
$phases = [
    1 => ['name' => 'Fase 1: Fino alla firma', 'tasks' => []],
    2 => ['name' => 'Fase 2: Pre apertura', 'tasks' => []],
    3 => ['name' => 'Fase 3: Apertura', 'tasks' => []]
];

foreach ($template_tasks as $task) {
    $phases[$task['phase']]['tasks'][] = $task;
}

// Lista utenti per dropdown
$users = ['Sara', 'Raf', 'Claudia', 'Pamela', 'Catia', 'Tutte'];

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agency-info{color:var(--cb-gray);font-size:.95rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.form-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid #10B981;padding:2rem;margin-bottom:2rem}
.phase-section{margin-bottom:2.5rem;padding-bottom:2.5rem;border-bottom:2px solid #F3F4F6}
.phase-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.phase-header{font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem}
.task-list{display:flex;flex-direction:column;gap:1rem}
.task-item{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center;padding:1rem;background:var(--bg);border-radius:8px}
.task-name{font-size:.95rem;color:var(--cb-midnight)}
.task-assignee{display:flex;flex-direction:column;gap:.25rem}
.task-assignee label{font-size:.75rem;color:var(--cb-gray);font-weight:600;text-transform:uppercase}
.task-assignee select{padding:.5rem;border:1px solid #E5E7EB;border-radius:6px;font-size:.9rem;min-width:150px}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-start{background:#10B981;color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-start:hover{background:#059669}
.info-box{background:#DBEAFE;border-left:4px solid #3B82F6;padding:1rem;border-radius:8px;margin-bottom:2rem}
.info-box p{margin:0;color:#1E40AF;font-size:.95rem}
</style>

<div class="page-header">
<div>
<h1 class="page-title">🚀 Avvia Onboarding</h1>
<div class="agency-info"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
</div>
<a href="agenzia_detail.php?code=<?= urlencode($agency['code']) ?>" class="back-btn">← Torna</a>
</div>

<div class="info-box">
<p><strong>ℹ️ Conferma responsabili:</strong> I task sono preassegnati secondo il template. Puoi modificare i responsabili prima di avviare l'onboarding.</p>
</div>

<form method="POST">
<div class="form-card">

<?php foreach ($phases as $phase_num => $phase): ?>
<?php if (!empty($phase['tasks'])): ?>
<div class="phase-section">
<div class="phase-header">
<?php 
$icons = [1 => '📋', 2 => '⚙️', 3 => '🎉'];
echo $icons[$phase_num] . ' ' . $phase['name'];
?>
</div>
<div class="task-list">
<?php foreach ($phase['tasks'] as $task): ?>
<div class="task-item">
<div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
<div class="task-assignee">
<label>Responsabile</label>
<select name="assignee_<?= $task['id'] ?>">
<?php foreach ($users as $user): ?>
<option value="<?= $user ?>" <?= $task['default_assignee'] === $user ? 'selected' : '' ?>><?= $user ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<div class="form-actions">
<a href="agenzia_detail.php?code=<?= urlencode($agency['code']) ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-start">🚀 Avvia Onboarding</button>
</div>

</div>
</form>

<?php require_once 'footer.php'; ?>

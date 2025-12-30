<?php
require_once 'check_auth.php';
require_once 'config/database.php';

// Solo admin
if ($_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = "Template Offboarding - CRM Coldwell Banker";
$pdo = getDB();

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] === 'add_task') {
            $stmt = $pdo->prepare("INSERT INTO offboarding_template (phase, task_name, task_order, default_assignee) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['phase'],
                $_POST['task_name'],
                $_POST['task_order'],
                $_POST['default_assignee']
            ]);
        }
        
        if ($_POST['action'] === 'update_task') {
            $stmt = $pdo->prepare("UPDATE offboarding_template SET task_name = ?, default_assignee = ?, task_order = ? WHERE id = ?");
            $stmt->execute([
                $_POST['task_name'],
                $_POST['default_assignee'],
                $_POST['task_order'],
                $_POST['task_id']
            ]);
        }
        
        if ($_POST['action'] === 'delete_task') {
            $pdo->prepare("UPDATE offboarding_template SET is_active = 0 WHERE id = ?")->execute([$_POST['task_id']]);
        }
        
        if ($_POST['action'] === 'restore_task') {
            $pdo->prepare("UPDATE offboarding_template SET is_active = 1 WHERE id = ?")->execute([$_POST['task_id']]);
        }
        
        header('Location: offboarding_template.php?success=1');
        exit;
    }
}

// Carica task
$stmt = $pdo->query("SELECT * FROM offboarding_template ORDER BY phase, task_order");
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

// Lista utenti
$users = ['Sara', 'Raf', 'Claudia', 'Pamela', 'Catia', 'Tutte'];

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center}
.page-title{font-size:1.75rem;font-weight:600}
.btn-add-task{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-add-task:hover{background:var(--cb-blue)}
.phase-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:2rem;overflow:hidden}
.phase-header{background:#EF4444;color:white;padding:1rem 1.5rem;font-size:1.1rem;font-weight:600}
.task-list{padding:1.5rem}
.task-item{border-bottom:1px solid #F3F4F6;padding:1rem 0;display:grid;grid-template-columns:50px 1fr 150px 80px 120px;gap:1rem;align-items:center}
.task-item:last-child{border:none}
.task-item.inactive{opacity:.4}
.task-order{font-weight:600;color:var(--cb-gray)}
.task-name{font-size:.95rem;color:var(--cb-midnight)}
.task-assignee{font-size:.9rem;color:var(--cb-gray)}
.task-actions{display:flex;gap:.5rem}
.btn-edit,.btn-delete,.btn-restore{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem .75rem;border-radius:6px;cursor:pointer;font-size:.85rem;transition:all .2s}
.btn-edit:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.btn-delete:hover{border-color:#EF4444;color:#EF4444}
.btn-restore:hover{border-color:#10B981;color:#10B981}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:12px;padding:2rem;max-width:500px;width:90%}
.modal-header{font-size:1.25rem;font-weight:600;margin-bottom:1.5rem}
.form-field{margin-bottom:1rem}
.form-field label{display:block;font-size:.875rem;font-weight:600;color:var(--cb-gray);margin-bottom:.5rem}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-actions{display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.success-message{background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46}
</style>

<div class="page-header">
<h1 class="page-title">⚙️ Gestione Template Offboarding</h1>
<button class="btn-add-task" onclick="openAddModal()">➕ Nuovo Task</button>
</div>

<?php if(isset($_GET['success'])): ?>
<div class="success-message">✓ Modifiche salvate con successo!</div>
<?php endif; ?>

<?php foreach ($phases as $phase_num => $phase): ?>
<div class="phase-card">
<div class="phase-header"><?= $phase['name'] ?></div>
<div class="task-list">
<?php if(empty($phase['tasks'])): ?>
<p style="color:var(--cb-gray);text-align:center;padding:2rem">Nessun task in questa fase</p>
<?php else: ?>
<?php foreach ($phase['tasks'] as $task): ?>
<div class="task-item <?= $task['is_active'] ? '' : 'inactive' ?>">
<div class="task-order">#<?= $task['task_order'] ?></div>
<div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
<div class="task-assignee">👤 <?= htmlspecialchars($task['default_assignee'] ?: '-') ?></div>
<div><?= $task['is_active'] ? '<span style="color:#10B981;font-size:.85rem">✓ Attivo</span>' : '<span style="color:#EF4444;font-size:.85rem">✗ Disattivo</span>' ?></div>
<div class="task-actions">
<button class="btn-edit" onclick='openEditModal(<?= json_encode($task) ?>)'>✏️</button>
<?php if($task['is_active']): ?>
<form method="POST" style="margin:0" onsubmit="return confirm('Disattivare questo task?')">
<input type="hidden" name="action" value="delete_task">
<input type="hidden" name="task_id" value="<?= $task['id'] ?>">
<button type="submit" class="btn-delete">🗑️</button>
</form>
<?php else: ?>
<form method="POST" style="margin:0">
<input type="hidden" name="action" value="restore_task">
<input type="hidden" name="task_id" value="<?= $task['id'] ?>">
<button type="submit" class="btn-restore">↻</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<!-- Modal Aggiungi Task -->
<div class="modal" id="addModal">
<div class="modal-content">
<div class="modal-header">➕ Nuovo Task</div>
<form method="POST">
<input type="hidden" name="action" value="add_task">
<div class="form-field">
<label>Fase</label>
<select name="phase" required>
<option value="1">Fase 1: Contratti & Amministrazione</option>
<option value="2">Fase 2: Strumenti Interni</option>
<option value="3">Fase 3: Strumenti Esterni</option>
<option value="4">Fase 4: Debranding</option>
</select>
</div>
<div class="form-field">
<label>Nome Task</label>
<input type="text" name="task_name" required>
</div>
<div class="form-field">
<label>Ordine</label>
<input type="number" name="task_order" value="1" min="1" required>
</div>
<div class="form-field">
<label>Responsabile Default</label>
<select name="default_assignee">
<option value="">Nessuno</option>
<?php foreach($users as $user): ?>
<option value="<?= $user ?>"><?= $user ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-actions">
<button type="button" class="btn-cancel" onclick="closeModal('addModal')">Annulla</button>
<button type="submit" class="btn-save">Salva</button>
</div>
</form>
</div>
</div>

<!-- Modal Modifica Task -->
<div class="modal" id="editModal">
<div class="modal-content">
<div class="modal-header">✏️ Modifica Task</div>
<form method="POST">
<input type="hidden" name="action" value="update_task">
<input type="hidden" name="task_id" id="edit_task_id">
<div class="form-field">
<label>Nome Task</label>
<input type="text" name="task_name" id="edit_task_name" required>
</div>
<div class="form-field">
<label>Ordine</label>
<input type="number" name="task_order" id="edit_task_order" min="1" required>
</div>
<div class="form-field">
<label>Responsabile Default</label>
<select name="default_assignee" id="edit_default_assignee">
<option value="">Nessuno</option>
<?php foreach($users as $user): ?>
<option value="<?= $user ?>"><?= $user ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-actions">
<button type="button" class="btn-cancel" onclick="closeModal('editModal')">Annulla</button>
<button type="submit" class="btn-save">Salva</button>
</div>
</form>
</div>
</div>

<script>
function openAddModal(){
document.getElementById("addModal").classList.add("active");
}

function openEditModal(task){
document.getElementById("edit_task_id").value = task.id;
document.getElementById("edit_task_name").value = task.task_name;
document.getElementById("edit_task_order").value = task.task_order;
document.getElementById("edit_default_assignee").value = task.default_assignee || "";
document.getElementById("editModal").classList.add("active");
}

function closeModal(modalId){
document.getElementById(modalId).classList.remove("active");
}

document.querySelectorAll(".modal").forEach(modal => {
modal.addEventListener("click", function(e){
if(e.target === this){
closeModal(this.id);
}
});
});
</script>

<?php require_once 'footer.php'; ?>

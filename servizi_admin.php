<?php
require_once 'check_auth.php';
require_once 'config/database.php';

// Solo admin può accedere
if ($_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = "Gestione Servizi Master - CRM Coldwell Banker";
$pdo = getDB();

// Gestione POST - Aggiungi/Modifica/Elimina
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO services_master (service_name, is_cb_suite, default_price, display_order) 
                                   VALUES (:name, :is_cb_suite, :price, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM services_master AS sm))");
            $stmt->execute([
                'name' => $_POST['service_name'],
                'is_cb_suite' => isset($_POST['is_cb_suite']) ? 1 : 0,
                'price' => $_POST['default_price'] ?: 0
            ]);
            $success = "Servizio aggiunto!";
        }
        
        if ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE services_master SET service_name = :name, is_cb_suite = :is_cb_suite, default_price = :price WHERE id = :id");
            $stmt->execute([
                'id' => $_POST['service_id'],
                'name' => $_POST['service_name'],
                'is_cb_suite' => isset($_POST['is_cb_suite']) ? 1 : 0,
                'price' => $_POST['default_price'] ?: 0
            ]);
            $success = "Servizio aggiornato!";
        }
        
        if ($_POST['action'] === 'delete') {
            // Verifica se è usato in contratti
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agency_contract_services WHERE service_id = :id");
            $stmt->execute(['id' => $_POST['service_id']]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = "Impossibile eliminare: servizio utilizzato in $count contratti!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM services_master WHERE id = :id");
                $stmt->execute(['id' => $_POST['service_id']]);
                $success = "Servizio eliminato!";
            }
        }
        
        if ($_POST['action'] === 'reorder') {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $stmt = $pdo->prepare("UPDATE services_master SET display_order = :order WHERE id = :id");
                $stmt->execute(['id' => $id, 'order' => $order]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Carica servizi
$stmt = $pdo->query("SELECT * FROM services_master ORDER BY display_order ASC, service_name ASC");
$services = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-add:hover{background:var(--cb-blue)}
.card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);overflow:hidden}
.services-table{width:100%;border-collapse:collapse}
.services-table thead{background:var(--bg)}
.services-table th{text-align:left;padding:1rem 1.5rem;font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #E5E7EB}
.services-table td{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6}
.services-table tr:last-child td{border-bottom:none}
.services-table tr:hover{background:var(--bg)}
.drag-handle{cursor:move;color:var(--cb-gray);font-size:1.2rem;margin-right:.5rem}
.drag-handle:hover{color:var(--cb-midnight)}
.service-name{font-weight:600;color:var(--cb-midnight)}
.service-price{color:var(--cb-bright-blue);font-weight:600}
.btn-icon{background:transparent;border:none;color:var(--cb-gray);cursor:pointer;padding:.5rem;border-radius:6px;transition:all .2s}
.btn-icon:hover{background:var(--bg);color:var(--cb-bright-blue)}
.btn-delete:hover{color:#EF4444}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:12px;max-width:500px;width:90%}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-body{padding:1.5rem}
.form-field{margin-bottom:1.5rem}
.form-field label{display:block;font-size:.875rem;font-weight:600;color:var(--cb-gray);margin-bottom:.5rem}
.form-field input{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus{outline:none;border-color:var(--cb-bright-blue)}
.modal-actions{padding:1.5rem;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:1rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.alert{padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem}
.alert.success{background:#D1FAE5;color:#065F46;border-left:4px solid #10B981}
.alert.error{background:#FEE2E2;color:#991B1B;border-left:4px solid #EF4444}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
</style>

<?php if(isset($success)): ?>
<div class="alert success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
<h1 class="page-title">⚙️ Gestione Servizi Master</h1>
<button class="btn-add" onclick="openAddModal()">➕ Nuovo Servizio</button>
</div>

<div class="card">
<?php if(empty($services)): ?>
<div class="empty-state">
<div style="font-size:3rem;margin-bottom:1rem">📦</div>
<p>Nessun servizio configurato</p>
</div>
<?php else: ?>
<table class="services-table">
<thead>
<tr>
<th style="width:50px"></th>
<th>Nome Servizio</th>
<th style="width:120px">CB Suite</th>
<th style="width:150px">Prezzo Default</th>
<th style="width:120px">Azioni</th>
</tr>
</thead>
<tbody id="servicesTableBody">
<?php foreach($services as $service): ?>
<tr data-id="<?= $service['id'] ?>">
<td><span class="drag-handle">☰</span></td>
<td class="service-name"><?= htmlspecialchars($service['service_name']) ?></td>
<td style="text-align:center">
<?php if($service['is_cb_suite']): ?>
<span style="background:#D1FAE5;color:#065F46;padding:.25rem .75rem;border-radius:6px;font-size:.85rem;font-weight:600">✓ Suite</span>
<?php else: ?>
<span style="color:var(--cb-gray);font-size:.85rem">-</span>
<?php endif; ?>
</td>
<td class="service-price">€ <?= number_format($service['default_price'], 2, ',', '.') ?></td>
<td>
<button class="btn-icon" onclick="openEditModal(<?= $service['id'] ?>, '<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>', <?= $service['is_cb_suite'] ?>, <?= $service['default_price'] ?>)" title="Modifica">✏️</button>
<button class="btn-icon btn-delete" onclick="deleteService(<?= $service['id'] ?>, '<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>')" title="Elimina">🗑️</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<!-- Modal Aggiungi -->
<div class="modal" id="addModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">➕ Nuovo Servizio</h2>
<button class="modal-close" onclick="closeAddModal()">×</button>
</div>
<form method="POST">
<input type="hidden" name="action" value="add">
<div class="modal-body">
<div class="form-field">
<label>Nome Servizio *</label>
<input type="text" name="service_name" required>
</div>
<div class="form-field">
<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
<input type="checkbox" name="is_cb_suite" style="width:auto">
<span>Parte di CB Suite</span>
</label>
</div>
<div class="form-field">
<label>Prezzo Default (€)</label>
<input type="number" name="default_price" step="0.01" min="0" value="0">
</div>
</div>
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeAddModal()">Annulla</button>
<button type="submit" class="btn-save">💾 Salva</button>
</div>
</form>
</div>
</div>

<!-- Modal Modifica -->
<div class="modal" id="editModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">✏️ Modifica Servizio</h2>
<button class="modal-close" onclick="closeEditModal()">×</button>
</div>
<form method="POST">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="service_id" id="edit_service_id">
<div class="modal-body">
<div class="form-field">
<label>Nome Servizio *</label>
<input type="text" name="service_name" id="edit_service_name" required>
</div>
<div class="form-field">
<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
<input type="checkbox" name="is_cb_suite" id="edit_is_cb_suite" style="width:auto">
<span>Parte di CB Suite</span>
</label>
</div>
<div class="form-field">
<label>Prezzo Default (€)</label>
<input type="number" name="default_price" id="edit_default_price" step="0.01" min="0">
</div>
</div>
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeEditModal()">Annulla</button>
<button type="submit" class="btn-save">💾 Salva</button>
</div>
</form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Modal Aggiungi
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

// Modal Modifica
function openEditModal(id, name, isSuite, price) {
    document.getElementById('edit_service_id').value = id;
    document.getElementById('edit_service_name').value = name;
    document.getElementById('edit_is_cb_suite').checked = isSuite == 1;
    document.getElementById('edit_default_price').value = price;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Elimina
function deleteService(id, name) {
    if (!confirm('Eliminare il servizio "' + name + '"?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="service_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Drag & Drop con SortableJS
const tbody = document.getElementById('servicesTableBody');
if (tbody) {
    new Sortable(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const rows = tbody.querySelectorAll('tr');
            const orders = {};
            rows.forEach((row, index) => {
                orders[row.dataset.id] = index;
            });
            
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reorder&orders=' + JSON.stringify(orders)
            });
        }
    });
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>

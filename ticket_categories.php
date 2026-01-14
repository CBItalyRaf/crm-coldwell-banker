<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/log_functions.php';

// Solo admin può gestire categorie
if ($_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: tickets.php');
    exit;
}

$pageTitle = "Gestione Categorie Ticket - CRM Coldwell Banker";
$pdo = getDB();

// Gestione POST - Aggiungi/Modifica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Aggiungi nuova categoria
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_categories (nome, descrizione, colore, icona, ordine, attivo)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $_POST['nome'],
                    $_POST['descrizione'],
                    $_POST['colore'],
                    $_POST['icona'],
                    $_POST['ordine']
                ]);
                
                $categoryId = $pdo->lastInsertId();
                
                // Log creazione categoria (protetto, non blocca mai)
                safeLogActivity(
                    $pdo,
                    $_SESSION['crm_user']['id'] ?? null,
                    $_SESSION['crm_user']['email'] ?? 'unknown',
                    'INSERT',
                    'ticket_categories',
                    $categoryId
                );
                
                $success = "Categoria aggiunta con successo!";
                
            } elseif ($_POST['action'] === 'edit') {
                // Carica dati vecchi per log
                $stmt = $pdo->prepare("SELECT * FROM ticket_categories WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Modifica categoria esistente
                $stmt = $pdo->prepare("
                    UPDATE ticket_categories 
                    SET nome = ?, descrizione = ?, colore = ?, icona = ?, ordine = ?, attivo = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['nome'],
                    $_POST['descrizione'],
                    $_POST['colore'],
                    $_POST['icona'],
                    $_POST['ordine'],
                    isset($_POST['attivo']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                // Log UPDATE categoria (protetto, non blocca mai)
                $newData = [
                    'nome' => $_POST['nome'],
                    'descrizione' => $_POST['descrizione'],
                    'colore' => $_POST['colore'],
                    'icona' => $_POST['icona'],
                    'ordine' => $_POST['ordine'],
                    'attivo' => isset($_POST['attivo']) ? 1 : 0
                ];
                $changes = getChangedFields($oldData, $newData);
                if (!empty($changes)) {
                    safeLogActivity(
                        $pdo,
                        $_SESSION['crm_user']['id'] ?? null,
                        $_SESSION['crm_user']['email'] ?? 'unknown',
                        'UPDATE',
                        'ticket_categories',
                        $_POST['id'],
                        $changes
                    );
                }
                
                $success = "Categoria aggiornata con successo!";
                
            } elseif ($_POST['action'] === 'delete') {
                // Elimina categoria (solo se non ha ticket)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE categoria_id = ?");
                $stmt->execute([$_POST['id']]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $error = "Impossibile eliminare: ci sono $count ticket con questa categoria!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM ticket_categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    // Log DELETE categoria (protetto, non blocca mai)
                    safeLogActivity(
                        $pdo,
                        $_SESSION['crm_user']['id'] ?? null,
                        $_SESSION['crm_user']['email'] ?? 'unknown',
                        'DELETE',
                        'ticket_categories',
                        $_POST['id']
                    );
                    
                    $success = "Categoria eliminata con successo!";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Carica tutte le categorie
$categories = $pdo->query("SELECT * FROM ticket_categories ORDER BY ordine, nome")->fetchAll();

// Conta ticket per categoria
$ticketCounts = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE categoria_id = ?");
    $stmt->execute([$cat['id']]);
    $ticketCounts[$cat['id']] = $stmt->fetchColumn();
}

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.categories-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;margin-bottom:2rem}
.category-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid;position:relative}
.category-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.category-icon{font-size:2rem}
.category-actions{display:flex;gap:.5rem}
.btn-icon{background:transparent;border:none;cursor:pointer;padding:.5rem;border-radius:6px;transition:background .2s;font-size:1.1rem}
.btn-icon:hover{background:var(--bg)}
.category-name{font-size:1.1rem;font-weight:600;margin-bottom:.5rem}
.category-desc{font-size:.9rem;color:var(--cb-gray);margin-bottom:1rem}
.category-meta{display:flex;gap:1rem;font-size:.85rem;color:var(--cb-gray);flex-wrap:wrap}
.badge-status{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600}
.badge-status.active{background:#D1FAE5;color:#065F46}
.badge-status.inactive{background:#F3F4F6;color:#6B7280}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-body{padding:1.5rem}
.form-field{margin-bottom:1.5rem}
.form-field label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--cb-midnight)}
.form-field input,.form-field textarea,.form-field select{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field textarea{min-height:100px;resize:vertical}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.color-picker-wrapper{display:flex;gap:1rem;align-items:center}
.color-preview{width:50px;height:50px;border-radius:8px;border:2px solid #E5E7EB}
.emoji-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:.5rem;max-height:200px;overflow-y:auto;padding:.5rem;background:var(--bg);border-radius:8px}
.emoji-option{font-size:1.5rem;cursor:pointer;padding:.5rem;text-align:center;border-radius:6px;transition:background .2s}
.emoji-option:hover{background:white}
.emoji-option.selected{background:var(--cb-bright-blue);color:white}
.modal-actions{padding:1.5rem;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:1rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.btn-delete{background:#EF4444;color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.success-msg{background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46}
.error-msg{background:#FEE2E2;border-left:4px solid #EF4444;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#991B1B}
.checkbox-field{display:flex;align-items:center;gap:.75rem;padding:1rem;background:var(--bg);border-radius:8px;cursor:pointer}
.checkbox-field input{width:20px;height:20px;cursor:pointer}
</style>

<?php if(isset($success)): ?>
<div class="success-msg">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
<h1 class="page-title">🏷️ Gestione Categorie Ticket</h1>
<button class="btn-add" onclick="openAddModal()">➕ Nuova Categoria</button>
</div>

<div class="categories-grid">
<?php foreach ($categories as $cat): ?>
<div class="category-card" style="border-left-color:<?= htmlspecialchars($cat['colore']) ?>">
<div class="category-header">
<div class="category-icon"><?= $cat['icona'] ?></div>
<div class="category-actions">
<button class="btn-icon" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)" title="Modifica">✏️</button>
<button class="btn-icon" onclick="confirmDelete(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome']) ?>')" title="Elimina">🗑️</button>
</div>
</div>
<div class="category-name"><?= htmlspecialchars($cat['nome']) ?></div>
<div class="category-desc"><?= htmlspecialchars($cat['descrizione']) ?></div>
<div class="category-meta">
<span>📊 Ordine: <?= $cat['ordine'] ?></span>
<span>🎫 <?= $ticketCounts[$cat['id']] ?> ticket</span>
<span class="badge-status <?= $cat['attivo'] ? 'active' : 'inactive' ?>">
<?= $cat['attivo'] ? 'Attiva' : 'Disattivata' ?>
</span>
</div>
</div>
<?php endforeach; ?>
</div>

<!-- Modal Aggiungi/Modifica -->
<div class="modal" id="categoryModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title" id="modalTitle">Nuova Categoria</h2>
<button class="modal-close" onclick="closeModal()">✕</button>
</div>
<form method="POST" id="categoryForm">
<input type="hidden" name="action" id="formAction" value="add">
<input type="hidden" name="id" id="categoryId">
<div class="modal-body">
<div class="form-field">
<label>Nome <span style="color:#EF4444">*</span></label>
<input type="text" name="nome" id="categoryNome" required maxlength="100" placeholder="Es: Supporto Tecnico">
</div>
<div class="form-field">
<label>Descrizione</label>
<textarea name="descrizione" id="categoryDesc" placeholder="Breve descrizione della categoria..."></textarea>
</div>
<div class="form-grid">
<div class="form-field">
<label>Colore <span style="color:#EF4444">*</span></label>
<div class="color-picker-wrapper">
<input type="color" name="colore" id="categoryColor" value="#1F69FF" required>
<div class="color-preview" id="colorPreview" style="background:#1F69FF"></div>
</div>
</div>
<div class="form-field">
<label>Ordine <span style="color:#EF4444">*</span></label>
<input type="number" name="ordine" id="categoryOrdine" value="10" required min="0" max="999">
</div>
</div>
<div class="form-field">
<label>Icona Emoji <span style="color:#EF4444">*</span></label>
<input type="hidden" name="icona" id="categoryIcona" value="📋" required>
<div class="emoji-grid" id="emojiGrid">
<?php 
$emojis = ['📋','🛠️','💼','📊','📧','🏢','📝','❓','💬','🔧','📱','💻','🎯','📈','🔔','⚙️','🎫','📌','✉️','🗂️'];
foreach ($emojis as $emoji): ?>
<div class="emoji-option" onclick="selectEmoji('<?= $emoji ?>')"><?= $emoji ?></div>
<?php endforeach; ?>
</div>
</div>
<div id="activeField" style="display:none">
<label class="checkbox-field">
<input type="checkbox" name="attivo" id="categoryAttivo" checked>
<div>
<div style="font-weight:600">Categoria Attiva</div>
<div style="font-size:.85rem;color:var(--cb-gray)">Visibile nella creazione ticket</div>
</div>
</label>
</div>
</div>
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeModal()">Annulla</button>
<button type="submit" class="btn-save" id="btnSave">💾 Salva</button>
<button type="button" class="btn-delete" id="btnDelete" style="display:none" onclick="deleteCategory()">🗑️ Elimina</button>
</div>
</form>
</div>
</div>

<!-- Form nascosto per delete -->
<form method="POST" id="deleteForm" style="display:none">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" id="deleteId">
</form>

<script>
const modal = document.getElementById('categoryModal');
const form = document.getElementById('categoryForm');
const colorInput = document.getElementById('categoryColor');
const colorPreview = document.getElementById('colorPreview');

// Color picker preview
colorInput.addEventListener('input', function() {
    colorPreview.style.background = this.value;
});

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Nuova Categoria';
    document.getElementById('formAction').value = 'add';
    document.getElementById('btnSave').textContent = '💾 Aggiungi';
    document.getElementById('btnDelete').style.display = 'none';
    document.getElementById('activeField').style.display = 'none';
    form.reset();
    colorInput.value = '#1F69FF';
    colorPreview.style.background = '#1F69FF';
    selectEmoji('📋');
    modal.classList.add('open');
}

function openEditModal(category) {
    document.getElementById('modalTitle').textContent = 'Modifica Categoria';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryNome').value = category.nome;
    document.getElementById('categoryDesc').value = category.descrizione || '';
    document.getElementById('categoryColor').value = category.colore;
    document.getElementById('categoryOrdine').value = category.ordine;
    document.getElementById('categoryIcona').value = category.icona;
    document.getElementById('categoryAttivo').checked = category.attivo == 1;
    colorPreview.style.background = category.colore;
    selectEmoji(category.icona);
    document.getElementById('btnSave').textContent = '💾 Salva Modifiche';
    document.getElementById('btnDelete').style.display = 'inline-block';
    document.getElementById('activeField').style.display = 'block';
    modal.classList.add('open');
}

function closeModal() {
    modal.classList.remove('open');
}

function selectEmoji(emoji) {
    document.getElementById('categoryIcona').value = emoji;
    document.querySelectorAll('.emoji-option').forEach(el => el.classList.remove('selected'));
    event.target.classList.add('selected');
}

function deleteCategory() {
    if (confirm('Sei sicuro di voler eliminare questa categoria?')) {
        document.getElementById('deleteId').value = document.getElementById('categoryId').value;
        document.getElementById('deleteForm').submit();
    }
}

function confirmDelete(id, name) {
    if (confirm('Sei sicuro di voler eliminare la categoria "' + name + '"?\n\nNon potrai eliminarla se ci sono ticket associati.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Chiudi modal cliccando fuori
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeModal();
    }
});
</script>

<?php require_once 'footer.php'; ?>

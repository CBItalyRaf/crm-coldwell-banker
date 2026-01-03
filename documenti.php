<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Gestione Documenti - CRM Coldwell Banker";
$pdo = getDB();

// Filtri
$typeFilter = $_GET['type'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$agencyFilter = $_GET['agency'] ?? 'all';
$search = $_GET['search'] ?? '';

// Carica categorie
$categoriesStmt = $pdo->query("SELECT * FROM document_categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $categoriesStmt->fetchAll();

// Carica agenzie per filtri e dropdown
$agenciesStmt = $pdo->query("SELECT code, name FROM agencies WHERE status != 'Prospect' ORDER BY name ASC");
$agencies = $agenciesStmt->fetchAll();

// Query documenti
$sql = "SELECT d.*, dc.name as category_name, dc.icon as category_icon,
        (SELECT COUNT(*) FROM document_agencies da WHERE da.document_id = d.id) as agencies_count
        FROM documents d
        JOIN document_categories dc ON d.category_id = dc.id
        WHERE 1=1";

$params = [];

if ($typeFilter !== 'all') {
    $sql .= " AND d.type = :type";
    $params[':type'] = $typeFilter;
}

if ($categoryFilter !== 'all') {
    $sql .= " AND d.category_id = :category";
    $params[':category'] = $categoryFilter;
}

if ($agencyFilter !== 'all') {
    $sql .= " AND (d.type = 'common' OR EXISTS (
        SELECT 1 FROM document_agencies da 
        WHERE da.document_id = d.id AND da.agency_code = :agency
    ))";
    $params[':agency'] = $agencyFilter;
}

if ($search) {
    $sql .= " AND (d.original_filename LIKE :search OR d.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY d.uploaded_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
:root{--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.header-actions{display:flex;gap:1rem}
.btn-primary{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500;text-decoration:none}
.btn-primary:hover{background:var(--cb-blue)}
.btn-success{background:var(--success);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-success:hover{background:#059669}
.btn-secondary{background:white;color:var(--cb-midnight);border:1px solid #E5E7EB;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-secondary:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1rem}
.filter-group{display:flex;flex-direction:column;gap:.5rem}
.filter-label{font-size:.85rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em}
.filter-select{padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;background:white;cursor:pointer}
.filter-select:focus{outline:none;border-color:var(--cb-bright-blue)}
.search-box{position:relative}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.documents-grid{display:grid;gap:1.5rem}
.document-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);transition:all .2s}
.document-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.12);transform:translateY(-2px)}
.document-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.document-info{flex:1}
.document-title{font-size:1.1rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem;display:flex;align-items:center;gap:.5rem}
.document-meta{display:flex;gap:1rem;font-size:.85rem;color:var(--cb-gray);flex-wrap:wrap}
.document-actions{display:flex;gap:.5rem}
.btn-icon{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem;border-radius:6px;cursor:pointer;transition:all .2s;font-size:1rem;width:36px;height:36px;display:flex;align-items:center;justify-content:center}
.btn-icon:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.btn-icon.danger:hover{border-color:var(--danger);color:var(--danger)}
.badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.badge.common{background:#DBEAFE;color:#1E40AF}
.badge.group{background:#FEF3C7;color:#92400E}
.badge.single{background:#E5E7EB;color:#6B7280}
.badge.public{background:#D1FAE5;color:#065F46}
.badge.broker{background:#FED7AA;color:#9A3412}
.agencies-list{margin-top:1rem;padding-top:1rem;border-top:1px solid #F3F4F6}
.agencies-toggle{background:transparent;border:none;color:var(--cb-bright-blue);font-size:.85rem;cursor:pointer;padding:.5rem 0;font-weight:500}
.agencies-toggle:hover{text-decoration:underline}
.agencies-items{display:none;margin-top:.5rem;padding:.75rem;background:var(--bg);border-radius:6px}
.agencies-items.active{display:block}
.agency-item{padding:.5rem 0;font-size:.85rem;color:var(--cb-midnight);border-bottom:1px solid #E5E7EB}
.agency-item:last-child{border-bottom:none}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-close:hover{color:var(--cb-midnight)}
.modal-body{padding:1.5rem}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-size:.9rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem}
.form-input,.form-select,.form-textarea{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-textarea{resize:vertical;min-height:80px}
.radio-group{display:flex;gap:1.5rem;flex-wrap:wrap}
.radio-label{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.95rem}
.radio-label input{cursor:pointer}
.agencies-selector{border:1px solid #E5E7EB;border-radius:8px;padding:1rem;max-height:300px;overflow-y:auto}
.agency-checkbox{display:flex;align-items:center;gap:.75rem;padding:.75rem;border-radius:6px;cursor:pointer;transition:background .2s}
.agency-checkbox:hover{background:var(--bg)}
.agency-checkbox input{cursor:pointer}
.agency-checkbox label{cursor:pointer;flex:1;font-size:.9rem}
.filter-agencies{margin-bottom:1rem}
.filter-agencies input{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:6px}
.select-all-btn{background:var(--bg);border:1px solid #E5E7EB;padding:.5rem 1rem;border-radius:6px;font-size:.85rem;cursor:pointer;margin-bottom:.5rem}
.select-all-btn:hover{background:#E5E7EB}
.file-upload{border:2px dashed #E5E7EB;border-radius:8px;padding:2rem;text-align:center;cursor:pointer;transition:all .2s}
.file-upload:hover{border-color:var(--cb-bright-blue);background:var(--bg)}
.file-upload.dragover{border-color:var(--cb-bright-blue);background:#EFF6FF}
.file-info{margin-top:1rem;padding:.75rem;background:var(--bg);border-radius:6px;font-size:.9rem;display:none}
.file-info.active{display:block}
.modal-footer{padding:1.5rem;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:1rem}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-icon{font-size:4rem;margin-bottom:1rem;opacity:.5}
@media(max-width:768px){
.filters-grid{grid-template-columns:1fr}
.header-actions{width:100%;flex-direction:column}
.btn-primary,.btn-success,.btn-secondary{width:100%;justify-content:center}
.document-header{flex-direction:column;gap:1rem}
.document-actions{width:100%}
}
</style>

<div class="page-header">
<h1 class="page-title">📄 Gestione Documenti</h1>
<div class="header-actions">
<button class="btn-success" onclick="openUploadModal()">➕ Carica Documento</button>
<button class="btn-secondary" onclick="openCategoriesModal()">🏷️ Gestisci Categorie</button>
</div>
</div>

<!-- Filtri -->
<div class="filters-bar">
<form method="GET" class="filters-grid">
<div class="filter-group">
<label class="filter-label">Tipo</label>
<select name="type" class="filter-select" onchange="this.form.submit()">
<option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Tutti</option>
<option value="common" <?= $typeFilter === 'common' ? 'selected' : '' ?>>Comuni</option>
<option value="group" <?= $typeFilter === 'group' ? 'selected' : '' ?>>Gruppi</option>
<option value="single" <?= $typeFilter === 'single' ? 'selected' : '' ?>>Singole</option>
</select>
</div>
<div class="filter-group">
<label class="filter-label">Categoria</label>
<select name="category" class="filter-select" onchange="this.form.submit()">
<option value="all">Tutte</option>
<?php foreach($categories as $cat): ?>
<option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['icon'] . ' ' . $cat['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="filter-group">
<label class="filter-label">Agenzia</label>
<select name="agency" class="filter-select" onchange="this.form.submit()">
<option value="all">Tutte</option>
<?php foreach($agencies as $agency): ?>
<option value="<?= htmlspecialchars($agency['code']) ?>" <?= $agencyFilter === $agency['code'] ? 'selected' : '' ?>>
<?= htmlspecialchars($agency['code'] . ' - ' . $agency['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="filter-group">
<label class="filter-label">Cerca</label>
<div class="search-box">
<input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Nome file o descrizione...">
</div>
</div>
</form>
</div>

<!-- Contatore -->
<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);color:var(--cb-gray);font-size:.95rem">
<strong style="color:var(--cb-midnight)"><?= count($documents) ?></strong> documenti trovati
</div>

<!-- Lista Documenti -->
<?php if (empty($documents)): ?>
<div class="empty-state">
<div class="empty-icon">📄</div>
<h3>Nessun documento trovato</h3>
<p>Carica il primo documento o modifica i filtri</p>
</div>
<?php else: ?>
<div class="documents-grid">
<?php foreach ($documents as $doc): ?>
<div class="document-card">
<div class="document-header">
<div class="document-info">
<div class="document-title">
<?= htmlspecialchars($doc['category_icon']) ?>
<?= htmlspecialchars($doc['original_filename']) ?>
</div>
<div class="document-meta">
<span class="badge <?= $doc['type'] ?>">
<?php
$typeLabels = ['common' => 'Comune', 'group' => 'Gruppo', 'single' => 'Singola'];
echo $typeLabels[$doc['type']];
?>
</span>
<span class="badge <?= $doc['visibility'] ?>">
<?= $doc['visibility'] === 'public' ? 'Pubblico' : 'Solo Broker' ?>
</span>
<span><?= htmlspecialchars($doc['category_name']) ?></span>
<span><?= number_format($doc['file_size'] / 1048576, 2) ?> MB</span>
<span>📅 <?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?></span>
<?php if ($doc['uploaded_by']): ?>
<span>👤 <?= htmlspecialchars($doc['uploaded_by']) ?></span>
<?php endif; ?>
</div>
<?php if ($doc['description']): ?>
<p style="margin-top:.5rem;font-size:.9rem;color:var(--cb-gray)"><?= htmlspecialchars($doc['description']) ?></p>
<?php endif; ?>
</div>
<div class="document-actions">
<a href="<?= htmlspecialchars($doc['filepath']) ?>" download class="btn-icon" title="Scarica">
⬇️
</a>
<?php if ($doc['type'] === 'group'): ?>
<button class="btn-icon" onclick="manageAgencies(<?= $doc['id'] ?>)" title="Gestisci agenzie">
👥
</button>
<?php endif; ?>
<button class="btn-icon danger" onclick="deleteDocument(<?= $doc['id'] ?>)" title="Elimina">
🗑️
</button>
</div>
</div>

<?php if ($doc['type'] !== 'common'): ?>
<div class="agencies-list">
<button class="agencies-toggle" onclick="toggleAgencies(<?= $doc['id'] ?>)">
<?php if ($doc['type'] === 'group'): ?>
📋 Assegnato a <?= $doc['agencies_count'] ?> agenzie
<?php else: ?>
<?php
$agencySql = "SELECT a.code, a.name 
              FROM document_agencies da 
              JOIN agencies a ON da.agency_code = a.code 
              WHERE da.document_id = ?";
$agencyStmt = $pdo->prepare($agencySql);
$agencyStmt->execute([$doc['id']]);
$agency = $agencyStmt->fetch();
if ($agency) {
    echo '🏢 ' . htmlspecialchars($agency['code'] . ' - ' . $agency['name']);
}
?>
<?php endif; ?>
<span id="arrow-<?= $doc['id'] ?>"> ▼</span>
</button>
<div class="agencies-items" id="agencies-<?= $doc['id'] ?>">
<?php
$agenciesSql = "SELECT a.code, a.name 
                FROM document_agencies da 
                JOIN agencies a ON da.agency_code = a.code 
                WHERE da.document_id = ? 
                ORDER BY a.name ASC";
$agenciesStmt = $pdo->prepare($agenciesSql);
$agenciesStmt->execute([$doc['id']]);
$docAgencies = $agenciesStmt->fetchAll();
foreach ($docAgencies as $agency):
?>
<div class="agency-item">
<?= htmlspecialchars($agency['code'] . ' - ' . $agency['name']) ?>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Upload -->
<div class="modal" id="uploadModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">📤 Carica Nuovo Documento</h2>
<button class="modal-close" onclick="closeUploadModal()">✕</button>
</div>
<form method="POST" action="documenti_upload.php" enctype="multipart/form-data" id="uploadForm">
<div class="modal-body">
<!-- Tipo documento -->
<div class="form-group">
<label class="form-label">Tipo Documento *</label>
<div class="radio-group">
<label class="radio-label">
<input type="radio" name="type" value="common" checked onchange="updateAgencySelector()">
📢 Comune (tutte le agenzie)
</label>
<label class="radio-label">
<input type="radio" name="type" value="group" onchange="updateAgencySelector()">
👥 Gruppo (selezione multipla)
</label>
<label class="radio-label">
<input type="radio" name="type" value="single" onchange="updateAgencySelector()">
🏢 Singola agenzia
</label>
</div>
</div>

<!-- Selettore agenzie (nascosto per common) -->
<div class="form-group" id="agencySelector" style="display:none">
<label class="form-label" id="agencySelectorLabel">Seleziona Agenzia *</label>
<!-- Dropdown singola -->
<select name="single_agency" id="singleAgencySelect" class="form-select" style="display:none">
<option value="">-- Seleziona agenzia --</option>
<?php foreach($agencies as $agency): ?>
<option value="<?= htmlspecialchars($agency['code']) ?>">
<?= htmlspecialchars($agency['code'] . ' - ' . $agency['name']) ?>
</option>
<?php endforeach; ?>
</select>

<!-- Multipla con filtro -->
<div id="multipleAgencySelect" style="display:none">
<div class="filter-agencies">
<input type="text" id="filterAgencies" placeholder="🔍 Filtra per nome agenzia..." onkeyup="filterAgenciesList()">
</div>
<button type="button" class="select-all-btn" onclick="selectAllFiltered()">
Seleziona tutte (filtrate)
</button>
<div class="agencies-selector" id="agenciesList">
<?php foreach($agencies as $agency): ?>
<div class="agency-checkbox" data-name="<?= strtolower($agency['name']) ?>">
<input type="checkbox" name="group_agencies[]" value="<?= htmlspecialchars($agency['code']) ?>" id="agency-<?= htmlspecialchars($agency['code']) ?>">
<label for="agency-<?= htmlspecialchars($agency['code']) ?>">
<?= htmlspecialchars($agency['code'] . ' - ' . $agency['name']) ?>
</label>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<!-- Categoria -->
<div class="form-group">
<label class="form-label">Categoria *</label>
<select name="category_id" class="form-select" required>
<option value="">-- Seleziona categoria --</option>
<?php foreach($categories as $cat): ?>
<option value="<?= $cat['id'] ?>">
<?= htmlspecialchars($cat['icon'] . ' ' . $cat['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<!-- Visibilità -->
<div class="form-group">
<label class="form-label">Visibilità *</label>
<div class="radio-group">
<label class="radio-label">
<input type="radio" name="visibility" value="public" checked>
👁️ Pubblico (tutti)
</label>
<label class="radio-label">
<input type="radio" name="visibility" value="broker_only">
🔒 Solo Broker
</label>
</div>
</div>

<!-- File -->
<div class="form-group">
<label class="form-label">File *</label>
<div class="file-upload" id="fileUpload">
<p>📁 Trascina file qui o click per selezionare</p>
<input type="file" name="file" id="fileInput" style="display:none" required onchange="showFileInfo()">
</div>
<div class="file-info" id="fileInfo"></div>
</div>

<!-- Descrizione -->
<div class="form-group">
<label class="form-label">Descrizione (opzionale)</label>
<textarea name="description" class="form-textarea" placeholder="Aggiungi una descrizione..."></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn-secondary" onclick="closeUploadModal()">Annulla</button>
<button type="submit" class="btn-success">📤 Carica Documento</button>
</div>
</form>
</div>
</div>

<script>
// Modal upload
function openUploadModal() {
    document.getElementById('uploadModal').classList.add('open');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('open');
    document.getElementById('uploadForm').reset();
    document.getElementById('fileInfo').classList.remove('active');
    updateAgencySelector();
}

// Gestione tipo documento
function updateAgencySelector() {
    const type = document.querySelector('input[name="type"]:checked').value;
    const selector = document.getElementById('agencySelector');
    const label = document.getElementById('agencySelectorLabel');
    const singleSelect = document.getElementById('singleAgencySelect');
    const multipleSelect = document.getElementById('multipleAgencySelect');
    
    if (type === 'common') {
        selector.style.display = 'none';
        singleSelect.removeAttribute('required');
    } else if (type === 'single') {
        selector.style.display = 'block';
        label.textContent = 'Seleziona Agenzia *';
        singleSelect.style.display = 'block';
        multipleSelect.style.display = 'none';
        singleSelect.setAttribute('required', 'required');
    } else { // group
        selector.style.display = 'block';
        label.textContent = 'Seleziona Agenzie (multipla) *';
        singleSelect.style.display = 'none';
        multipleSelect.style.display = 'block';
        singleSelect.removeAttribute('required');
    }
}

// File upload drag&drop
const fileUpload = document.getElementById('fileUpload');
const fileInput = document.getElementById('fileInput');

fileUpload.addEventListener('click', () => fileInput.click());
fileUpload.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUpload.classList.add('dragover');
});
fileUpload.addEventListener('dragleave', () => {
    fileUpload.classList.remove('dragover');
});
fileUpload.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUpload.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showFileInfo();
    }
});

function showFileInfo() {
    const file = fileInput.files[0];
    if (file) {
        const size = (file.size / 1048576).toFixed(2);
        document.getElementById('fileInfo').innerHTML = `
            <strong>📄 ${file.name}</strong><br>
            Dimensione: ${size} MB
        `;
        document.getElementById('fileInfo').classList.add('active');
    }
}

// Filtro agenzie
function filterAgenciesList() {
    const filter = document.getElementById('filterAgencies').value.toLowerCase();
    const checkboxes = document.querySelectorAll('#agenciesList .agency-checkbox');
    
    checkboxes.forEach(checkbox => {
        const name = checkbox.getAttribute('data-name');
        if (name.includes(filter)) {
            checkbox.style.display = 'flex';
        } else {
            checkbox.style.display = 'none';
        }
    });
}

// Seleziona tutte filtrate
function selectAllFiltered() {
    const checkboxes = document.querySelectorAll('#agenciesList .agency-checkbox');
    checkboxes.forEach(checkbox => {
        if (checkbox.style.display !== 'none') {
            checkbox.querySelector('input').checked = true;
        }
    });
}

// Toggle lista agenzie
function toggleAgencies(id) {
    const list = document.getElementById('agencies-' + id);
    const arrow = document.getElementById('arrow-' + id);
    list.classList.toggle('active');
    arrow.textContent = list.classList.contains('active') ? ' ▲' : ' ▼';
}

// Gestisci agenzie (gruppo)
function manageAgencies(id) {
    window.location.href = 'documenti_manage_agencies.php?id=' + id;
}

// Elimina documento
function deleteDocument(id) {
    if (confirm('Sei sicuro di voler eliminare questo documento?\n\nQuesta azione è irreversibile.')) {
        window.location.href = 'documenti_delete.php?id=' + id;
    }
}

// Chiudi modal click fuori
window.addEventListener('click', (e) => {
    const modal = document.getElementById('uploadModal');
    if (e.target === modal) {
        closeUploadModal();
    }
});
</script>

<?php require_once 'footer.php'; ?>

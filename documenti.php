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
$folderFilter = $_GET['folder'] ?? '';
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

if ($folderFilter !== '') {
    // Dentro una cartella: mostra SOLO file in questa cartella specifica
    $sql .= " AND d.folder_path = :folder";
    $params[':folder'] = $folderFilter;
} else {
    // ROOT: mostra SOLO file senza cartella (folder_path NULL o vuoto)
    $sql .= " AND (d.folder_path IS NULL OR d.folder_path = '')";
}

if ($search) {
    $sql .= " AND (d.original_filename LIKE :search OR d.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY d.uploaded_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Recupera sottocartelle dalla tabella folders con statistiche
$folderSql = "SELECT f.folder_path,
              COUNT(d.id) as file_count,
              COALESCE(SUM(d.file_size), 0) as total_size,
              MAX(d.uploaded_at) as last_modified
              FROM folders f
              LEFT JOIN documents d ON d.folder_path = f.folder_path
              WHERE 1=1";
if ($folderFilter) {
    // Se sono dentro una cartella, mostro le sottocartelle dirette
    $folderSql .= " AND f.folder_path LIKE :folder_pattern AND f.folder_path != :current_folder";
    $folderParams = [
        ':folder_pattern' => $folderFilter . '%',
        ':current_folder' => $folderFilter
    ];
} else {
    // ROOT: mostro solo cartelle di primo livello (senza /)
    $folderSql .= " AND f.folder_path NOT LIKE '%/%/%'";
    $folderParams = [];
}
$folderSql .= " GROUP BY f.folder_path ORDER BY f.folder_path ASC";

$folderStmt = $pdo->prepare($folderSql);
$folderStmt->execute($folderParams);
$allFoldersData = $folderStmt->fetchAll();

// Filtra per mostrare solo sottocartelle dirette
$subfolders = [];
foreach ($allFoldersData as $folderData) {
    $folder = $folderData['folder_path'];
    if (empty($folder)) continue;
    
    if ($folderFilter) {
        // Dentro una cartella - mostra sottocartelle
        if (substr($folder, 0, strlen($folderFilter)) !== $folderFilter) continue;
        $relativePath = substr($folder, strlen($folderFilter));
        $parts = explode('/', trim($relativePath, '/'));
        if (!empty($parts[0]) && !isset($subfolders[$parts[0]])) {
            $subfolders[$parts[0]] = [
                'path' => $folderFilter . $parts[0] . '/',
                'file_count' => $folderData['file_count'],
                'total_size' => $folderData['total_size'],
                'last_modified' => $folderData['last_modified']
            ];
        }
    } else {
        // ROOT - mostra solo primo livello
        $parts = explode('/', trim($folder, '/'));
        if (!empty($parts[0]) && !isset($subfolders[$parts[0]])) {
            $subfolders[$parts[0]] = [
                'path' => $parts[0] . '/',
                'file_count' => $folderData['file_count'],
                'total_size' => $folderData['total_size'],
                'last_modified' => $folderData['last_modified']
            ];
        }
    }
}

require_once 'header.php';
?>

<?php if ($folderFilter): ?>
<div style="background:white;padding:.85rem 1.25rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;align-items:center;gap:.5rem;font-size:.85rem">
<a href="?<?= http_build_query(array_merge($_GET, ['folder' => ''])) ?>" style="color:var(--cb-bright-blue);text-decoration:none">📁 Root</a>
<span style="color:var(--cb-gray)">/</span>
<?php
$parts = explode('/', trim($folderFilter, '/'));
$currentPath = '';
foreach ($parts as $i => $part):
    $currentPath .= $part . '/';
    if ($i < count($parts) - 1):
?>
<a href="?<?= http_build_query(array_merge($_GET, ['folder' => $currentPath])) ?>" style="color:var(--cb-bright-blue);text-decoration:none"><?= htmlspecialchars($part) ?></a>
<span style="color:var(--cb-gray)">/</span>
<?php else: ?>
<strong style="color:var(--cb-midnight)"><?= htmlspecialchars($part) ?></strong>
<?php endif; endforeach; ?>
</div>
<?php endif; ?>

<style>
:root{--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.5rem;font-weight:600}
.header-actions{display:flex;gap:1rem}
.btn-primary{background:var(--cb-bright-blue);color:white;border:none;padding:.65rem 1.25rem;border-radius:8px;font-size:.85rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500;text-decoration:none}
.btn-primary:hover{background:var(--cb-blue)}
.btn-success{background:var(--success);color:white;border:none;padding:.65rem 1.25rem;border-radius:8px;font-size:.85rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-success:hover{background:#059669}
.btn-secondary{background:white;color:var(--cb-midnight);border:1px solid #E5E7EB;padding:.65rem 1.25rem;border-radius:8px;font-size:.85rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-secondary:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1rem}
.filter-group{display:flex;flex-direction:column;gap:.5rem}
.filter-label{font-size:.8rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em}
.filter-select{padding:.65rem .85rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.85rem;background:white;cursor:pointer}
.filter-select:focus{outline:none;border-color:var(--cb-bright-blue)}
.search-box{position:relative}
.search-box input{width:100%;padding:.65rem .85rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.85rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}

/* Finder View */
.finder-container{background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.finder-table{width:100%;border-collapse:collapse}
.finder-table thead{background:#F9FAFB;border-bottom:2px solid #E5E7EB}
.finder-table th{text-align:left;padding:12px 16px;font-size:.75rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em}
.finder-row{border-bottom:1px solid #F3F4F6;transition:background .15s;cursor:pointer}
.finder-row:hover{background:#F9FAFB}
.finder-row:last-child{border-bottom:none}
.finder-row td{padding:10px 16px;font-size:.85rem}
.finder-name{display:flex;align-items:center;gap:.75rem}
.finder-icon{font-size:1.25rem;flex-shrink:0}
.finder-label{color:var(--cb-midnight);font-weight:500;flex:1}
.finder-meta{color:var(--cb-gray);font-size:.8rem}
.finder-actions{text-align:right;display:flex;justify-content:flex-end;gap:.5rem;align-items:center}
.finder-arrow{color:var(--cb-gray);font-size:1.25rem;opacity:.5}
.folder-row:hover .finder-arrow{opacity:1;color:var(--cb-bright-blue)}
.action-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.35rem .5rem;border-radius:6px;cursor:pointer;transition:all .2s;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;min-width:32px}
.action-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue);background:#EFF6FF}
.action-delete:hover{border-color:var(--danger);color:var(--danger);background:#FEE2E2}
.type-badge{padding:.2rem .6rem;border-radius:10px;font-size:.65rem;font-weight:600;text-transform:uppercase;margin-left:.5rem}
.badge-common{background:#DBEAFE;color:#1E40AF}
.badge-group{background:#FEF3C7;color:#92400E}
.badge-single{background:#E5E7EB;color:#6B7280}

.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.1rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-close:hover{color:var(--cb-midnight)}
.modal-body{padding:1.5rem}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-size:.85rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem}
.form-input,.form-select,.form-textarea{width:100%;padding:.65rem .85rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.85rem}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-textarea{resize:vertical;min-height:80px}
.radio-group{display:flex;gap:1.5rem;flex-wrap:wrap}
.radio-label{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.95rem}
.radio-label input{cursor:pointer}
.agencies-selector{border:1px solid #E5E7EB;border-radius:8px;padding:1rem;max-height:300px;overflow-y:auto}
.agency-checkbox{display:flex;align-items:center;gap:.75rem;padding:.75rem;border-radius:6px;cursor:pointer;transition:background .2s}
.agency-checkbox:hover{background:var(--bg)}
.agency-checkbox input{cursor:pointer}
.agency-checkbox label{cursor:pointer;flex:1;font-size:.85rem}
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
.finder-table th:nth-child(3),.finder-table td:nth-child(3),
.finder-table th:nth-child(4),.finder-table td:nth-child(4){display:none}
.finder-table th:nth-child(1),.finder-table td:nth-child(1){width:50%}
.finder-table th:nth-child(2),.finder-table td:nth-child(2){width:25%}
.finder-table th:nth-child(5),.finder-table td:nth-child(5){width:25%}
.finder-name{gap:.5rem}
.finder-icon{font-size:1.1rem}
.finder-label{font-size:.8rem}
.type-badge{display:none}
.action-btn{min-width:28px;padding:.25rem .35rem;font-size:.85rem}
}
</style>

<div class="page-header">
<h1 class="page-title">📄 Gestione Documenti</h1>
<div class="header-actions">
<button class="btn-success" onclick="openUploadModal()">➕ Carica Documento/i</button>
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
<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);color:var(--cb-gray);font-size:.85rem">
<?php 
$totalItems = count($documents) + count($subfolders);
if (count($subfolders) > 0) {
    echo '<strong style="color:var(--cb-midnight)">' . count($subfolders) . '</strong> cartelle, ';
}
?>
<strong style="color:var(--cb-midnight)"><?= count($documents) ?></strong> documenti
</div>

<!-- Lista Documenti -->
<?php if (empty($documents) && empty($subfolders)): ?>
<div class="empty-state">
<div class="empty-icon">📄</div>
<h3>Nessun documento trovato</h3>
<p>Carica il primo documento o modifica i filtri</p>
</div>
<?php else: ?>
<div class="finder-container">
<table class="finder-table">
<thead>
<tr>
<th style="width:45%">Nome</th>
<th style="width:12%">Tipo</th>
<th style="width:10%">Dimensione</th>
<th style="width:15%">Modificato</th>
<th style="width:18%;text-align:right">Azioni</th>
</tr>
</thead>
<tbody>

<!-- Cartelle -->
<?php foreach ($subfolders as $folderName => $folderData): ?>
<tr class="finder-row folder-row" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['folder' => $folderData['path']])) ?>'">
<td class="finder-name">
<span class="finder-icon">📁</span>
<span class="finder-label"><?= htmlspecialchars($folderName) ?></span>
</td>
<td class="finder-meta"><?= $folderData['file_count'] ?> file</td>
<td class="finder-meta"><?= $folderData['total_size'] > 0 ? number_format($folderData['total_size'] / 1048576, 1) . ' MB' : '—' ?></td>
<td class="finder-meta"><?= $folderData['last_modified'] ? date('d/m/y H:i', strtotime($folderData['last_modified'])) : '—' ?></td>
<td class="finder-actions" onclick="event.stopPropagation()">
<span class="finder-arrow">›</span>
</td>
</tr>
<?php endforeach; ?>

<!-- File -->
<?php foreach ($documents as $doc): ?>
<tr class="finder-row file-row">
<td class="finder-name">
<span class="finder-icon"><?= htmlspecialchars($doc['category_icon']) ?></span>
<span class="finder-label"><?= htmlspecialchars($doc['original_filename']) ?></span>
<?php if ($doc['type'] === 'common'): ?>
<span class="type-badge badge-common">Comune</span>
<?php elseif ($doc['type'] === 'group'): ?>
<span class="type-badge badge-group">Gruppo</span>
<?php else: ?>
<span class="type-badge badge-single">Singola</span>
<?php endif; ?>
</td>
<td class="finder-meta"><?= htmlspecialchars($doc['category_name']) ?></td>
<td class="finder-meta"><?= number_format($doc['file_size'] / 1048576, 1) ?> MB</td>
<td class="finder-meta"><?= date('d/m/y H:i', strtotime($doc['uploaded_at'])) ?></td>
<td class="finder-actions">
<a href="/<?= htmlspecialchars($doc['filepath']) ?>" download class="action-btn" title="Scarica" onclick="event.stopPropagation()">⬇️</a>
<?php if ($doc['type'] === 'group' || $doc['type'] === 'single'): ?>
<a href="documenti_manage_agencies.php?id=<?= $doc['id'] ?>" class="action-btn" title="Gestisci agenzie" onclick="event.stopPropagation()">👥</a>
<?php endif; ?>
<button onclick="event.stopPropagation();deleteDoc(<?= $doc['id'] ?>,'<?= htmlspecialchars($doc['original_filename']) ?>')" class="action-btn action-delete" title="Elimina">🗑️</button>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
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

<!-- Cartella (opzionale) -->
<div class="form-group">
<label class="form-label">Cartella (opzionale)</label>
<input type="text" name="folder_path" class="form-input" placeholder="Es: Modulistica/Affitti oppure Loghi/Bodini">
<p style="font-size:.85rem;color:var(--cb-gray);margin-top:.5rem">
💡 Lascia vuoto per root, oppure scrivi nome cartella (usa "/" per sottocartelle).<br>
La cartella sarà creata automaticamente come: <strong id="folderTypeHint">Comune</strong>
</p>
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
<label class="form-label">File * (supporta selezione multipla)</label>
<div class="file-upload" id="fileUpload">
<p>📁 Trascina file qui o click per selezionare</p>
<p style="font-size:.85rem;margin-top:.5rem;color:var(--cb-gray)">Puoi selezionare più file insieme (Ctrl+Click o Cmd+Click)</p>
<input type="file" name="files[]" id="fileInput" style="display:none" multiple onchange="showFileInfo()">
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
    document.getElementById('fileInfo').innerHTML = '';
    updateAgencySelector();
}

// Gestione tipo documento
function updateAgencySelector() {
    const type = document.querySelector('input[name="type"]:checked').value;
    const selector = document.getElementById('agencySelector');
    const label = document.getElementById('agencySelectorLabel');
    const singleSelect = document.getElementById('singleAgencySelect');
    const multipleSelect = document.getElementById('multipleAgencySelect');
    const folderHint = document.getElementById('folderTypeHint');
    
    // Aggiorna hint tipo cartella
    if (folderHint) {
        if (type === 'common') {
            folderHint.textContent = 'Comune (visibile a tutte le agenzie)';
        } else if (type === 'group') {
            folderHint.textContent = 'Gruppo (visibile solo alle agenzie selezionate)';
        } else {
            folderHint.textContent = 'Singola agenzia (visibile solo a quell\'agenzia)';
        }
    }
    
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
    const files = fileInput.files;
    
    if (files.length === 0) return;
    
    let totalSize = 0;
    let fileList = '<strong>File selezionati:</strong><br>';
    
    for (let i = 0; i < Math.min(files.length, 10); i++) {
        totalSize += files[i].size;
        fileList += `📄 ${files[i].name}<br>`;
    }
    
    if (files.length > 10) {
        fileList += `... e altri ${files.length - 10} file<br>`;
    }
    
    const size = (totalSize / 1048576).toFixed(2);
    fileList += `<br><strong>Totale: ${files.length} file (${size} MB)</strong>`;
    
    document.getElementById('fileInfo').innerHTML = fileList;
    document.getElementById('fileInfo').classList.add('active');
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

function deleteDoc(id, filename) {
    if (confirm('Eliminare "' + filename + '"?\n\nQuesta azione è irreversibile.')) {
        window.location.href = 'documenti_delete.php?id=' + id;
    }
}

// Chiudi modal click fuori
window.addEventListener('click', (e) => {
    const uploadModal = document.getElementById('uploadModal');
    const categoriesModal = document.getElementById('categoriesModal');
    
    if (e.target === uploadModal) {
        closeUploadModal();
    }
    if (e.target === categoriesModal) {
        closeCategoriesModal();
    }
});

// Modal Categorie
function openCategoriesModal() {
    document.getElementById('categoriesModal').style.display = 'flex';
    loadCategories();
}

function closeCategoriesModal() {
    document.getElementById('categoriesModal').style.display = 'none';
}

function loadCategories() {
    console.log('Loading categories...');
    fetch('api_categories.php?action=list')
        .then(r => {
            console.log('Response:', r);
            return r.json();
        })
        .then(data => {
            console.log('Data:', data);
            if (data.success) {
                renderCategoriesList(data.categories);
            } else {
                console.error('API error:', data.error);
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            document.getElementById('categoriesList').innerHTML = '<p style="color:red">Errore caricamento categorie</p>';
        });
}

function renderCategoriesList(categories) {
    const html = categories.map(cat => {
        const escapedName = cat.name.replace(/'/g, "\\'");
        const escapedIcon = cat.icon.replace(/'/g, "\\'");
        
        return `
        <div class="category-item" style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #e0e0e0">
            <div>
                <span style="font-size:20px">${cat.icon}</span>
                <strong>${cat.name}</strong>
                ${cat.is_active == 0 ? '<span style="color:#999"> (Disattivata)</span>' : ''}
            </div>
            <div>
                <button onclick='editCategory(${cat.id}, "${escapedName}", "${escapedIcon}", ${cat.is_active})' class="btn-sm">✏️ Modifica</button>
                <button onclick="deleteCategory(${cat.id})" class="btn-sm btn-danger">🗑️</button>
            </div>
        </div>
        `;
    }).join('');
    
    document.getElementById('categoriesList').innerHTML = html;
}

function addCategory() {
    const name = prompt('Nome categoria:');
    const icon = prompt('Emoji icona:', '📄');
    
    if (!name) return;
    
    fetch('api_categories.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'add', name, icon})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Categoria aggiunta!');
            loadCategories();
        } else {
            alert('Errore: ' + data.error);
        }
    })
    .catch(err => {
        console.error('Errore:', err);
        alert('Errore di connessione');
    });
}

function editCategory(id, name, icon, isActive) {
    const newName = prompt('Nuovo nome:', name);
    const newIcon = prompt('Nuova icona:', icon);
    
    if (!newName) return;
    
    fetch('api_categories.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'edit', id, name: newName, icon: newIcon})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadCategories();
            location.reload();
        } else {
            alert('Errore: ' + data.error);
        }
    });
}

function deleteCategory(id) {
    if (!confirm('Eliminare questa categoria?\n\nI documenti associati rimarranno senza categoria.')) return;
    
    fetch('api_categories.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadCategories();
            location.reload();
        } else {
            alert('Errore: ' + data.error);
        }
    });
}
</script>

<!-- Modal Categorie -->
<div id="categoriesModal" class="modal">
    <div class="modal-content" style="max-width:600px">
        <span class="close" onclick="closeCategoriesModal()">&times;</span>
        <h2>🏷️ Gestione Categorie</h2>
        
        <button onclick="addCategory()" class="btn-primary" style="margin-bottom:20px">➕ Nuova Categoria</button>
        
        <div id="categoriesList"></div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

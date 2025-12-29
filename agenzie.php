<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Gestione Agenzie - CRM Coldwell Banker";
$pdo = getDB();

$statusFilter = $_GET['status'] ?? 'Active';
$search = $_GET['search'] ?? '';
$searchType = $_GET['search_type'] ?? 'all';

// Valida searchType per evitare SQL injection o valori non previsti
$validSearchTypes = ['all', 'city', 'province', 'people'];
if (!in_array($searchType, $validSearchTypes)) {
    $searchType = 'all';
}

$sql = "SELECT code, name, city, province, status, broker_manager, email, phone 
        FROM agencies 
        WHERE status != 'Prospect'";

if ($statusFilter !== 'all') {
    $sql .= " AND status = :status";
}

if ($search) {
    if ($searchType === 'city') {
        $sql .= " AND city LIKE :search";
    } elseif ($searchType === 'province') {
        $sql .= " AND province LIKE :search";
    } elseif ($searchType === 'people') {
        $sql .= " AND broker_manager LIKE :search";
    } else {
        // all - usa placeholder distinti per evitare problemi con PDO
        $sql .= " AND (name LIKE :search1 OR code LIKE :search2 OR city LIKE :search3 OR province LIKE :search4 OR broker_manager LIKE :search5)";
    }
}

$sql .= " ORDER BY name ASC";

$params = [];

if ($statusFilter !== 'all') {
    $params[':status'] = $statusFilter;
}
if ($search) {
    if ($searchType === 'all') {
        // Placeholder distinti per 'all'
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
        $params[':search4'] = "%$search%";
        $params[':search5'] = "%$search%";
    } else {
        $params[':search'] = "%$search%";
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agencies = $stmt->fetchAll();

// Count totale con status filter applicato (ma senza search)
$countSql = "SELECT COUNT(*) FROM agencies WHERE status != 'Prospect'";
if ($statusFilter !== 'all') {
    $countSql .= " AND status = :status";
}
$countStmt = $pdo->prepare($countSql);
if ($statusFilter !== 'all') {
    $countStmt->bindValue(':status', $statusFilter);
}
$countStmt->execute();
$totalCount = $countStmt->fetchColumn();

require_once 'header.php';
?>

<style>
:root{--success:#10B981;--warning:#F59E0B;--danger:#EF4444}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.header-actions{display:flex;gap:1rem}
.btn-export{background:var(--success);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-export:hover{background:#059669}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-add:hover{background:var(--cb-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}
.search-box{position:relative;flex:1}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.search-results{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #E5E7EB;border-radius:8px;margin-top:.5rem;max-height:400px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,.1);z-index:1000;display:none}
.search-results.active{display:block}
.search-item{padding:1rem;border-bottom:1px solid #F3F4F6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;color:var(--cb-midnight);margin-bottom:.25rem}
.search-item-meta{font-size:.85rem;color:var(--cb-gray)}
.status-filters{display:flex;gap:.5rem;flex-wrap:wrap}
.filter-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;cursor:pointer;transition:all .2s;font-size:.9rem}
.filter-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filter-btn.active{background:var(--cb-bright-blue);color:white;border-color:var(--cb-bright-blue)}
.table-container{background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.agencies-table{width:100%;border-collapse:collapse}
.agencies-table th{text-align:left;padding:1rem 1.5rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #E5E7EB}
.agencies-table th.sortable{cursor:pointer;user-select:none;transition:background .2s}
.agencies-table th.sortable:hover{background:#E5E7EB}
.agencies-table th.sortable .sort-arrow{font-size:.7em;opacity:.3;margin-left:.25rem}
.agencies-table th.sortable.asc .sort-arrow{opacity:1}
.agencies-table th.sortable.asc .sort-arrow::after{content:'↑'}
.agencies-table th.sortable.desc .sort-arrow{opacity:1}
.agencies-table th.sortable.desc .sort-arrow::after{content:'↓'}
.agencies-table td{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6}
.agencies-table tbody tr{cursor:pointer;transition:background .2s}
.agencies-table tbody tr:hover{background:var(--bg)}
.agency-name{font-weight:600;color:var(--cb-midnight)}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.closed{background:#FEE2E2;color:#991B1B}
.status-badge.opening{background:#FEF3C7;color:#92400E}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-close:hover{color:var(--cb-midnight)}
.checkbox-group{padding:1.5rem}
.checkbox-group h3{font-size:1rem;font-weight:600;margin-bottom:1rem}
.checkbox-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.checkbox-label{display:flex;align-items:center;gap:.5rem;cursor:pointer}
.modal-actions{padding:1.5rem;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:1rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.btn-cancel:hover{border-color:var(--cb-gray)}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-state-icon{font-size:4rem;margin-bottom:1rem;opacity:.5}
@media(max-width:768px){
.filters-grid{grid-template-columns:1fr}
.checkbox-grid{grid-template-columns:1fr}
.agencies-table th,.agencies-table td{padding:.75rem .5rem}
.header-actions{width:100%;flex-direction:column}
.btn-export,.btn-add{width:100%;justify-content:center}
}
</style>

<div class="page-header">
<h1 class="page-title">🏢 Gestione Agenzie</h1>
<div class="header-actions">
<button class="btn-export" onclick="openExportModal()">📥 Esporta CSV</button>
<a href="agenzia_add.php" class="btn-add">➕ Nuova Agenzia</a>
</div>
</div>

<div class="filters-bar">
<div class="filters-grid">
<div class="search-box">
<div style="display:flex;gap:.5rem">
<select id="searchType" name="search_type" style="padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;background:white;cursor:pointer;min-width:140px">
<option value="all" <?= ($searchType ?? 'all') === 'all' ? 'selected' : '' ?>>🌐 Tutto</option>
<option value="city" <?= ($searchType ?? '') === 'city' ? 'selected' : '' ?>>🏙️ Solo Città</option>
<option value="province" <?= ($searchType ?? '') === 'province' ? 'selected' : '' ?>>📍 Solo Provincia</option>
<option value="people" <?= ($searchType ?? '') === 'people' ? 'selected' : '' ?>>👤 Solo Broker</option>
</select>
<input type="text" id="agenciesSearch" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cerca..." autocomplete="off" style="flex:1">
</div>
<div class="search-results" id="agenciesSearchResults"></div>
</div>
<div class="status-filters">
<form method="GET" id="statusForm">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType ?? 'all') ?>">
<button type="submit" name="status" value="all" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">Tutte</button>
<button type="submit" name="status" value="Active" class="filter-btn <?= $statusFilter === 'Active' ? 'active' : '' ?>">Active</button>
<button type="submit" name="status" value="Opening" class="filter-btn <?= $statusFilter === 'Opening' ? 'active' : '' ?>">Opening</button>
<button type="submit" name="status" value="Closed" class="filter-btn <?= $statusFilter === 'Closed' ? 'active' : '' ?>">Closed</button>
</form>
</div>
</div>
</div>

<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);color:var(--cb-gray);font-size:.95rem">
<?php if($search): ?>
Trovate <strong style="color:var(--cb-midnight)"><?= count($agencies) ?></strong> agenzie
<?php 
$searchTypeLabels = ['all' => 'ovunque', 'city' => 'in città', 'province' => 'in provincia', 'people' => 'in broker'];
$typeLabel = $searchTypeLabels[$searchType] ?? 'ovunque';
?>
per "<strong><?= htmlspecialchars($search) ?></strong>" <?= $typeLabel ?>
<?php if(count($agencies) < $totalCount): ?>
<span style="opacity:.7">(<?= $totalCount ?> totali con status <?= $statusFilter ?>)</span>
<?php endif; ?>
<?php elseif($statusFilter !== 'all'): ?>
<strong style="color:var(--cb-midnight)"><?= $totalCount ?></strong> agenzie con status <?= $statusFilter ?>
<?php else: ?>
<strong style="color:var(--cb-midnight)"><?= $totalCount ?></strong> agenzie totali
<?php endif; ?>
</div>

<?php if (empty($agencies)): ?>
<div class="empty-state">
<div class="empty-state-icon">🏢</div>
<h3>Nessuna agenzia trovata</h3>
<p>Prova a modificare i filtri o aggiungi una nuova agenzia</p>
</div>
<?php else: ?>
<div class="table-container">
<table class="agencies-table">
<thead>
<tr>
<th class="sortable" data-sort="code"><span style="color:var(--cb-bright-blue)">CBI</span> <span class="sort-arrow">⇅</span></th>
<th class="sortable" data-sort="name"><span style="color:var(--cb-bright-blue)">NOME</span> <span class="sort-arrow">⇅</span></th>
<th class="sortable" data-sort="city"><span style="color:var(--cb-bright-blue)">CITTÀ</span> <span class="sort-arrow">⇅</span></th>
<th>BROKER MANAGER</th>
<th>STATUS</th>
<th>EMAIL</th>
<th>TELEFONO</th>
</tr>
</thead>
<tbody>
<?php foreach ($agencies as $agency): ?>
<tr onclick="window.location.href='agenzia_detail.php?code=<?= urlencode($agency['code']) ?>'">
<td><?= htmlspecialchars($agency['code']) ?></td>
<td class="agency-name"><?= htmlspecialchars($agency['name']) ?></td>
<td><?= htmlspecialchars($agency['city']) ?><?= $agency['province'] ? ', ' . htmlspecialchars($agency['province']) : '' ?></td>
<td><?= htmlspecialchars($agency['broker_manager'] ?: 'Non assegnato') ?></td>
<td><span class="status-badge <?= strtolower($agency['status']) ?>"><?= htmlspecialchars($agency['status']) ?></span></td>
<td><?= htmlspecialchars($agency['email'] ?: '-') ?></td>
<td><?= htmlspecialchars($agency['phone'] ?: '-') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<div class="modal" id="exportModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">📥 Esporta Agenzie</h2>
<button class="modal-close" onclick="closeExportModal()">✕</button>
</div>
<form method="POST" action="agenzie_export.php">
<div class="checkbox-group">
<h3>Info Base</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="code" checked> Codice</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="name" checked> Nome</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="city" checked> Città</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="province"> Provincia</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="email"> Email</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="phone"> Telefono</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="broker"> Broker Manager</label>
</div>
</div>
<div class="checkbox-group">
<h3>Contrattuale</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="tech_fee"> Tech Fee</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="contract_expiry"> Scadenza</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="activation_date"> Attivazione</label>
</div>
</div>
<div class="checkbox-group">
<h3>Servizi</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="cb_suite"> CB Suite</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="canva"> Canva</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="regold"> Regold</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="james"> James Edition</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="docudrop"> Docudrop</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="unique"> Unique</label>
</div>
</div>
<input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType ?? 'all') ?>">
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeExportModal()">Annulla</button>
<button type="submit" class="btn-export">📥 Esporta CSV</button>
</div>
</form>
</div>
</div>

<script>
const exportModal=document.getElementById('exportModal');

function openExportModal(){
const searchValue = document.getElementById('agenciesSearch').value;
const searchTypeValue = document.getElementById('searchType')?.value || 'all';
const hiddenSearch = document.querySelector('#exportModal input[name="search"]');
const hiddenSearchType = document.querySelector('#exportModal input[name="search_type"]');
if(hiddenSearch) hiddenSearch.value = searchValue;
if(hiddenSearchType) hiddenSearchType.value = searchTypeValue;
exportModal.classList.add('open');
}

function closeExportModal(){
exportModal.classList.remove('open');
}

// Gestione filtro ricerca
const searchTypeSelect = document.getElementById('searchType');
const searchInput=document.getElementById('agenciesSearch');

if(searchTypeSelect) {
    searchTypeSelect.addEventListener('change', function() {
        // Submit form quando cambia tipo
        const form = document.createElement('form');
        form.method = 'GET';
        form.style.display = 'none';
        
        const statusInput = document.createElement('input');
        statusInput.name = 'status';
        statusInput.value = '<?= htmlspecialchars($statusFilter) ?>';
        form.appendChild(statusInput);
        
        const searchInput = document.createElement('input');
        searchInput.name = 'search';
        searchInput.value = document.getElementById('agenciesSearch').value;
        form.appendChild(searchInput);
        
        const typeInput = document.createElement('input');
        typeInput.name = 'search_type';
        typeInput.value = this.value;
        form.appendChild(typeInput);
        
        document.body.appendChild(form);
        form.submit();
    });
}

const searchResults=document.getElementById('agenciesSearchResults');
const agenciesTable=document.querySelector('.agencies-table tbody');
let searchTimeout;
let allRows=[];

if(agenciesTable){
allRows=Array.from(agenciesTable.querySelectorAll('tr'));
}

if(searchInput && searchResults){
searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim(); // NON toLowerCase - LIKE è già case-insensitive
const searchTypeElem = document.getElementById('searchType');
const searchType = searchTypeElem ? searchTypeElem.value : 'all'; // Fallback a 'all' se non esiste

// NON filtrare tabella lato client - usiamo sempre ricerca server-side
// per gestire correttamente i filtri città/provincia/broker

if(query.length<2){
searchResults.classList.remove('active');
// Se cancello la ricerca, ricarica senza filtro
if(query.length === 0 && '<?= $search ?>' !== '') {
    window.location.href = '?status=<?= htmlspecialchars($statusFilter) ?>&search_type=' + searchType;
}
return;
}

// Submit automatico dopo 500ms di pausa
searchTimeout=setTimeout(()=>{
    const searchTypeElem = document.getElementById('searchType');
    const searchType = searchTypeElem ? searchTypeElem.value : 'all'; // Fallback a 'all'
    
    console.log('Search submit:', {query: query, searchType: searchType, status: '<?= htmlspecialchars($statusFilter) ?>'});
    
    const form = document.createElement('form');
    form.method = 'GET';
    form.style.display = 'none';
    
    const statusInput = document.createElement('input');
    statusInput.name = 'status';
    statusInput.value = '<?= htmlspecialchars($statusFilter) ?>';
    form.appendChild(statusInput);
    
    const searchField = document.createElement('input');
    searchField.name = 'search';
    searchField.value = query;
    form.appendChild(searchField);
    
    const typeInput = document.createElement('input');
    typeInput.name = 'search_type';
    typeInput.value = searchType;
    form.appendChild(typeInput);
    
    document.body.appendChild(form);
    form.submit();
}, 500);
});

document.addEventListener('click',function(e){
if(!e.target.closest('.search-box')){
searchResults.classList.remove('active');
}
});
}

let currentSort={column:null,direction:'asc'};

document.querySelectorAll('.sortable').forEach(header=>{
header.addEventListener('click',function(){
const column=this.dataset.sort;
const columnIndex={code:0,name:1,city:2}[column];

if(currentSort.column===column){
currentSort.direction=currentSort.direction==='asc'?'desc':'asc';
}else{
currentSort.column=column;
currentSort.direction='asc';
}

document.querySelectorAll('.sortable').forEach(h=>h.classList.remove('asc','desc'));
this.classList.add(currentSort.direction);

const sortedRows=allRows.slice().sort((a,b)=>{
const aText=a.cells[columnIndex].textContent.trim();
const bText=b.cells[columnIndex].textContent.trim();
const comparison=aText.localeCompare(bText,'it',{numeric:true});
return currentSort.direction==='asc'?comparison:-comparison;
});

agenciesTable.innerHTML='';
sortedRows.forEach(row=>agenciesTable.appendChild(row));

allRows=sortedRows;
});
});
</script>

<?php require_once 'footer.php'; ?>

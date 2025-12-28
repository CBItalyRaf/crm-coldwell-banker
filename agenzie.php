<?php
//fixato
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Gestione Agenzie - CRM Coldwell Banker";
$pdo = getDB();

// Filtri - DEFAULT ACTIVE
$statusFilter = $_GET['status'] ?? 'Active';
$search = $_GET['search'] ?? '';

// Query con filtri (escludi Prospect)
$sql = "SELECT code, name, city, province, status, broker_manager, email, phone 
        FROM agencies 
        WHERE status != 'Prospect'";

if ($statusFilter !== 'all') {
    $sql .= " AND status = :status";
}

if ($search) {
    $sql .= " AND (name LIKE :search OR code LIKE :search OR city LIKE :search)";
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);

if ($statusFilter !== 'all') {
    $stmt->bindValue(':status', $statusFilter);
}
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}

$stmt->execute();
$agencies = $stmt->fetchAll();

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
.filters-bar{background:white;border-radius:12px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}
.search-box{position:relative;flex:1}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.search-results{position:absolute;top:100%;left:0;right:0;margin-top:.5rem;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:400px;overflow-y:auto;display:none;z-index:1000}
.search-results.active{display:block}
.search-item{padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;margin-bottom:.25rem}
.search-item-meta{font-size:.85rem;color:var(--cb-gray)}
.status-filters{display:flex;gap:.5rem;flex-wrap:wrap}
.filter-btn{background:white;border:1px solid #E5E7EB;padding:.5rem 1rem;border-radius:8px;font-size:.875rem;cursor:pointer;transition:all .2s}
.filter-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filter-btn.active{background:var(--cb-bright-blue);color:white;border-color:var(--cb-bright-blue)}
.table-container{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden}
.agencies-table{width:100%;border-collapse:collapse}
.agencies-table th{text-align:left;padding:1rem 1.5rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #E5E7EB}
.agencies-table td{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6}
.agencies-table tbody tr:last-child td{border-bottom:none}
.agencies-table tbody tr:hover{background:var(--bg);cursor:pointer}
.agency-name{font-weight:600;color:var(--cb-blue)}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;display:inline-block}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.closed{background:#FEE2E2;color:#991B1B}
.status-badge.opening{background:#FEF3C7;color:#92400E}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-state-icon{font-size:4rem;margin-bottom:1rem;opacity:.3}
.modal{display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;padding:2rem;max-width:600px;width:90%;max-height:80vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.checkbox-group{margin-bottom:1.5rem}
.checkbox-group h3{font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--cb-midnight)}
.checkbox-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem}
.checkbox-label{display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.5rem;border-radius:6px;transition:background .2s}
.checkbox-label:hover{background:var(--bg)}
.checkbox-label input{cursor:pointer}
.modal-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray);color:var(--cb-midnight)}
@media(max-width:768px){
.page-title{font-size:1.5rem}
.filters-grid{grid-template-columns:1fr}
.table-container{overflow-x:auto}
.agencies-table{font-size:.875rem}
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
<input type="text" id="agenciesSearch" placeholder="🔍 Cerca per nome, codice o città..." autocomplete="off">
<div class="search-results" id="agenciesSearchResults"></div>
</div>
<div class="status-filters">
<form method="GET" id="statusForm">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<button type="submit" name="status" value="all" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">Tutte</button>
<button type="submit" name="status" value="Active" class="filter-btn <?= $statusFilter === 'Active' ? 'active' : '' ?>">Active</button>
<button type="submit" name="status" value="Opening" class="filter-btn <?= $statusFilter === 'Opening' ? 'active' : '' ?>">Opening</button>
<button type="submit" name="status" value="Closed" class="filter-btn <?= $statusFilter === 'Closed' ? 'active' : '' ?>">Closed</button>
</form>
</div>
</div>
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
<th>Codice</th>
<th>Nome</th>
<th>Città</th>
<th>Broker Manager</th>
<th>Status</th>
<th>Email</th>
<th>Telefono</th>
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
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeExportModal()">Annulla</button>
<button type="submit" class="btn-export">📥 Esporta CSV</button>
</div>
</form>
</div>
</div>

<script>
// Export modal
const exportModal=document.getElementById('exportModal');

function openExportModal(){
exportModal.classList.add('open');
}

function closeExportModal(){
exportModal.classList.remove('open');
}

// Autocomplete search + filtro tabella
const searchInput=document.getElementById('agenciesSearch');
const searchResults=document.getElementById('agenciesSearchResults');
const agenciesTable=document.querySelector('.agencies-table tbody');
let searchTimeout;
let allRows=[];

// Salva tutte le righe all'avvio
if(agenciesTable){
allRows=Array.from(agenciesTable.querySelectorAll('tr'));
}

if(searchInput && searchResults){
searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim().toLowerCase();

// Filtro tabella in real-time
if(agenciesTable){
if(query.length===0){
// Mostra tutte le righe
allRows.forEach(row=>row.style.display='');
}else{
// Filtra righe
allRows.forEach(row=>{
const text=row.textContent.toLowerCase();
row.style.display=text.includes(query)?'':'none';
});
}
}

// Autocomplete API
if(query.length<2){
searchResults.classList.remove('active');
return;
}

searchTimeout=setTimeout(()=>{
fetch('https://admin.mycb.it/search_api.php?q='+encodeURIComponent(query))
.then(r=>r.json())
.then(data=>{
if(data.length===0){
searchResults.innerHTML='<div style="padding:1rem;text-align:center;color:#6D7180">Nessun risultato</div>';
}else{
searchResults.innerHTML=data.map(item=>`
<div class="search-item" onclick="window.location.href='${item.url}'">
<div class="search-item-title">${item.title}</div>
<div class="search-item-meta">${item.meta}</div>
</div>
`).join('');
}
searchResults.classList.add('active');
});
},300);
});

// Chiudi autocomplete cliccando fuori
document.addEventListener('click',function(e){
if(!e.target.closest('.search-box')){
searchResults.classList.remove('active');
}
});
}
</script>

<?php require_once 'footer.php'; ?>

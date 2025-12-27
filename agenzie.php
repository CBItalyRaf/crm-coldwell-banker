<?php
require_once 'check_auth.php';
require_once 'config/database.php';

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

$user = $_SESSION['crm_user'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestione Agenzie - CRM Coldwell Banker</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--cb-blue:#012169;--cb-bright-blue:#1F69FF;--cb-midnight:#0A1730;--cb-gray:#6D7180;--bg:#F5F7FA;--success:#10B981;--warning:#F59E0B;--danger:#EF4444}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--cb-midnight);line-height:1.6}
.header{background:var(--cb-blue);color:white;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header-content{max-width:1400px;margin:0 auto;padding:0 1.5rem;display:flex;justify-content:space-between;align-items:center;min-height:70px}
.header-left{display:flex;align-items:center;gap:3rem}
.logo{height:32px}
.hamburger{display:none;background:transparent;border:none;color:white;font-size:1.5rem;cursor:pointer;padding:.5rem}
.main-nav{display:flex;gap:.25rem;align-items:center}
.nav-item{position:relative}
.nav-button{background:transparent;border:none;color:white;padding:.75rem 1.25rem;font-size:.875rem;font-weight:500;cursor:pointer;border-radius:6px;transition:background .2s;display:flex;align-items:center;gap:.5rem;text-decoration:none;text-transform:uppercase;letter-spacing:.05em}
.nav-button:hover{background:rgba(255,255,255,.15)}
.dropdown-menu{position:absolute;top:100%;left:0;margin-top:.5rem;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);min-width:200px;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .2s;z-index:1000}
.nav-item:hover .dropdown-menu,.nav-item.open .dropdown-menu{opacity:1;visibility:visible;transform:translateY(0)}
.dropdown-item{display:block;padding:.75rem 1.25rem;color:var(--cb-midnight);text-decoration:none;font-size:.9rem;transition:background .2s}
.dropdown-item:first-child{border-radius:8px 8px 0 0}
.dropdown-item:last-child{border-radius:0 0 8px 8px}
.dropdown-item:hover{background:var(--bg)}
.user-menu{position:relative}
.user-button{display:flex;align-items:center;gap:.75rem;background:transparent;border:none;color:white;padding:.5rem 1rem;cursor:pointer;border-radius:6px;font-size:.95rem;transition:background .2s}
.user-button:hover{background:rgba(255,255,255,.1)}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--cb-bright-blue);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.9rem}
.mobile-sidebar{position:fixed;top:0;left:-300px;width:300px;height:100vh;background:var(--cb-blue);z-index:2000;transition:left .3s;overflow-y:auto;padding:1rem 0}
.mobile-sidebar.open{left:0}
.sidebar-header{padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:1rem}
.sidebar-close{background:transparent;border:none;color:white;font-size:1.5rem;cursor:pointer;float:right}
.sidebar-nav{padding:0 1rem}
.sidebar-item{margin-bottom:.5rem}
.sidebar-button{width:100%;background:transparent;border:none;color:white;padding:.75rem 1rem;text-align:left;font-size:.875rem;font-weight:500;cursor:pointer;border-radius:6px;text-transform:uppercase;letter-spacing:.05em}
.sidebar-button:hover{background:rgba(255,255,255,.1)}
.sidebar-dropdown{padding-left:1rem;margin-top:.5rem;display:none}
.sidebar-dropdown.open{display:block}
.sidebar-dropdown-item{display:block;padding:.75rem 1rem;color:white;text-decoration:none;font-size:.85rem;border-radius:6px}
.sidebar-dropdown-item:hover{background:rgba(255,255,255,.1)}
.sidebar-user{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.1);margin-top:1rem}
.sidebar-user-info{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;color:white}
.sidebar-backdrop{position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);z-index:1999;display:none}
.sidebar-backdrop.open{display:block}
.container{max-width:1400px;margin:0 auto;padding:2rem 1.5rem}
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
.hamburger{display:block}
.header-left{gap:1rem}
.main-nav,.user-menu{display:none}
.container{padding:1rem}
.page-title{font-size:1.5rem}
.filters-grid{grid-template-columns:1fr}
.table-container{overflow-x:auto}
.agencies-table{font-size:.875rem}
.agencies-table th,.agencies-table td{padding:.75rem .5rem}
.header-actions{width:100%;flex-direction:column}
.btn-export,.btn-add{width:100%;justify-content:center}
}
.search-container{position:relative;background:white;border-radius:12px;padding:1rem 1.5rem;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.search-container input{width:100%;border:none;font-size:1rem;outline:none;background:transparent}
.search-results{position:absolute;top:100%;left:0;right:0;margin-top:.5rem;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:400px;overflow-y:auto;display:none;z-index:1000}
.search-results.active{display:block}
.search-item{padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;margin-bottom:.25rem}
.search-item-meta{font-size:.85rem;color:var(--cb-gray)}
</style>
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="mobile-sidebar" id="mobileSidebar">
<div class="sidebar-header">
<button class="sidebar-close" id="sidebarClose">✕</button>
</div>
<div class="sidebar-nav">
<div class="sidebar-item">
<button class="sidebar-button" onclick="toggleSidebarDropdown('gestione')">GESTIONE ▼</button>
<div class="sidebar-dropdown open" id="dropdown-gestione">
<a href="agenzie.php" class="sidebar-dropdown-item">🏢 Agenzie</a>
<a href="agenti.php" class="sidebar-dropdown-item">👥 Agenti</a>
<a href="servizi.php" class="sidebar-dropdown-item">⚙️ Servizi</a>
</div>
</div>
<div class="sidebar-item">
<button class="sidebar-button" onclick="toggleSidebarDropdown('operations')">OPERATIONS ▼</button>
<div class="sidebar-dropdown" id="dropdown-operations">
<a href="onboarding.php" class="sidebar-dropdown-item">📥 Onboarding</a>
<a href="offboarding.php" class="sidebar-dropdown-item">📤 Offboarding</a>
<a href="ticket.php" class="sidebar-dropdown-item">🎫 Ticket</a>
</div>
</div>
<div class="sidebar-item">
<button class="sidebar-button" onclick="toggleSidebarDropdown('admin')">AMMINISTRAZIONE ▼</button>
<div class="sidebar-dropdown" id="dropdown-admin">
<a href="fatture.php" class="sidebar-dropdown-item">💰 Fatture</a>
<a href="fornitori.php" class="sidebar-dropdown-item">🏪 Fornitori</a>
</div>
</div>
<div class="sidebar-item">
<a href="sviluppo.php" class="sidebar-button">SVILUPPO</a>
</div>
<div class="sidebar-item">
<button class="sidebar-button" onclick="toggleSidebarDropdown('team')">TEAM ▼</button>
<div class="sidebar-dropdown" id="dropdown-team">
<a href="ferie.php" class="sidebar-dropdown-item">🌴 Ferie</a>
<a href="calendario.php" class="sidebar-dropdown-item">📅 Calendario</a>
</div>
</div>
</div>
<div class="sidebar-user">
<div class="sidebar-user-info">
<div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
<span><?= htmlspecialchars($user['name']) ?></span>
</div>
<a href="index.php" class="sidebar-dropdown-item">🏠 Dashboard</a>
<a href="logout.php" class="sidebar-dropdown-item">🚪 Logout</a>
</div>
</div>
<div class="header">
<div class="header-content">
<div class="header-left">
<button class="hamburger" id="hamburger">☰</button>
<img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker" class="logo">
<nav class="main-nav">
<div class="nav-item open">
<button class="nav-button">GESTIONE ▼</button>
<div class="dropdown-menu">
<a href="agenzie.php" class="dropdown-item">🏢 Agenzie</a>
<a href="agenti.php" class="dropdown-item">👥 Agenti</a>
<a href="servizi.php" class="dropdown-item">⚙️ Servizi</a>
</div>
</div>
<div class="nav-item">
<button class="nav-button">OPERATIONS ▼</button>
<div class="dropdown-menu">
<a href="onboarding.php" class="dropdown-item">📥 Onboarding</a>
<a href="offboarding.php" class="dropdown-item">📤 Offboarding</a>
<a href="ticket.php" class="dropdown-item">🎫 Ticket</a>
</div>
</div>
<div class="nav-item">
<button class="nav-button">AMMINISTRAZIONE ▼</button>
<div class="dropdown-menu">
<a href="fatture.php" class="dropdown-item">💰 Fatture</a>
<a href="fornitori.php" class="dropdown-item">🏪 Fornitori</a>
</div>
</div>
<a href="sviluppo.php" class="nav-button">SVILUPPO</a>
<div class="nav-item">
<button class="nav-button">TEAM ▼</button>
<div class="dropdown-menu">
<a href="ferie.php" class="dropdown-item">🌴 Ferie</a>
<a href="calendario.php" class="dropdown-item">📅 Calendario</a>
</div>
</div>
</nav>
</div>
<div class="nav-item user-menu">
<button class="user-button">
<div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
<span><?= htmlspecialchars($user['name']) ?></span>
<span>▼</span>
</button>
<div class="dropdown-menu" style="right:0;left:auto">
<a href="index.php" class="dropdown-item">🏠 Dashboard</a>
<a href="logout.php" class="dropdown-item">🚪 Logout</a>
</div>
</div>
</div>
</div>
<div class="container">
<div class="search-container">
<input type="text" id="searchInput" placeholder="🔍 Cerca agenzie, agenti..." autocomplete="off">
<div class="search-results" id="searchResults"></div>
</div>
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
<form method="GET" style="display:flex;gap:.5rem">
<input type="text" name="search" placeholder="🔍 Cerca per nome, codice o città..." value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
</form>
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
</div>

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
const hamburger=document.getElementById('hamburger');
const mobileSidebar=document.getElementById('mobileSidebar');
const sidebarBackdrop=document.getElementById('sidebarBackdrop');
const sidebarClose=document.getElementById('sidebarClose');
const exportModal=document.getElementById('exportModal');

hamburger?.addEventListener('click',()=>{
mobileSidebar.classList.add('open');
sidebarBackdrop.classList.add('open');
});

sidebarClose?.addEventListener('click',closeSidebar);
sidebarBackdrop?.addEventListener('click',closeSidebar);

function closeSidebar(){
mobileSidebar.classList.remove('open');
sidebarBackdrop.classList.remove('open');
}

function toggleSidebarDropdown(id){
const dropdown=document.getElementById('dropdown-'+id);
dropdown?.classList.toggle('open');
}

function openExportModal(){
exportModal.classList.add('open');
}

function closeExportModal(){
exportModal.classList.remove('open');
}

document.querySelectorAll('.nav-button').forEach(btn=>{
btn.addEventListener('click',function(e){
e.stopPropagation();
const parent=this.closest('.nav-item');
document.querySelectorAll('.nav-item').forEach(item=>{
if(item!==parent)item.classList.remove('open');
});
parent.classList.toggle('open');
});
});

document.addEventListener('click',()=>{
document.querySelectorAll('.nav-item').forEach(item=>item.classList.remove('open'));
});

// Autocomplete search
const searchInput=document.getElementById('searchInput');
const searchResults=document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim();
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

document.addEventListener('click',function(e){
if(!e.target.closest('.search-container')){
searchResults.classList.remove('active');
}
});
</script>
</body>
</html>

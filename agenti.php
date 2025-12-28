<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Gestione Agenti - CRM Coldwell Banker";
$pdo = getDB();

$statusFilter = $_GET['status'] ?? 'Active';
$search = $_GET['search'] ?? '';

$sql = "SELECT a.*, ag.name as agency_name, ag.code as agency_code 
        FROM agents a 
        LEFT JOIN agencies ag ON a.agency_id = ag.id 
        WHERE 1=1";

if ($statusFilter !== 'all') {
    $sql .= " AND a.status = :status";
}

if ($search) {
    $sql .= " AND (a.full_name LIKE :search1 OR a.email_corporate LIKE :search2 OR a.mobile LIKE :search3 OR ag.name LIKE :search4)";
}

$sql .= " ORDER BY a.full_name ASC";

$stmt = $pdo->prepare($sql);

if ($statusFilter !== 'all') {
    $stmt->bindValue(':status', $statusFilter);
}
if ($search) {
    $stmt->bindValue(':search1', "%$search%");
    $stmt->bindValue(':search2', "%$search%");
    $stmt->bindValue(':search3', "%$search%");
    $stmt->bindValue(':search4', "%$search%");
}

$stmt->execute();
$agents = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.header-actions{display:flex;gap:1rem}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-add:hover{background:var(--cb-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}
.search-box{position:relative;flex:1}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.status-filters{display:flex;gap:.5rem;flex-wrap:wrap}
.filter-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;cursor:pointer;transition:all .2s;font-size:.9rem}
.filter-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filter-btn.active{background:var(--cb-bright-blue);color:white;border-color:var(--cb-bright-blue)}
.table-container{background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.agents-table{width:100%;border-collapse:collapse}
.agents-table th{text-align:left;padding:1rem 1.5rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #E5E7EB}
.agents-table td{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6}
.agents-table tbody tr{cursor:pointer;transition:background .2s}
.agents-table tbody tr:hover{background:var(--bg)}
.agent-name{font-weight:600;color:var(--cb-midnight)}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.inactive{background:#FEE2E2;color:#991B1B}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-state-icon{font-size:4rem;margin-bottom:1rem;opacity:.5}
</style>

<div class="page-header">
<h1 class="page-title">👥 Gestione Agenti</h1>
<div class="header-actions">
<a href="agente_add.php" class="btn-add">➕ Nuovo Agente</a>
</div>
</div>

<div class="filters-bar">
<div class="filters-grid">
<div class="search-box">
<input type="text" id="agentsSearch" placeholder="🔍 Cerca per nome, email, telefono o agenzia..." autocomplete="off">
</div>
<div class="status-filters">
<form method="GET" id="statusForm">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<button type="submit" name="status" value="all" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">Tutti</button>
<button type="submit" name="status" value="Active" class="filter-btn <?= $statusFilter === 'Active' ? 'active' : '' ?>">Active</button>
<button type="submit" name="status" value="Inactive" class="filter-btn <?= $statusFilter === 'Inactive' ? 'active' : '' ?>">Inactive</button>
</form>
</div>
</div>
</div>

<?php if (empty($agents)): ?>
<div class="empty-state">
<div class="empty-state-icon">👥</div>
<h3>Nessun agente trovato</h3>
<p>Prova a modificare i filtri o aggiungi un nuovo agente</p>
</div>
<?php else: ?>
<div class="table-container">
<table class="agents-table">
<thead>
<tr>
<th>NOME</th>
<th>AGENZIA</th>
<th>EMAIL</th>
<th>TELEFONO</th>
<th>STATUS</th>
</tr>
</thead>
<tbody>
<?php foreach ($agents as $agent): ?>
<tr onclick="window.location.href='agente_detail.php?id=<?= $agent['id'] ?>'">
<td class="agent-name"><?= htmlspecialchars($agent['full_name']) ?></td>
<td><?= htmlspecialchars($agent['agency_name'] ?: '-') ?> <?= $agent['agency_code'] ? '(' . htmlspecialchars($agent['agency_code']) . ')' : '' ?></td>
<td><?= htmlspecialchars($agent['email_corporate'] ?: $agent['email_personal'] ?: '-') ?></td>
<td><?= htmlspecialchars($agent['mobile'] ?: '-') ?></td>
<td><span class="status-badge <?= strtolower($agent['status']) ?>"><?= htmlspecialchars($agent['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<script>
const searchInput=document.getElementById('agentsSearch');
let allRows=[];

if(document.querySelector('.agents-table tbody')){
allRows=Array.from(document.querySelectorAll('.agents-table tbody tr'));
}

if(searchInput){
searchInput.addEventListener('input',function(){
const query=this.value.trim().toLowerCase();

if(query.length===0){
allRows.forEach(row=>row.style.display='');
}else{
allRows.forEach(row=>{
const text=row.textContent.toLowerCase();
row.style.display=text.includes(query)?'':'none';
});
}
});
}
</script>

<?php require_once 'footer.php'; ?>

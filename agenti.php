<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Gestione Agenti - CRM Coldwell Banker";
$pdo = getDB();

$statusFilters = isset($_GET['status']) && is_array($_GET['status']) ? $_GET['status'] : ['Active'];
$validStatuses = ['Active', 'Inactive'];
$statusFilters = array_intersect($statusFilters, $validStatuses);
if (empty($statusFilters)) {
    $statusFilters = ['Active'];
}

// Filtri tipo account M365
$accountTypeFilters = isset($_GET['account_type']) && is_array($_GET['account_type']) ? $_GET['account_type'] : ['agente', 'agenzia', 'servizio', 'master'];
$validAccountTypes = ['agente', 'agenzia', 'servizio', 'master'];
$accountTypeFilters = array_intersect($accountTypeFilters, $validAccountTypes);
if (empty($accountTypeFilters)) {
    $accountTypeFilters = ['agente', 'agenzia', 'servizio', 'master'];
}

$search = $_GET['search'] ?? '';
$searchType = $_GET['search_type'] ?? 'all';

// Valida searchType
$validSearchTypes = ['all', 'city', 'province', 'people'];
if (!in_array($searchType, $validSearchTypes)) {
    $searchType = 'all';
}

$sql = "SELECT a.*, ag.name as agency_name, ag.code as agency_code, ag.city as agency_city, ag.province as agency_province
        FROM agents a 
        LEFT JOIN agencies ag ON a.agency_id = ag.id 
        WHERE a.status IN (" . implode(',', array_fill(0, count($statusFilters), '?')) . ")
        AND a.m365_account_type IN (" . implode(',', array_fill(0, count($accountTypeFilters), '?')) . ")";

$params = array_merge($statusFilters, $accountTypeFilters);

if ($search) {
    if ($searchType === 'city') {
        $sql .= " AND ag.city LIKE ?";
        $params[] = "%$search%";
    } elseif ($searchType === 'province') {
        $sql .= " AND ag.province LIKE ?";
        $params[] = "%$search%";
    } elseif ($searchType === 'people') {
        $sql .= " AND a.full_name LIKE ?";
        $params[] = "%$search%";
    } else {
        // all - cerca in tutto
        $sql .= " AND (a.full_name LIKE ? OR a.email_corporate LIKE ? OR ag.name LIKE ? OR ag.city LIKE ? OR ag.province LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$sql .= " ORDER BY a.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agents = $stmt->fetchAll();

// Count totale con status filter e account type filter applicati
$countSql = "SELECT COUNT(*) FROM agents 
             WHERE status IN (" . implode(',', array_fill(0, count($statusFilters), '?')) . ")
             AND m365_account_type IN (" . implode(',', array_fill(0, count($accountTypeFilters), '?')) . ")";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute(array_merge($statusFilters, $accountTypeFilters));
$totalCount = $countStmt->fetchColumn();

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.header-actions{display:flex;gap:1rem}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-add:hover{background:var(--cb-blue)}
.btn-export{background:#10B981;color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-export:hover{background:#059669}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}
.search-box{position:relative;flex:1}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.status-filters{display:flex;gap:.5rem;flex-wrap:wrap}
.filter-checkbox{display:inline-flex;align-items:center;gap:.5rem;background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;cursor:pointer;transition:all .2s;font-size:.9rem;user-select:none}
.filter-checkbox:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.filter-checkbox.active{background:var(--cb-bright-blue);color:white;border-color:var(--cb-bright-blue)}
.filter-checkbox input[type="checkbox"]{cursor:pointer}
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
</style>

<div class="page-header">
<h1 class="page-title">👥 Gestione Agenti</h1>
<div class="header-actions">
<button class="btn-export" onclick="openExportModal()">📥 Esporta CSV</button>
<a href="agente_add.php" class="btn-add">➕ Nuovo Agente</a>
</div>
</div>

<div class="filters-bar">
<div class="filters-grid">
<div class="search-box">
<div style="display:flex;gap:.5rem">
<select id="searchType" name="search_type" style="padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;background:white;cursor:pointer;min-width:140px">
<option value="all" <?= $searchType === 'all' ? 'selected' : '' ?>>🌍 Tutto</option>
<option value="city" <?= $searchType === 'city' ? 'selected' : '' ?>>🏙️ Solo Città</option>
<option value="province" <?= $searchType === 'province' ? 'selected' : '' ?>>📍 Solo Provincia</option>
<option value="people" <?= $searchType === 'people' ? 'selected' : '' ?>>👤 Solo Persone</option>
</select>
<input type="text" id="agentsSearch" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cerca..." autocomplete="off" style="flex:1">
</div>
</div>
<div class="status-filters">
<form method="GET" id="statusForm">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType) ?>">
<?php foreach($accountTypeFilters as $type): ?>
<input type="hidden" name="account_type[]" value="<?= htmlspecialchars($type) ?>">
<?php endforeach; ?>
<label class="filter-checkbox <?= in_array('Active', $statusFilters) ? 'active' : '' ?>">
<input type="checkbox" name="status[]" value="Active" <?= in_array('Active', $statusFilters) ? 'checked' : '' ?> onchange="document.getElementById('statusForm').submit()">
Active
</label>
<label class="filter-checkbox <?= in_array('Inactive', $statusFilters) ? 'active' : '' ?>">
<input type="checkbox" name="status[]" value="Inactive" <?= in_array('Inactive', $statusFilters) ? 'checked' : '' ?> onchange="document.getElementById('statusForm').submit()">
Inactive
</label>
</form>
</div>
</div>
</div>

<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
<div style="color:var(--cb-gray);font-size:.875rem;font-weight:600;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em">Tipo Account</div>
<form method="GET" id="accountTypeForm" style="display:flex;gap:.5rem;flex-wrap:wrap">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType) ?>">
<?php foreach($statusFilters as $status): ?>
<input type="hidden" name="status[]" value="<?= htmlspecialchars($status) ?>">
<?php endforeach; ?>
<label class="filter-checkbox <?= in_array('agente', $accountTypeFilters) ? 'active' : '' ?>">
<input type="checkbox" name="account_type[]" value="agente" <?= in_array('agente', $accountTypeFilters) ? 'checked' : '' ?> onchange="document.getElementById('accountTypeForm').submit()">
👤 Agenti
</label>
<label class="filter-checkbox <?= in_array('agenzia', $accountTypeFilters) ? 'active' : '' ?>">
<input type="checkbox" name="account_type[]" value="agenzia" <?= in_array('agenzia', $accountTypeFilters) ? 'checked' : '' ?> onchange="document.getElementById('accountTypeForm').submit()">
🏢 Agenzia
</label>
<label class="filter-checkbox <?= in_array('servizio', $accountTypeFilters) ? 'active' : '' ?>">
<input type="checkbox" name="account_type[]" value="servizio" <?= in_array('servizio', $accountTypeFilters) ? 'checked' : '' ?> onchange="document.getElementById('accountTypeForm').submit()">
⚙️ Servizio
</label>
<label class="filter-checkbox <?= in_array('master', $accountTypeFilters) ? 'active' : '' ?>">
<input type="checkbox" name="account_type[]" value="master" <?= in_array('master', $accountTypeFilters) ? 'checked' : '' ?> onchange="document.getElementById('accountTypeForm').submit()">
⭐ Master
</label>
</form>
</div>

<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);color:var(--cb-gray);font-size:.95rem">
<?php if($search): ?>
Trovati <strong style="color:var(--cb-midnight)"><?= count($agents) ?></strong> agenti
<?php 
$searchTypeLabels = ['all' => 'ovunque', 'city' => 'in città', 'province' => 'in provincia', 'people' => 'in persone'];
$typeLabel = $searchTypeLabels[$searchType] ?? 'ovunque';
?>
per "<strong><?= htmlspecialchars($search) ?></strong>" <?= $typeLabel ?>
<?php if(count($agents) < $totalCount): ?>
<span style="opacity:.7">(<?= $totalCount ?> totali)</span>
<?php endif; ?>
<?php else: ?>
<strong style="color:var(--cb-midnight)"><?= $totalCount ?></strong> agenti
<?php endif; ?>
<?php
$accountTypeLabels = ['agente' => 'Agenti', 'agenzia' => 'Agenzia', 'servizio' => 'Servizio', 'master' => 'Master'];
$selectedTypes = array_map(fn($t) => $accountTypeLabels[$t] ?? $t, $accountTypeFilters);
?>
<span style="opacity:.7"> - Tipo: <?= implode(', ', $selectedTypes) ?> | Status: <?= implode(', ', $statusFilters) ?></span>
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
<th>RUOLI</th>
<th>EMAIL</th>
<th>TELEFONO</th>
<th>STATUS</th>
</tr>
</thead>
<tbody>
<?php foreach ($agents as $agent): 
    $rolesJson = $agent['role'];
    $roles = $rolesJson ? json_decode($rolesJson, true) : [];
?>
<tr onclick="window.location.href='agente_detail.php?id=<?= $agent['id'] ?>'">
<td class="agent-name"><?= htmlspecialchars($agent['full_name']) ?></td>
<td><?= htmlspecialchars($agent['agency_name'] ?: '-') ?> <?= $agent['agency_code'] ? '(' . htmlspecialchars($agent['agency_code']) . ')' : '' ?></td>
<td>
<?php if (!empty($roles)): ?>
    <?php foreach ($roles as $role): ?>
        <?php
        $roleBadges = [
            'broker' => ['label' => 'Broker', 'color' => '#3B82F6'],
            'broker_manager' => ['label' => 'Broker Manager', 'color' => '#10B981'],
            'legale_rappresentante' => ['label' => 'Legale Rapp.', 'color' => '#F59E0B'],
            'preposto' => ['label' => 'Preposto', 'color' => '#F97316'],
            'global_luxury' => ['label' => 'Global Luxury', 'color' => '#8B5CF6']
        ];
        $badge = $roleBadges[$role] ?? ['label' => $role, 'color' => '#6B7280'];
        ?>
        <span style="display:inline-block;padding:.25rem .5rem;border-radius:6px;font-size:.7rem;font-weight:600;background:<?= $badge['color'] ?>;color:white;margin-right:.25rem;margin-bottom:.25rem;white-space:nowrap"><?= $badge['label'] ?></span>
    <?php endforeach; ?>
<?php else: ?>
    <span style="color:var(--cb-gray);font-size:.85rem">-</span>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($agent['email_corporate'] ?: $agent['email_personal'] ?: '-') ?></td>
<td><?= htmlspecialchars($agent['mobile'] ?: '-') ?></td>
<td><span class="status-badge <?= strtolower($agent['status']) ?>"><?= htmlspecialchars($agent['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Modal Export -->
<div class="modal" id="exportModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">📥 Esporta Agenti</h2>
<button class="modal-close" onclick="closeExportModal()">✕</button>
</div>
<form method="POST" action="agenti_export.php">
<div class="checkbox-group">
<h3>Info Base</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="full_name" checked> Nome Completo</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="agency_name" checked> Agenzia</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="agency_code" checked> Codice Agenzia</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="role"> Ruoli</label>
</div>
</div>
<div class="checkbox-group">
<h3>Contatti</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="email_corporate" checked> Email Corporate</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="email_personal"> Email Personale</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="mobile" checked> Telefono</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="phone"> Telefono Fisso</label>
</div>
</div>
<div class="checkbox-group">
<h3>Anagrafica</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="fiscal_code"> Codice Fiscale</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="birth_date"> Data Nascita</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="birth_place"> Luogo Nascita</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="address"> Indirizzo</label>
</div>
</div>
<div class="checkbox-group">
<h3>Microsoft 365</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="m365_account_type"> Tipo Account</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="m365_plan"> Piano M365</label>
</div>
</div>
<div class="checkbox-group">
<h3>Altro</h3>
<div class="checkbox-grid">
<label class="checkbox-label"><input type="checkbox" name="export[]" value="status" checked> Status</label>
<label class="checkbox-label"><input type="checkbox" name="export[]" value="created_at"> Data Creazione</label>
</div>
</div>
<?php foreach($statusFilters as $status): ?>
<input type="hidden" name="status_filter[]" value="<?= htmlspecialchars($status) ?>">
<?php endforeach; ?>
<?php foreach($accountTypeFilters as $type): ?>
<input type="hidden" name="account_type_filter[]" value="<?= htmlspecialchars($type) ?>">
<?php endforeach; ?>
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
<input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType) ?>">
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
exportModal.classList.add('open');
}

function closeExportModal(){
exportModal.classList.remove('open');
}

// Gestione cambio tipo ricerca
const searchTypeSelect = document.getElementById('searchType');
const searchInput=document.getElementById('agentsSearch');

if(searchTypeSelect) {
    searchTypeSelect.addEventListener('change', function() {
        // Submit form quando cambia tipo
        const form = document.createElement('form');
        form.method = 'GET';
        form.style.display = 'none';
        
        // Aggiungi status multipli
        <?php foreach($statusFilters as $status): ?>
        const statusInput<?= $status ?> = document.createElement('input');
        statusInput<?= $status ?>.name = 'status[]';
        statusInput<?= $status ?>.value = '<?= htmlspecialchars($status) ?>';
        form.appendChild(statusInput<?= $status ?>);
        <?php endforeach; ?>
        
        const searchInputField = document.createElement('input');
        searchInputField.name = 'search';
        searchInputField.value = document.getElementById('agentsSearch').value;
        form.appendChild(searchInputField);
        
        const typeInput = document.createElement('input');
        typeInput.name = 'search_type';
        typeInput.value = this.value;
        form.appendChild(typeInput);
        
        document.body.appendChild(form);
        form.submit();
    });
}

let searchTimeout;
let allRows=[];

if(document.querySelector('.agents-table tbody')){
allRows=Array.from(document.querySelectorAll('.agents-table tbody tr'));
}

if(searchInput){
searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim();
const searchTypeElem = document.getElementById('searchType');
const searchType = searchTypeElem ? searchTypeElem.value : 'all';

if(query.length<2){
    // Se cancello la ricerca, ricarica senza filtro
    if(query.length === 0 && '<?= $search ?>' !== '') {
        window.location.href = '?<?php foreach($statusFilters as $i => $status): ?>status[]=<?= urlencode($status) ?><?= $i < count($statusFilters)-1 ? '&' : '' ?><?php endforeach; ?>&search_type=' + searchType;
    }
    return;
}

// Submit automatico dopo 500ms di pausa
searchTimeout=setTimeout(()=>{
    const form = document.createElement('form');
    form.method = 'GET';
    form.style.display = 'none';
    
    // Aggiungi status multipli
    <?php foreach($statusFilters as $status): ?>
    const statusInput<?= $status ?> = document.createElement('input');
    statusInput<?= $status ?>.name = 'status[]';
    statusInput<?= $status ?>.value = '<?= htmlspecialchars($status) ?>';
    form.appendChild(statusInput<?= $status ?>);
    <?php endforeach; ?>
    
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
}
</script>

<?php require_once 'footer.php'; ?>

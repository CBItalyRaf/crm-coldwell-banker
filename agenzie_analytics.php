<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

// Solo admin possono accedere
if (!isset($_SESSION['crm_user']) || $_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = "Analytics Agenzie - CRM Coldwell Banker";
$pdo = getDB();

// Filtri date
$soldFrom = $_GET['sold_from'] ?? '';
$soldTo = $_GET['sold_to'] ?? '';
$activationFrom = $_GET['activation_from'] ?? '';
$activationTo = $_GET['activation_to'] ?? '';
$closedFrom = $_GET['closed_from'] ?? '';
$closedTo = $_GET['closed_to'] ?? '';

// Query base
$sql = "SELECT code, name, city, province, type, status, sold_date, activation_date, closed_date 
        FROM agencies 
        WHERE status != 'Prospect'";

$params = [];

// Filtro sold_date
if ($soldFrom && $soldTo) {
    $sql .= " AND sold_date BETWEEN :sold_from AND :sold_to";
    $params[':sold_from'] = $soldFrom;
    $params[':sold_to'] = $soldTo;
} elseif ($soldFrom) {
    $sql .= " AND sold_date >= :sold_from";
    $params[':sold_from'] = $soldFrom;
} elseif ($soldTo) {
    $sql .= " AND sold_date <= :sold_to";
    $params[':sold_to'] = $soldTo;
}

// Filtro activation_date
if ($activationFrom && $activationTo) {
    $sql .= " AND activation_date BETWEEN :activation_from AND :activation_to";
    $params[':activation_from'] = $activationFrom;
    $params[':activation_to'] = $activationTo;
} elseif ($activationFrom) {
    $sql .= " AND activation_date >= :activation_from";
    $params[':activation_from'] = $activationFrom;
} elseif ($activationTo) {
    $sql .= " AND activation_date <= :activation_to";
    $params[':activation_to'] = $activationTo;
}

// Filtro closed_date
if ($closedFrom && $closedTo) {
    $sql .= " AND closed_date BETWEEN :closed_from AND :closed_to";
    $params[':closed_from'] = $closedFrom;
    $params[':closed_to'] = $closedTo;
} elseif ($closedFrom) {
    $sql .= " AND closed_date >= :closed_from";
    $params[':closed_from'] = $closedFrom;
} elseif ($closedTo) {
    $sql .= " AND closed_date <= :closed_to";
    $params[':closed_to'] = $closedTo;
}

$sql .= " ORDER BY sold_date DESC, activation_date DESC, closed_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agencies = $stmt->fetchAll();

// Statistiche
$soldCount = 0;
$activatedCount = 0;
$closedCount = 0;

foreach ($agencies as $agency) {
    if ($agency['sold_date'] && 
        (!$soldFrom || $agency['sold_date'] >= $soldFrom) && 
        (!$soldTo || $agency['sold_date'] <= $soldTo)) {
        $soldCount++;
    }
    if ($agency['activation_date'] && 
        (!$activationFrom || $agency['activation_date'] >= $activationFrom) && 
        (!$activationTo || $agency['activation_date'] <= $activationTo)) {
        $activatedCount++;
    }
    if ($agency['closed_date'] && 
        (!$closedFrom || $agency['closed_date'] >= $closedFrom) && 
        (!$closedTo || $agency['closed_date'] <= $closedTo)) {
        $closedCount++;
    }
}

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.filters-card{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:1.5rem}
.filter-group{display:flex;flex-direction:column;gap:.5rem}
.filter-label{font-size:.875rem;font-weight:600;color:var(--cb-midnight);display:flex;align-items:center;gap:.5rem}
.date-range{display:flex;gap:.5rem;align-items:center}
.date-range input{flex:1;padding:.5rem;border:1px solid #E5E7EB;border-radius:6px;font-size:.875rem}
.date-range input:focus{outline:none;border-color:var(--cb-bright-blue)}
.date-range-separator{color:var(--cb-gray);font-size:.875rem}
.filter-actions{display:flex;gap:1rem;justify-content:flex-end}
.btn-search{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;font-weight:500}
.btn-search:hover{background:var(--cb-blue)}
.btn-reset{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:all .2s;font-size:.95rem}
.btn-reset:hover{border-color:var(--cb-gray)}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:2rem}
.stat-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);text-align:center}
.stat-icon{font-size:2rem;margin-bottom:.5rem}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--cb-blue);margin-bottom:.25rem}
.stat-label{font-size:.875rem;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.table-container{background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.analytics-table{width:100%;border-collapse:collapse}
.analytics-table th{text-align:left;padding:1rem 1.5rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #E5E7EB}
.analytics-table td{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6}
.analytics-table tbody tr{transition:background .2s;cursor:pointer}
.analytics-table tbody tr:hover{background:var(--bg)}
.agency-name{font-weight:600;color:var(--cb-midnight)}
.date-cell{font-size:.875rem;color:var(--cb-gray)}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.opening{background:#FEF3C7;color:#92400E}
.status-badge.closing{background:#FEE2E2;color:#991B1B}
.status-badge.closed{background:#F3F4F6;color:#6B7280}
.type-badge{padding:.25rem .5rem;border-radius:6px;font-size:.75rem;font-weight:600;background:#EFF6FF;color:#1E40AF}
.type-badge.satellite{background:#FEF3C7;color:#92400E}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-state-icon{font-size:4rem;margin-bottom:1rem;opacity:.5}
.btn-export{background:var(--success);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-export:hover{background:#059669}
@media(max-width:1024px){
.filters-grid{grid-template-columns:1fr}
.stats-grid{grid-template-columns:1fr}
}
</style>

<div class="page-header">
<h1 class="page-title">📊 Analytics Agenzie</h1>
<div style="display:flex;gap:1rem">
<a href="agenzie.php" class="btn-reset">← Torna alla Lista</a>
<?php if(!empty($agencies)): ?>
<button class="btn-export" onclick="exportCSV()">📥 Esporta CSV</button>
<?php endif; ?>
</div>
</div>

<div class="filters-card">
<form method="GET">
<div class="filters-grid">
<div class="filter-group">
<label class="filter-label">📦 SOLD DATE</label>
<div class="date-range">
<input type="date" name="sold_from" value="<?= htmlspecialchars($soldFrom) ?>" placeholder="Da">
<span class="date-range-separator">→</span>
<input type="date" name="sold_to" value="<?= htmlspecialchars($soldTo) ?>" placeholder="A">
</div>
</div>

<div class="filter-group">
<label class="filter-label">✅ ACTIVATION DATE</label>
<div class="date-range">
<input type="date" name="activation_from" value="<?= htmlspecialchars($activationFrom) ?>" placeholder="Da">
<span class="date-range-separator">→</span>
<input type="date" name="activation_to" value="<?= htmlspecialchars($activationTo) ?>" placeholder="A">
</div>
</div>

<div class="filter-group">
<label class="filter-label">❌ CLOSED DATE</label>
<div class="date-range">
<input type="date" name="closed_from" value="<?= htmlspecialchars($closedFrom) ?>" placeholder="Da">
<span class="date-range-separator">→</span>
<input type="date" name="closed_to" value="<?= htmlspecialchars($closedTo) ?>" placeholder="A">
</div>
</div>
</div>

<div class="filter-actions">
<button type="button" class="btn-reset" onclick="window.location.href='agenzie_analytics.php'">↻ Reset</button>
<button type="submit" class="btn-search">🔍 Cerca</button>
</div>
</form>
</div>

<?php if($soldFrom || $soldTo || $activationFrom || $activationTo || $closedFrom || $closedTo): ?>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon">📦</div>
<div class="stat-value"><?= $soldCount ?></div>
<div class="stat-label">Vendute</div>
</div>
<div class="stat-card">
<div class="stat-icon">✅</div>
<div class="stat-value"><?= $activatedCount ?></div>
<div class="stat-label">Aperte</div>
</div>
<div class="stat-card">
<div class="stat-icon">❌</div>
<div class="stat-value"><?= $closedCount ?></div>
<div class="stat-label">Chiuse</div>
</div>
</div>
<?php endif; ?>

<?php if (empty($agencies)): ?>
<div class="empty-state">
<div class="empty-state-icon">📊</div>
<h3>Nessun risultato</h3>
<p>Seleziona un periodo per visualizzare i dati</p>
</div>
<?php else: ?>
<div class="table-container">
<table class="analytics-table" id="analyticsTable">
<thead>
<tr>
<th>CBI</th>
<th>NOME</th>
<th>CITTÀ</th>
<th>TIPO</th>
<th>STATUS</th>
<th>SOLD DATE</th>
<th>ACTIVATION</th>
<th>CLOSED</th>
</tr>
</thead>
<tbody>
<?php foreach ($agencies as $agency): ?>
<tr onclick="window.location.href='agenzia_detail.php?code=<?= urlencode($agency['code']) ?>'">
<td><?= htmlspecialchars($agency['code']) ?></td>
<td class="agency-name"><?= htmlspecialchars($agency['name']) ?></td>
<td><?= htmlspecialchars($agency['city'] ?: '-') ?><?= $agency['province'] ? ', ' . htmlspecialchars($agency['province']) : '' ?></td>
<td>
<?php if($agency['type'] === 'Satellite'): ?>
<span class="type-badge satellite">Satellite</span>
<?php else: ?>
<span class="type-badge"><?= htmlspecialchars($agency['type']) ?></span>
<?php endif; ?>
</td>
<td><span class="status-badge <?= strtolower($agency['status']) ?>"><?= htmlspecialchars($agency['status']) ?></span></td>
<td class="date-cell"><?= $agency['sold_date'] ? date('d/m/Y', strtotime($agency['sold_date'])) : '-' ?></td>
<td class="date-cell"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></td>
<td class="date-cell"><?= $agency['closed_date'] ? date('d/m/Y', strtotime($agency['closed_date'])) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<script>
function exportCSV() {
    const table = document.getElementById('analyticsTable');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach((td, idx) => {
            let text = td.textContent.trim();
            // Clean badges
            if(td.querySelector('.status-badge') || td.querySelector('.type-badge')) {
                text = td.querySelector('.status-badge, .type-badge').textContent.trim();
            }
            row.push('"' + text.replace(/"/g, '""') + '"');
        });
        csv.push(row.join(','));
    });
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'analytics_agenzie_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'footer.php'; ?>

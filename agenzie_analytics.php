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

// Filtro periodo unico
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$soldAgencies = [];
$activatedAgencies = [];
$closedAgencies = [];

if ($dateFrom && $dateTo) {
    // Vendute nel periodo
    $sql = "SELECT code, name, city, province, type, status, sold_date, activation_date, closed_date 
            FROM agencies 
            WHERE sold_date BETWEEN :date_from AND :date_to
            ORDER BY sold_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
    $soldAgencies = $stmt->fetchAll();
    
    // Aperte nel periodo
    $sql = "SELECT code, name, city, province, type, status, sold_date, activation_date, closed_date 
            FROM agencies 
            WHERE activation_date BETWEEN :date_from AND :date_to
            ORDER BY activation_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
    $activatedAgencies = $stmt->fetchAll();
    
    // Chiuse nel periodo
    $sql = "SELECT code, name, city, province, type, status, sold_date, activation_date, closed_date 
            FROM agencies 
            WHERE closed_date BETWEEN :date_from AND :date_to
            ORDER BY closed_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
    $closedAgencies = $stmt->fetchAll();
}

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.filters-card{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-content{display:flex;gap:1rem;align-items:flex-end}
.filter-group{flex:1;display:flex;flex-direction:column;gap:.5rem}
.filter-label{font-size:.875rem;font-weight:600;color:var(--cb-midnight);display:flex;align-items:center;gap:.5rem}
.date-range{display:flex;gap:.5rem;align-items:center}
.date-range input{flex:1;padding:.75rem;border:1px solid #E5E7EB;border-radius:6px;font-size:.9rem}
.date-range input:focus{outline:none;border-color:var(--cb-bright-blue)}
.date-range-separator{color:var(--cb-gray);font-size:.875rem;font-weight:600}
.filter-actions{display:flex;gap:1rem}
.btn-search{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;font-weight:500;white-space:nowrap}
.btn-search:hover{background:var(--cb-blue)}
.btn-reset{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:all .2s;font-size:.95rem;white-space:nowrap}
.btn-reset:hover{border-color:var(--cb-gray)}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:2rem}
.stat-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);text-align:center}
.stat-icon{font-size:2.5rem;margin-bottom:.5rem}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--cb-blue);margin-bottom:.25rem}
.stat-label{font-size:.875rem;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.section-title{font-size:1.25rem;font-weight:600;color:var(--cb-midnight);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
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
.no-data{text-align:center;padding:2rem;color:var(--cb-gray);font-style:italic}
.btn-export{background:var(--success);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;font-weight:500}
.btn-export:hover{background:#059669}
@media(max-width:1024px){
.filters-content{flex-direction:column}
.stats-grid{grid-template-columns:1fr}
}
</style>

<div class="page-header">
<h1 class="page-title">📊 Analytics Agenzie</h1>
<div style="display:flex;gap:1rem">
<a href="agenzie.php" class="btn-reset">← Torna alla Lista</a>
</div>
</div>

<div class="filters-card">
<form method="GET">
<div class="filters-content">
<div class="filter-group">
<label class="filter-label">📅 PERIODO</label>
<div class="date-range">
<input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="Da" required>
<span class="date-range-separator">→</span>
<input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" placeholder="A" required>
</div>
</div>
<div class="filter-actions">
<button type="button" class="btn-reset" onclick="window.location.href='agenzie_analytics.php'">↻ Reset</button>
<button type="submit" class="btn-search">🔍 Cerca</button>
</div>
</div>
</form>
</div>

<?php if($dateFrom && $dateTo): ?>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon">📦</div>
<div class="stat-value"><?= count($soldAgencies) ?></div>
<div class="stat-label">Vendute</div>
</div>
<div class="stat-card">
<div class="stat-icon">✅</div>
<div class="stat-value"><?= count($activatedAgencies) ?></div>
<div class="stat-label">Aperte</div>
</div>
<div class="stat-card">
<div class="stat-icon">❌</div>
<div class="stat-value"><?= count($closedAgencies) ?></div>
<div class="stat-label">Chiuse</div>
</div>
</div>

<!-- TABELLA VENDUTE -->
<h2 class="section-title">📦 Agenzie Vendute nel Periodo</h2>
<?php if(empty($soldAgencies)): ?>
<div class="no-data">Nessuna agenzia venduta in questo periodo</div>
<?php else: ?>
<div class="table-container">
<table class="analytics-table">
<thead>
<tr>
<th>CBI</th>
<th>NOME</th>
<th>CITTÀ</th>
<th>TIPO</th>
<th>STATUS</th>
<th>DATA VENDITA</th>
<th>DATA ATTIVAZIONE</th>
</tr>
</thead>
<tbody>
<?php foreach ($soldAgencies as $agency): ?>
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
<td class="date-cell"><strong><?= $agency['sold_date'] ? date('d/m/Y', strtotime($agency['sold_date'])) : '-' ?></strong></td>
<td class="date-cell"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- TABELLA APERTE -->
<h2 class="section-title">✅ Agenzie Aperte nel Periodo</h2>
<?php if(empty($activatedAgencies)): ?>
<div class="no-data">Nessuna agenzia aperta in questo periodo</div>
<?php else: ?>
<div class="table-container">
<table class="analytics-table">
<thead>
<tr>
<th>CBI</th>
<th>NOME</th>
<th>CITTÀ</th>
<th>TIPO</th>
<th>STATUS</th>
<th>DATA VENDITA</th>
<th>DATA ATTIVAZIONE</th>
</tr>
</thead>
<tbody>
<?php foreach ($activatedAgencies as $agency): ?>
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
<td class="date-cell"><strong><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- TABELLA CHIUSE -->
<h2 class="section-title">❌ Agenzie Chiuse nel Periodo</h2>
<?php if(empty($closedAgencies)): ?>
<div class="no-data">Nessuna agenzia chiusa in questo periodo</div>
<?php else: ?>
<div class="table-container">
<table class="analytics-table">
<thead>
<tr>
<th>CBI</th>
<th>NOME</th>
<th>CITTÀ</th>
<th>TIPO</th>
<th>STATUS</th>
<th>DATA ATTIVAZIONE</th>
<th>DATA CHIUSURA</th>
</tr>
</thead>
<tbody>
<?php foreach ($closedAgencies as $agency): ?>
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
<td class="date-cell"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></td>
<td class="date-cell"><strong><?= $agency['closed_date'] ? date('d/m/Y', strtotime($agency['closed_date'])) : '-' ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state">
<div class="empty-state-icon">📊</div>
<h3>Seleziona un periodo</h3>
<p>Imposta le date per visualizzare le statistiche</p>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

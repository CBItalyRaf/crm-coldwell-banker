<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/log_functions.php';

// Solo admin può vedere i log
if ($_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = "Log Attività - CRM Coldwell Banker";
$pdo = getDB();

// Filtri
$actionFilter = $_GET['action'] ?? '';
$tableFilter = $_GET['table'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Query - raggruppa per azione (potrebbero esserci più field_name per stessa UPDATE)
$sql = "SELECT 
    MIN(id) as group_id,
    user_id,
    username,
    table_name,
    record_id,
    action,
    ip_address,
    MIN(created_at) as created_at,
    COUNT(DISTINCT field_name) as fields_count
FROM audit_logs 
WHERE 1=1";

$params = [];

if ($actionFilter) {
    $sql .= " AND action = ?";
    $params[] = $actionFilter;
}
if ($tableFilter) {
    $sql .= " AND table_name = ?";
    $params[] = $tableFilter;
}
if ($userFilter) {
    $sql .= " AND username LIKE ?";
    $params[] = "%$userFilter%";
}
if ($dateFrom) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " GROUP BY user_id, username, table_name, record_id, action, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s')
ORDER BY created_at DESC 
LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Statistiche
$statsToday = $pdo->query("SELECT COUNT(DISTINCT CONCAT(table_name, '-', record_id, '-', action, '-', DATE_FORMAT(created_at, '%Y%m%d%H%i%s'))) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$statsWeek = $pdo->query("SELECT COUNT(DISTINCT CONCAT(table_name, '-', record_id, '-', action, '-', DATE_FORMAT(created_at, '%Y%m%d%H%i%s'))) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.stat-label{font-size:.875rem;color:var(--cb-gray);margin-bottom:.5rem}
.stat-value{font-size:2rem;font-weight:700;color:var(--cb-midnight)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.85rem;font-weight:600;color:var(--cb-gray)}
.form-field input,.form-field select{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.9rem}
.btn-filter{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-size:.9rem;grid-column:span 2}
.btn-reset{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-block;text-align:center}
.logs-table{width:100%;background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.logs-table th{text-align:left;padding:1rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase}
.logs-table td{padding:1rem;border-bottom:1px solid #F3F4F6}
.logs-table tr:hover{background:var(--bg)}
.action-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.action-INSERT{background:#D1FAE5;color:#065F46}
.action-UPDATE{background:#DBEAFE;color:#1E40AF}
.action-DELETE{background:#FEE2E2;color:#991B1B}
.changes-preview{font-size:.85rem;color:var(--cb-gray);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer}
.changes-preview:hover{color:var(--cb-bright-blue)}
</style>

<div class="page-header">
<h1 class="page-title">📋 Log Attività</h1>
</div>

<div class="stats-grid">
<div class="stat-card">
<div class="stat-label">Azioni Oggi</div>
<div class="stat-value"><?= $statsToday ?></div>
</div>
<div class="stat-card">
<div class="stat-label">Azioni Ultima Settimana</div>
<div class="stat-value"><?= $statsWeek ?></div>
</div>
<div class="stat-card">
<div class="stat-label">Totale Visualizzato</div>
<div class="stat-value"><?= count($logs) ?></div>
</div>
</div>

<div class="filters-bar">
<form method="GET">
<div class="filters-grid">
<div class="form-field">
<label>Azione</label>
<select name="action">
<option value="">Tutte</option>
<option value="INSERT" <?= $actionFilter === 'INSERT' ? 'selected' : '' ?>>Creazione</option>
<option value="UPDATE" <?= $actionFilter === 'UPDATE' ? 'selected' : '' ?>>Modifica</option>
<option value="DELETE" <?= $actionFilter === 'DELETE' ? 'selected' : '' ?>>Eliminazione</option>
</select>
</div>
<div class="form-field">
<label>Entità</label>
<select name="table">
<option value="">Tutte</option>
<option value="agencies" <?= $tableFilter === 'agencies' ? 'selected' : '' ?>>Agenzie</option>
<option value="agents" <?= $tableFilter === 'agents' ? 'selected' : '' ?>>Agenti</option>
<option value="tickets" <?= $tableFilter === 'tickets' ? 'selected' : '' ?>>Ticket</option>
<option value="ticket_categories" <?= $tableFilter === 'ticket_categories' ? 'selected' : '' ?>>Categorie Ticket</option>
</select>
</div>
<div class="form-field">
<label>Utente</label>
<input type="text" name="user" value="<?= htmlspecialchars($userFilter) ?>" placeholder="Email utente">
</div>
<div class="form-field">
<label>Data Da</label>
<input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
</div>
<div class="form-field">
<label>Data A</label>
<input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
</div>
<button type="submit" class="btn-filter">🔍 Filtra</button>
<a href="log_activity.php" class="btn-reset">↻ Reset</a>
</div>
</form>
</div>

<?php if (empty($logs)): ?>
<div style="text-align:center;padding:4rem;color:var(--cb-gray)">
<div style="font-size:4rem;margin-bottom:1rem">📋</div>
<h3>Nessuna attività trovata</h3>
<p>Prova a modificare i filtri</p>
</div>
<?php else: ?>
<table class="logs-table">
<thead>
<tr>
<th>DATA/ORA</th>
<th>UTENTE</th>
<th>AZIONE</th>
<th>ENTITÀ</th>
<th>ID</th>
<th>CAMPI MODIFICATI</th>
<th>IP</th>
</tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
<td style="white-space:nowrap;font-size:.85rem">
<?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
</td>
<td style="font-size:.85rem"><?= htmlspecialchars($log['username']) ?></td>
<td>
<span class="action-badge action-<?= $log['action'] ?>">
<?= formatActionType($log['action']) ?>
</span>
</td>
<td><?= formatTableName($log['table_name']) ?></td>
<td style="font-weight:600;color:var(--cb-bright-blue)"><?= $log['record_id'] ?></td>
<td>
<?php if ($log['action'] === 'UPDATE' && $log['fields_count'] > 0): ?>
<span style="font-size:.85rem;color:var(--cb-gray)">
<?= $log['fields_count'] ?> <?= $log['fields_count'] === 1 ? 'campo' : 'campi' ?>
</span>
<?php else: ?>
<span style="color:#ccc">-</span>
<?php endif; ?>
</td>
<td style="font-size:.75rem;color:var(--cb-gray)"><?= htmlspecialchars($log['ip_address'] ?: '-') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<div style="margin-top:2rem;padding:1rem;background:#FEF3C7;border-radius:8px;font-size:.9rem;color:#92400E">
ℹ️ Vengono mostrati gli ultimi 500 record. Usa i filtri per cercare azioni specifiche.
</div>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

// Solo admin possono vedere i log
if ($_SESSION['crm_user']['crm_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = "Log Attività - CRM Coldwell Banker";
$pdo = getDB();

// Filtri
$logType = $_GET['type'] ?? 'all'; // all, access, audit
$userId = $_GET['user_id'] ?? '';
$tableName = $_GET['table'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Query log accessi
$accessLogs = [];
if ($logType === 'all' || $logType === 'access') {
    $sql = "SELECT * FROM login_logs WHERE 1=1";
    $params = [];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    if ($dateFrom) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accessLogs = $stmt->fetchAll();
}

// Query log modifiche
$auditLogs = [];
if ($logType === 'all' || $logType === 'audit') {
    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    if ($tableName) {
        $sql .= " AND table_name = ?";
        $params[] = $tableName;
    }
    if ($dateFrom) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll();
}

// Lista utenti per filtro
$users = $pdo->query("SELECT DISTINCT user_id, username FROM login_logs ORDER BY username")->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field select,.form-field input{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field select:focus,.form-field input:focus{outline:none;border-color:var(--cb-bright-blue)}
.filter-btns{display:flex;gap:.5rem}
.btn-filter{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-filter:hover{background:var(--cb-blue)}
.btn-reset{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:all .2s}
.btn-reset:hover{border-color:var(--cb-gray)}
.log-section{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem;overflow:hidden}
.log-section h3{background:var(--bg);padding:1rem 1.5rem;margin:0;font-size:1rem;font-weight:600;color:var(--cb-midnight)}
.log-entry{padding:1rem 1.5rem;border-bottom:1px solid #F3F4F6;display:flex;gap:1rem;align-items:start}
.log-entry:last-child{border:none}
.log-icon{font-size:1.5rem;opacity:.5}
.log-content{flex:1}
.log-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:.5rem}
.log-user{font-weight:600;color:var(--cb-midnight)}
.log-time{font-size:.85rem;color:var(--cb-gray)}
.log-action{font-size:.95rem;color:var(--cb-gray)}
.log-details{background:var(--bg);padding:.75rem;border-radius:6px;margin-top:.5rem;font-size:.9rem}
.log-change{margin-bottom:.25rem}
.log-change:last-child{margin-bottom:0}
.old-value{color:#991B1B;text-decoration:line-through}
.new-value{color:#065F46;font-weight:500}
.badge{padding:.25rem .5rem;border-radius:6px;font-size:.75rem;font-weight:600}
.badge-login{background:#DBEAFE;color:#1E40AF}
.badge-logout{background:#FEE2E2;color:#991B1B}
.badge-insert{background:#D1FAE5;color:#065F46}
.badge-update{background:#FEF3C7;color:#92400E}
.badge-delete{background:#FEE2E2;color:#991B1B}
.empty-state{text-align:center;padding:3rem 2rem;color:var(--cb-gray)}
</style>

<div class="page-header">
<h1 class="page-title">📋 Log Attività</h1>
</div>

<div class="filters-bar">
<form method="GET">
<div class="filters-grid">
<div class="form-field">
<label>Tipo Log</label>
<select name="type">
<option value="all" <?= $logType === 'all' ? 'selected' : '' ?>>Tutti</option>
<option value="access" <?= $logType === 'access' ? 'selected' : '' ?>>Solo Accessi</option>
<option value="audit" <?= $logType === 'audit' ? 'selected' : '' ?>>Solo Modifiche</option>
</select>
</div>
<div class="form-field">
<label>Utente</label>
<select name="user_id">
<option value="">Tutti gli utenti</option>
<?php foreach($users as $u): ?>
<option value="<?= $u['user_id'] ?>" <?= $userId == $u['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-field">
<label>Tabella</label>
<select name="table">
<option value="">Tutte</option>
<option value="agencies" <?= $tableName === 'agencies' ? 'selected' : '' ?>>Agenzie</option>
<option value="agents" <?= $tableName === 'agents' ? 'selected' : '' ?>>Agenti</option>
<option value="agency_services" <?= $tableName === 'agency_services' ? 'selected' : '' ?>>Servizi</option>
</select>
</div>
<div class="form-field">
<label>Data Da</label>
<input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
</div>
<div class="form-field">
<label>Data A</label>
<input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
</div>
</div>
<div class="filter-btns">
<button type="submit" class="btn-filter">🔍 Filtra</button>
<a href="log_activity.php" class="btn-reset">↻ Reset</a>
</div>
</form>
</div>

<?php if(!empty($accessLogs) && ($logType === 'all' || $logType === 'access')): ?>
<div class="log-section">
<h3>🔐 Log Accessi</h3>
<?php foreach($accessLogs as $log): ?>
<div class="log-entry">
<div class="log-icon"><?= $log['action'] === 'login' ? '🟢' : '🔴' ?></div>
<div class="log-content">
<div class="log-header">
<div>
<span class="log-user"><?= htmlspecialchars($log['username']) ?></span>
<span class="badge badge-<?= $log['action'] ?>"><?= strtoupper($log['action']) ?></span>
</div>
<span class="log-time"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></span>
</div>
<div class="log-action">
IP: <?= htmlspecialchars($log['ip_address']) ?>
<?php if($log['session_duration']): ?>
 • Durata sessione: <?= gmdate('H:i:s', $log['session_duration']) ?>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(!empty($auditLogs) && ($logType === 'all' || $logType === 'audit')): ?>
<div class="log-section">
<h3>✏️ Log Modifiche</h3>
<?php 
// Raggruppa per record_id e created_at per mostrare modifiche multiple insieme
$grouped = [];
foreach($auditLogs as $log) {
    $key = $log['table_name'] . '_' . $log['record_id'] . '_' . $log['created_at'];
    $grouped[$key]['logs'][] = $log;
    $grouped[$key]['info'] = $log;
}

foreach($grouped as $group): 
    $info = $group['info'];
    $logs = $group['logs'];
?>
<div class="log-entry">
<div class="log-icon">✏️</div>
<div class="log-content">
<div class="log-header">
<div>
<span class="log-user"><?= htmlspecialchars($info['username']) ?></span>
<span class="badge badge-<?= strtolower($info['action']) ?>"><?= $info['action'] ?></span>
</div>
<span class="log-time"><?= date('d/m/Y H:i:s', strtotime($info['created_at'])) ?></span>
</div>
<div class="log-action">
<?php
$tableLabels = ['agencies' => 'Agenzia', 'agents' => 'Agente', 'agency_services' => 'Servizio'];
echo $tableLabels[$info['table_name']] ?? $info['table_name'];
?> #<?= $info['record_id'] ?> • IP: <?= htmlspecialchars($info['ip_address']) ?>
</div>
<?php if($info['action'] === 'UPDATE' && count($logs) > 0): ?>
<div class="log-details">
<?php foreach($logs as $change): ?>
<div class="log-change">
<strong><?= formatFieldName($change['field_name']) ?>:</strong>
<span class="old-value"><?= htmlspecialchars($change['old_value'] ?: '(vuoto)') ?></span>
→
<span class="new-value"><?= htmlspecialchars($change['new_value'] ?: '(vuoto)') ?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(empty($accessLogs) && empty($auditLogs)): ?>
<div class="empty-state">
<div style="font-size:3rem;margin-bottom:1rem">📋</div>
<h3>Nessun log trovato</h3>
<p>Prova a modificare i filtri</p>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Dettaglio Agenzia - CRM Coldwell Banker";
$pdo = getDB();

$code = $_GET['code'] ?? '';

if (!$code) {
    header('Location: agenzie.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = :code");
$stmt->execute(['code' => $code]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agents WHERE agency_code = :code ORDER BY status DESC, name ASC");
$stmt->execute(['code' => $code]);
$agents = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.detail-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left{display:flex;align-items:center;gap:1.5rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.agency-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agency-code{color:var(--cb-bright-blue);font-weight:600;font-size:1rem}
.tabs{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.tabs-nav{display:flex;border-bottom:2px solid #E5E7EB;padding:0 1.5rem}
.tab-btn{background:transparent;border:none;padding:1rem 1.5rem;cursor:pointer;font-size:.95rem;font-weight:500;color:var(--cb-gray);border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s}
.tab-btn:hover{color:var(--cb-midnight)}
.tab-btn.active{color:var(--cb-bright-blue);border-bottom-color:var(--cb-bright-blue)}
.tab-content{padding:2rem;display:none}
.tab-content.active{display:block}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem}
.info-card{background:var(--bg);padding:1.5rem;border-radius:10px;border-left:4px solid var(--cb-bright-blue)}
.info-card h3{font-size:.875rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);margin:0 0 .75rem 0;font-weight:600}
.info-card .value{font-size:1.125rem;font-weight:600;color:var(--cb-midnight)}
.agents-table{width:100%;border-collapse:collapse}
.agents-table th{text-align:left;padding:1rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase}
.agents-table td{padding:1rem;border-top:1px solid #F3F4F6}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.inactive{background:#FEE2E2;color:#991B1B}
</style>

<div class="detail-header">
<div class="header-left">
<a href="agenzie.php" class="back-btn">← Torna</a>
<div>
<h1 class="agency-title"><?= htmlspecialchars($agency['name']) ?></h1>
<div class="agency-code"><?= htmlspecialchars($agency['code']) ?></div>
</div>
<span class="status-badge <?= strtolower($agency['status']) ?>"><?= htmlspecialchars($agency['status']) ?></span>
</div>
</div>

<div class="tabs">
<div class="tabs-nav">
<button class="tab-btn active" onclick="switchTab('info')">📊 Info Base</button>
<button class="tab-btn" onclick="switchTab('contrattuale')">📄 Contrattuale</button>
<button class="tab-btn" onclick="switchTab('agenti')">👥 Agenti (<?= count($agents) ?>)</button>
</div>

<div class="tab-content active" id="tab-info">
<div class="info-grid">
<div class="info-card">
<h3>Codice</h3>
<div class="value"><?= htmlspecialchars($agency['code']) ?></div>
</div>
<div class="info-card">
<h3>Tipo</h3>
<div class="value"><?= htmlspecialchars($agency['type']) ?></div>
</div>
<div class="info-card">
<h3>Broker Manager</h3>
<div class="value"><?= htmlspecialchars($agency['broker_manager'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Rappresentante Legale</h3>
<div class="value"><?= htmlspecialchars($agency['legal_representative'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Indirizzo</h3>
<div class="value"><?= htmlspecialchars($agency['address'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Città</h3>
<div class="value"><?= htmlspecialchars($agency['city'] ?: '-') ?>, <?= htmlspecialchars($agency['province'] ?: '') ?></div>
</div>
<div class="info-card">
<h3>CAP</h3>
<div class="value"><?= htmlspecialchars($agency['zip_code'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Email</h3>
<div class="value" style="font-size:1rem"><?= htmlspecialchars($agency['email'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Telefono</h3>
<div class="value"><?= htmlspecialchars($agency['phone'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>PEC</h3>
<div class="value" style="font-size:0.9rem"><?= htmlspecialchars($agency['pec'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>P.IVA</h3>
<div class="value"><?= htmlspecialchars($agency['vat_number'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Codice Fiscale</h3>
<div class="value"><?= htmlspecialchars($agency['tax_code'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Codice SDI</h3>
<div class="value"><?= htmlspecialchars($agency['sdi_code'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Ragione Sociale</h3>
<div class="value"><?= htmlspecialchars($agency['company_name'] ?: '-') ?></div>
</div>
</div>
</div>

<div class="tab-content" id="tab-contrattuale">
<div class="info-grid">
<div class="info-card">
<h3>Data Attivazione</h3>
<div class="value"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></div>
</div>
<div class="info-card">
<h3>Scadenza Contratto</h3>
<div class="value"><?= $agency['contract_expiry'] ? date('d/m/Y', strtotime($agency['contract_expiry'])) : '-' ?></div>
</div>
<div class="info-card">
<h3>Durata (anni)</h3>
<div class="value"><?= htmlspecialchars($agency['contract_duration_years'] ?: '-') ?></div>
</div>
<div class="info-card">
<h3>Tech Fee</h3>
<div class="value"><?= $agency['tech_fee'] ? '€ ' . number_format($agency['tech_fee'], 2, ',', '.') : '-' ?></div>
</div>
<div class="info-card">
<h3>Data Chiusura</h3>
<div class="value"><?= $agency['closed_date'] ? date('d/m/Y', strtotime($agency['closed_date'])) : '-' ?></div>
</div>
</div>
</div>

<div class="tab-content" id="tab-agenti">
<table class="agents-table">
<thead>
<tr>
<th>Nome</th>
<th>Email</th>
<th>Telefono</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php if(empty($agents)): ?>
<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--cb-gray)">Nessun agente trovato</td></tr>
<?php else: ?>
<?php foreach($agents as $agent): ?>
<tr>
<td><?= htmlspecialchars($agent['name']) ?></td>
<td><?= htmlspecialchars($agent['email_corporate'] ?: $agent['email_personal'] ?: '-') ?></td>
<td><?= htmlspecialchars($agent['phone'] ?: '-') ?></td>
<td><span class="status-badge <?= strtolower($agent['status']) ?>"><?= htmlspecialchars($agent['status']) ?></span></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<script>
function switchTab(tabName){
document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(content=>content.classList.remove('active'));
event.target.classList.add('active');
document.getElementById('tab-'+tabName).classList.add('active');
}
</script>

<?php require_once 'footer.php'; ?>

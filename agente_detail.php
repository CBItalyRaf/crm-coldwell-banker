<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Dettaglio Agente - CRM Coldwell Banker";
$pdo = getDB();

$id = $_GET['id'] ?? '';

if (!$id) {
    header('Location: agenti.php');
    exit;
}

$stmt = $pdo->prepare("SELECT a.*, ag.name as agency_name, ag.code as agency_code 
                       FROM agents a 
                       LEFT JOIN agencies ag ON a.agency_id = ag.id 
                       WHERE a.id = :id");
$stmt->execute(['id' => $id]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: agenti.php');
    exit;
}

require_once 'header.php';
?>

<style>
.detail-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left{display:flex;align-items:center;gap:1.5rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.agent-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agent-agency{color:var(--cb-gray);font-size:.95rem;margin-top:.25rem}
.edit-btn{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;font-size:.95rem;transition:background .2s}
.edit-btn:hover{background:var(--cb-blue)}
.edit-btn:disabled{background:#D1D5DB;cursor:not-allowed}
.info-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid var(--cb-bright-blue);padding:2rem}
.info-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid #F3F4F6}
.info-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.info-section h3{font-size:1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.info-field{display:flex;flex-direction:column;gap:.25rem}
.info-field label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);font-weight:600}
.info-field .value{font-size:1rem;color:var(--cb-midnight);font-weight:500}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.inactive{background:#FEE2E2;color:#991B1B}
</style>

<div class="detail-header">
<div class="header-left">
<a href="agenti.php" class="back-btn">← Torna</a>
<div>
<h1 class="agent-title"><?= htmlspecialchars($agent['full_name']) ?></h1>
<?php if($agent['agency_name']): ?>
<div class="agent-agency"><?= htmlspecialchars($agent['agency_name']) ?> (<?= htmlspecialchars($agent['agency_code']) ?>)</div>
<?php endif; ?>
</div>
<span class="status-badge <?= strtolower($agent['status']) ?>"><?= htmlspecialchars($agent['status']) ?></span>
</div>
<?php if(in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])): ?>
<button class="edit-btn" onclick="window.location.href='agente_edit.php?id=<?= $agent['id'] ?>'">🔓 Modifica</button>
<?php else: ?>
<button class="edit-btn" disabled>🔒 Sola Lettura</button>
<?php endif; ?>
</div>

<div class="info-card">

<div class="info-section">
<h3>Anagrafica</h3>
<div class="info-grid">
<div class="info-field">
<label>Nome</label>
<div class="value"><?= htmlspecialchars($agent['first_name'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Cognome</label>
<div class="value"><?= htmlspecialchars($agent['last_name'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Nome Completo</label>
<div class="value"><?= htmlspecialchars($agent['full_name']) ?></div>
</div>
<div class="info-field" style="grid-column:1/-1">
<label>Ruoli</label>
<div class="value">
<?php 
$rolesJson = $agent['role'];
$roles = $rolesJson ? json_decode($rolesJson, true) : [];

if (!empty($roles)):
    $roleBadges = [
        'broker' => ['label' => 'Broker', 'color' => '#3B82F6'],
        'broker_manager' => ['label' => 'Broker Manager', 'color' => '#10B981'],
        'legale_rappresentante' => ['label' => 'Legale Rappresentante', 'color' => '#F59E0B'],
        'preposto' => ['label' => 'Preposto', 'color' => '#F97316'],
        'global_luxury' => ['label' => 'Global Luxury', 'color' => '#8B5CF6']
    ];
    
    foreach ($roles as $role):
        $badge = $roleBadges[$role] ?? ['label' => ucfirst($role), 'color' => '#6B7280'];
?>
    <span style="display:inline-block;padding:.5rem 1rem;border-radius:8px;font-size:.875rem;font-weight:600;background:<?= $badge['color'] ?>;color:white;margin-right:.5rem;margin-bottom:.5rem"><?= $badge['label'] ?></span>
<?php 
    endforeach;
else:
?>
    <span style="color:var(--cb-gray)">Nessun ruolo assegnato</span>
<?php endif; ?>
</div>
</div>
</div>
</div>

<div class="info-section">
<h3>Contatti</h3>
<div class="info-grid">
<div class="info-field">
<label>Cellulare</label>
<div class="value"><?= htmlspecialchars($agent['mobile'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Email Aziendale</label>
<div class="value"><?= htmlspecialchars($agent['email_corporate'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Email Personale</label>
<div class="value"><?= htmlspecialchars($agent['email_personal'] ?: '-') ?></div>
</div>
</div>
</div>

<div class="info-section">
<h3>Microsoft 365</h3>
<div class="info-grid">
<div class="info-field">
<label>Piano M365</label>
<div class="value"><?= htmlspecialchars($agent['m365_plan'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Data Attivazione Email</label>
<div class="value"><?= $agent['email_activation_date'] ? date('d/m/Y', strtotime($agent['email_activation_date'])) : '-' ?></div>
</div>
<div class="info-field">
<label>Data Scadenza Email</label>
<div class="value"><?= $agent['email_expiry_date'] ? date('d/m/Y', strtotime($agent['email_expiry_date'])) : '-' ?></div>
</div>
<div class="info-field">
<label>Data Disabilitazione Email</label>
<div class="value"><?= $agent['email_disabled_date'] ? date('d/m/Y', strtotime($agent['email_disabled_date'])) : '-' ?></div>
</div>
</div>
</div>

<?php if($agent['notes']): ?>
<div class="info-section">
<h3>Note</h3>
<div class="value"><?= nl2br(htmlspecialchars($agent['notes'])) ?></div>
</div>
<?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>

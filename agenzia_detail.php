<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$stmt = $pdo->prepare("SELECT * FROM agents WHERE agency_id = :agency_id ORDER BY status DESC, full_name ASC");
$stmt->execute(['agency_id' => $agency['id']]);
$allAgents = $stmt->fetchAll();

// Separa Active e Inactive
$activeAgents = array_filter($allAgents, fn($a) => $a['status'] === 'Active');
$inactiveAgents = array_filter($allAgents, fn($a) => $a['status'] !== 'Active');

// Carica TUTTI i servizi dal master con info su attivazione agenzia E contratto
$stmt = $pdo->prepare("
    SELECT 
        sm.id,
        sm.service_name,
        sm.is_cb_suite,
        sm.display_order,
        COALESCE(ags.is_active, 0) as is_active_services,
        acs.is_mandatory,
        CASE 
            WHEN acs.is_mandatory = 1 THEN 1
            WHEN ags.is_active = 1 THEN 1
            ELSE 0
        END as is_active,
        ags.activation_date,
        ags.expiration_date,
        ags.renewal_required,
        ags.invoice_reference,
        ags.notes
    FROM services_master sm
    LEFT JOIN agency_services ags ON sm.service_name = ags.service_name AND ags.agency_id = :agency_id1
    LEFT JOIN agency_contract_services acs ON sm.id = acs.service_id AND acs.agency_id = :agency_id2
    WHERE sm.is_active = 1
    ORDER BY sm.display_order ASC, sm.service_name ASC
");
$stmt->execute(['agency_id1' => $agency['id'], 'agency_id2' => $agency['id']]);
$allServicesData = $stmt->fetchAll();

// Separa CB Suite e servizi standalone
$cbSuiteServices = array_filter($allServicesData, fn($s) => $s['is_cb_suite'] == 1);
$standaloneServices = array_filter($allServicesData, fn($s) => $s['is_cb_suite'] == 0);

require_once 'header.php';
?>

<style>
.detail-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left{display:flex;align-items:center;gap:1.5rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.agency-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agency-code{color:var(--cb-bright-blue);font-weight:600;font-size:1rem}
.edit-btn{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;font-size:.95rem;transition:background .2s}
.edit-btn:hover{background:var(--cb-blue)}
.edit-btn:disabled{background:#D1D5DB;cursor:not-allowed}
.tabs{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid var(--cb-bright-blue)}
.tabs.status-closed{border-left-color:#9CA3AF}
.tabs.status-opening{border-left-color:#F59E0B}
.tabs-nav{display:flex;border-bottom:2px solid #E5E7EB;padding:0 1.5rem}
.tab-btn{background:transparent;border:none;padding:1rem 1.5rem;cursor:pointer;font-size:.95rem;font-weight:500;color:var(--cb-gray);border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s}
.tab-btn:hover{color:var(--cb-midnight)}
.tab-btn.active{color:var(--cb-bright-blue);border-bottom-color:var(--cb-bright-blue)}
.tab-content{padding:2rem;display:none}
.tab-content.active{display:block}
.info-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid #F3F4F6}
.info-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.info-section h3{font-size:1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.info-field{display:flex;flex-direction:column;gap:.25rem}
.info-field label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);font-weight:600}
.info-field .value{font-size:1rem;color:var(--cb-midnight);font-weight:500}
.agents-table{width:100%;border-collapse:collapse}
.agents-table th{text-align:left;padding:1rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase}
.agents-table td{padding:1rem;border-top:1px solid #F3F4F6}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.inactive{background:#FEE2E2;color:#991B1B}
.service-box{background:var(--bg);border-left:4px solid var(--cb-bright-blue);padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center}
.service-name{font-weight:600;color:var(--cb-midnight)}
.service-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.service-badge.attivo{background:#D1FAE5;color:#065F46}
.service-badge.non-attivo{background:#FEE2E2;color:#991B1B}
</style>

<div class="detail-header">
<div class="header-left">
<a href="agenzie.php" class="back-btn">← Torna</a>
<div>
<h1 class="agency-title"><?= htmlspecialchars($agency['name']) ?></h1>
<div class="agency-code"><?= htmlspecialchars($agency['code']) ?></div>
</div>
<div style="display:flex;gap:.5rem;align-items:center">
<span class="status-badge" style="background:#E5E7EB;color:var(--cb-gray)"><?= htmlspecialchars($agency['type']) ?></span>
<span class="status-badge <?= strtolower($agency['status']) ?>"><?= htmlspecialchars($agency['status']) ?></span>
</div>
</div>
<div style="display:flex;gap:.75rem">
<?php if($agency['status'] === 'Opening' && in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])): ?>
<a href="onboarding_start.php?agency_id=<?= $agency['id'] ?>" class="edit-btn" style="background:#10B981;text-decoration:none">🚀 Avvia Onboarding</a>
<?php endif; ?>
<?php if($agency['status'] === 'In Onboarding'): ?>
<a href="onboarding_detail.php?agency_id=<?= $agency['id'] ?>" class="edit-btn" style="background:#10B981;text-decoration:none">📋 Vedi Onboarding</a>
<?php endif; ?>
<?php if(in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])): ?>
<button class="edit-btn" id="editBtn">🔓 Modifica</button>
<?php else: ?>
<button class="edit-btn" disabled>🔒 Sola Lettura</button>
<?php endif; ?>
</div>
</div>

<div class="tabs status-<?= strtolower($agency['status']) ?>">
<div class="tabs-nav">
<button class="tab-btn active" onclick="switchTab('info')">📊 Info Agenzia</button>
<button class="tab-btn" onclick="switchTab('contrattuale')">📄 Contrattuale</button>
<button class="tab-btn" onclick="switchTab('servizi')">⚙️ Servizi (<?= count(array_filter($allServicesData, fn($s) => $s['is_active'] == 1)) ?>)</button>
<button class="tab-btn" onclick="switchTab('agenti')">👥 Agenti (<?= count($activeAgents) ?>)</button>
</div>

<div class="tab-content active" id="tab-info">
<div class="info-section">
<h3>Anagrafica</h3>
<div class="info-grid">
<div class="info-field">
<label>Tipo Agenzia</label>
<div class="value"><?= htmlspecialchars($agency['type']) ?></div>
</div>
<div class="info-field">
<label>Broker Manager</label>
<div class="value"><?= htmlspecialchars($agency['broker_manager'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Cellulare Broker</label>
<div class="value"><?= htmlspecialchars($agency['broker_mobile'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Rappresentante Legale</label>
<div class="value"><?= htmlspecialchars($agency['legal_representative'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Ragione Sociale</label>
<div class="value"><?= htmlspecialchars($agency['company_name'] ?: '-') ?></div>
</div>
</div>
</div>

<div class="info-section">
<h3>Sede</h3>
<div class="info-grid">
<div class="info-field" style="grid-column:1/-1">
<label>Indirizzo</label>
<div class="value"><?= htmlspecialchars($agency['address'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Città</label>
<div class="value"><?= htmlspecialchars($agency['city'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Provincia</label>
<div class="value"><?= htmlspecialchars($agency['province'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>CAP</label>
<div class="value"><?= htmlspecialchars($agency['zip_code'] ?: '-') ?></div>
</div>
</div>
</div>

<div class="info-section">
<h3>Contatti</h3>
<div class="info-grid">
<div class="info-field">
<label>Email</label>
<div class="value"><?= htmlspecialchars($agency['email'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Telefono</label>
<div class="value"><?= htmlspecialchars($agency['phone'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>PEC</label>
<div class="value" style="font-size:.85rem"><?= htmlspecialchars($agency['pec'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Sito Web</label>
<div class="value"><?= htmlspecialchars($agency['website'] ?: '-') ?></div>
</div>
</div>
</div>

<div class="info-section">
<h3>Dati Fiscali</h3>
<div class="info-grid">
<div class="info-field">
<label>Partita IVA</label>
<div class="value"><?= htmlspecialchars($agency['vat_number'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Codice Fiscale</label>
<div class="value"><?= htmlspecialchars($agency['tax_code'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>Codice SDI</label>
<div class="value"><?= htmlspecialchars($agency['sdi_code'] ?: '-') ?></div>
</div>
<div class="info-field">
<label>REA</label>
<div class="value"><?= htmlspecialchars($agency['rea'] ?: '-') ?></div>
</div>
</div>
</div>
</div>

<div class="tab-content" id="tab-contrattuale">

<div class="info-section">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
<h3 style="margin:0">Date Contrattuali</h3>
<a href="contratto_edit.php?code=<?= urlencode($agency['code']) ?>" class="edit-btn" style="text-decoration:none;font-size:.9rem;padding:.5rem 1rem">✏️ Modifica Contratto</a>
</div>
<div class="info-grid">
<div class="info-field">
<label>Data Attivazione</label>
<div class="value"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : '-' ?></div>
</div>
<div class="info-field">
<label>Scadenza Contratto</label>
<div class="value"><?= $agency['contract_expiry'] ? date('d/m/Y', strtotime($agency['contract_expiry'])) : '-' ?></div>
</div>
<div class="info-field">
<label>Durata Contratto</label>
<div class="value"><?= $agency['contract_duration_years'] ? $agency['contract_duration_years'] . ' anni' : '-' ?></div>
</div>
<div class="info-field">
<label>Data Chiusura</label>
<div class="value"><?= $agency['closed_date'] ? date('d/m/Y', strtotime($agency['closed_date'])) : '-' ?></div>
</div>
</div>
</div>

<div class="info-section">
<h3>Condizioni Economiche</h3>
<div class="info-grid">
<div class="info-field">
<label>Tech Fee (Forfait)</label>
<div class="value" style="font-size:1.5rem;font-weight:600;color:var(--cb-bright-blue)">
<?= $agency['tech_fee'] ? '€ ' . number_format($agency['tech_fee'], 2, ',', '.') : '€ 0,00' ?>
</div>
</div>
</div>
</div>

<?php
// Carica servizi obbligatori e facoltativi dal contratto
$stmtContract = $pdo->prepare("
    SELECT acs.*, sm.service_name, sm.default_price 
    FROM agency_contract_services acs
    JOIN services_master sm ON acs.service_id = sm.id
    WHERE acs.agency_id = :agency_id
    ORDER BY acs.is_mandatory DESC, sm.service_name ASC
");
$stmtContract->execute(['agency_id' => $agency['id']]);
$contractServices = $stmtContract->fetchAll();

$mandatoryServices = array_filter($contractServices, fn($s) => $s['is_mandatory'] == 1);
$optionalServices = array_filter($contractServices, fn($s) => $s['is_mandatory'] == 0);

$totalOptional = array_reduce($optionalServices, function($sum, $s) {
    return $sum + ($s['custom_price'] ?? $s['default_price']);
}, 0);
?>

<?php if(!empty($mandatoryServices)): ?>
<div class="info-section">
<h3>Servizi Obbligatori <span style="font-size:.85rem;font-weight:400;color:var(--cb-gray)">(coperti dalla Tech Fee)</span></h3>
<div style="display:grid;gap:.75rem">
<?php foreach($mandatoryServices as $svc): ?>
<div style="background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
<div>
<div style="font-weight:600;color:#065F46"><?= htmlspecialchars($svc['service_name']) ?></div>
<?php if($svc['notes']): ?>
<div style="font-size:.85rem;color:#059669;margin-top:.25rem"><?= htmlspecialchars($svc['notes']) ?></div>
<?php endif; ?>
</div>
<div style="background:#10B981;color:white;padding:.25rem .75rem;border-radius:6px;font-size:.85rem;font-weight:600">OBBLIGATORIO</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php if(!empty($optionalServices)): ?>
<div class="info-section">
<h3>Servizi Facoltativi Attivati <span style="font-size:.85rem;font-weight:400;color:var(--cb-gray)">(costi aggiuntivi)</span></h3>
<div style="display:grid;gap:.75rem">
<?php foreach($optionalServices as $svc): 
$price = $svc['custom_price'] ?? $svc['default_price'];
?>
<div style="background:#DBEAFE;border-left:4px solid #3B82F6;padding:1rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
<div style="flex:1">
<div style="font-weight:600;color:#1E40AF"><?= htmlspecialchars($svc['service_name']) ?></div>
<?php if($svc['notes']): ?>
<div style="font-size:.85rem;color:#2563EB;margin-top:.25rem"><?= htmlspecialchars($svc['notes']) ?></div>
<?php endif; ?>
</div>
<div style="font-size:1.25rem;font-weight:600;color:#1E40AF">€ <?= number_format($price, 2, ',', '.') ?></div>
</div>
<?php endforeach; ?>
</div>

<div style="background:var(--bg);padding:1rem;border-radius:8px;margin-top:1rem">
<div style="display:flex;justify-content:space-between;align-items:center">
<div style="font-weight:600;color:var(--cb-midnight)">Totale Servizi Facoltativi:</div>
<div style="font-size:1.5rem;font-weight:600;color:#3B82F6">€ <?= number_format($totalOptional, 2, ',', '.') ?></div>
</div>
</div>
</div>
<?php endif; ?>

<?php if(empty($mandatoryServices) && empty($optionalServices)): ?>
<div style="text-align:center;padding:3rem;color:var(--cb-gray)">
<div style="font-size:3rem;margin-bottom:1rem;opacity:.5">📋</div>
<p>Nessun servizio configurato nel contratto</p>
<a href="contratto_edit.php?code=<?= urlencode($agency['code']) ?>" style="display:inline-block;margin-top:1rem;background:var(--cb-bright-blue);color:white;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none">Configura Contratto</a>
</div>
<?php endif; ?>

<div class="info-section" style="background:#F0F9FF;border:2px solid #3B82F6;border-radius:12px;padding:1.5rem">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
<div>
<div style="font-size:.875rem;text-transform:uppercase;color:#1E40AF;margin-bottom:.5rem;font-weight:600">Costo Totale Mensile</div>
<div style="font-size:2rem;font-weight:700;color:#1E40AF">
€ <?= number_format(($agency['tech_fee'] ?? 0) + $totalOptional, 2, ',', '.') ?>
</div>
<div style="font-size:.85rem;color:#3B82F6;margin-top:.5rem">
Tech Fee: €<?= number_format($agency['tech_fee'] ?? 0, 2, ',', '.') ?> 
<?php if($totalOptional > 0): ?>
+ Facoltativi: €<?= number_format($totalOptional, 2, ',', '.') ?>
<?php endif; ?>
</div>
</div>
</div>
</div>

</div>

<div class="tab-content" id="tab-servizi">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
<h3 style="font-size:1.25rem;font-weight:600;margin:0">Servizi</h3>
<a href="servizi_edit.php?code=<?= urlencode($agency['code']) ?>" class="edit-btn" style="text-decoration:none;font-size:.9rem;padding:.5rem 1rem">✏️ Gestisci Servizi</a>
</div>

<?php if(!empty($cbSuiteServices)): ?>
<!-- CB Suite Container -->
<div class="service-box" style="cursor:pointer;margin-bottom:1rem;background:linear-gradient(135deg, #012169 0%, #1F69FF 100%);color:white;border-radius:12px" onclick="toggleCBSuite()">
<div style="display:flex;justify-content:space-between;align-items:center;padding:1.5rem">
<div>
<div style="font-size:1.5rem;font-weight:700;display:flex;align-items:center;gap:.75rem">
📦 CB Suite
<span style="background:rgba(255,255,255,.2);padding:.25rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600">
<?= count($cbSuiteServices) ?> servizi
</span>
</div>
<div style="font-size:.9rem;opacity:.9;margin-top:.5rem">Pacchetto servizi integrati</div>
</div>
<span id="arrow-cbsuite" style="font-size:1.5rem;transition:transform .2s">▼</span>
</div>
</div>

<div id="cbsuite-details" style="display:none;margin-bottom:2rem;padding-left:2rem">
<?php foreach($cbSuiteServices as $i => $service): ?>
<div class="service-box" style="cursor:pointer;margin-bottom:.75rem;border-radius:8px" onclick="toggleService(<?= $i ?>)">
<div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
<div style="display:flex;align-items:center;gap:1rem">
<span class="service-badge <?= $service['is_active'] ? 'attivo' : 'non-attivo' ?>">
<?= $service['is_active'] ? 'ATTIVO' : 'NON ATTIVO' ?>
</span>
<span id="arrow-<?= $i ?>" style="font-size:1.2rem;color:var(--cb-gray);transition:transform .2s">▼</span>
</div>
</div>

<div id="service-details-<?= $i ?>" style="display:none;background:white;padding:1.5rem;border:1px solid #E5E7EB;border-top:none;border-radius:0 0 8px 8px;margin-bottom:.75rem;margin-left:2rem">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem">
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Data Attivazione</div>
<div style="font-weight:500"><?= $service['activation_date'] ? date('d/m/Y', strtotime($service['activation_date'])) : '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Data Scadenza</div>
<div style="font-weight:500"><?= $service['expiration_date'] ? date('d/m/Y', strtotime($service['expiration_date'])) : '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Rinnovo Richiesto</div>
<div style="font-weight:500"><?= $service['renewal_required'] ?: '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Riferimento Fattura</div>
<div style="font-weight:500"><?= htmlspecialchars($service['invoice_reference'] ?: '-') ?></div>
</div>
</div>
<?php if($service['notes']): ?>
<div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #F3F4F6">
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.5rem">Note</div>
<div style="font-weight:500"><?= nl2br(htmlspecialchars($service['notes'])) ?></div>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(!empty($standaloneServices)): ?>
<!-- Servizi Standalone -->
<div style="margin-top:2rem">
<h4 style="font-size:1.1rem;font-weight:600;margin-bottom:1rem;color:var(--cb-midnight)">Altri Servizi</h4>
<?php foreach($standaloneServices as $i => $service): 
$idx = 'standalone_' . $i;
?>
<div class="service-box" style="cursor:pointer;margin-bottom:.75rem;border-radius:8px" onclick="toggleService('<?= $idx ?>')">
<div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
<div style="display:flex;align-items:center;gap:1rem">
<span class="service-badge <?= $service['is_active'] ? 'attivo' : 'non-attivo' ?>">
<?= $service['is_active'] ? 'ATTIVO' : 'NON ATTIVO' ?>
</span>
<span id="arrow-<?= $idx ?>" style="font-size:1.2rem;color:var(--cb-gray);transition:transform .2s">▼</span>
</div>
</div>

<div id="service-details-<?= $idx ?>" style="display:none;background:white;padding:1.5rem;border:1px solid #E5E7EB;border-top:none;border-radius:0 0 8px 8px;margin-bottom:.75rem">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem">
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Data Attivazione</div>
<div style="font-weight:500"><?= $service['activation_date'] ? date('d/m/Y', strtotime($service['activation_date'])) : '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Data Scadenza</div>
<div style="font-weight:500"><?= $service['expiration_date'] ? date('d/m/Y', strtotime($service['expiration_date'])) : '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Rinnovo Richiesto</div>
<div style="font-weight:500"><?= $service['renewal_required'] ?: '-' ?></div>
</div>
<div>
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.25rem">Riferimento Fattura</div>
<div style="font-weight:500"><?= htmlspecialchars($service['invoice_reference'] ?: '-') ?></div>
</div>
</div>
<?php if($service['notes']): ?>
<div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #F3F4F6">
<div style="font-size:.75rem;text-transform:uppercase;color:var(--cb-gray);margin-bottom:.5rem">Note</div>
<div style="font-weight:500"><?= nl2br(htmlspecialchars($service['notes'])) ?></div>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(empty($cbSuiteServices) && empty($standaloneServices)): ?>
<div style="text-align:center;padding:3rem;color:var(--cb-gray)">
<div style="font-size:3rem;margin-bottom:1rem;opacity:.5">⚙️</div>
<p>Nessun servizio configurato</p>
<a href="servizi_edit.php?code=<?= urlencode($agency['code']) ?>" style="display:inline-block;margin-top:1rem;background:var(--cb-bright-blue);color:white;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none">Gestisci Servizi</a>
</div>
<?php endif; ?>

</div>

<div class="tab-content" id="tab-agenti">
<div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center">
<h3 style="margin:0;font-size:1.1rem;font-weight:600">Agenti Agenzia</h3>
<?php if(count($inactiveAgents) > 0): ?>
<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
<input type="checkbox" id="showInactiveAgents" onchange="toggleInactiveAgents()">
<span style="font-size:.9rem;color:var(--cb-gray)">Mostra inattivi (<?= count($inactiveAgents) ?>)</span>
</label>
<?php endif; ?>
</div>
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
<?php if(empty($activeAgents) && empty($inactiveAgents)): ?>
<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--cb-gray)">Nessun agente trovato</td></tr>
<?php else: ?>
<?php foreach($activeAgents as $agent): ?>
<tr onclick="window.location.href='agente_detail.php?id=<?= $agent['id'] ?>'" style="cursor:pointer">
<td><?= htmlspecialchars($agent['full_name']) ?></td>
<td><?= htmlspecialchars($agent['email_corporate'] ?: $agent['email_personal'] ?: '-') ?></td>
<td><?= htmlspecialchars($agent['mobile'] ?: '-') ?></td>
<td><span class="status-badge active">Active</span></td>
</tr>
<?php endforeach; ?>
<?php foreach($inactiveAgents as $agent): ?>
<tr class="inactive-agent" style="display:none;cursor:pointer" onclick="window.location.href='agente_detail.php?id=<?= $agent['id'] ?>'">
<td><?= htmlspecialchars($agent['full_name']) ?></td>
<td><?= htmlspecialchars($agent['email_corporate'] ?: $agent['email_personal'] ?: '-') ?></td>
<td><?= htmlspecialchars($agent['mobile'] ?: '-') ?></td>
<td><span class="status-badge inactive"><?= htmlspecialchars($agent['status']) ?></span></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<script>
let currentTab = 'info';

function switchTab(tabName){
currentTab = tabName;
document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(content=>content.classList.remove('active'));
event.target.classList.add('active');
document.getElementById('tab-'+tabName).classList.add('active');
}

function toggleService(index){
const details = document.getElementById('service-details-' + index);
const arrow = document.getElementById('arrow-' + index);
if(details.style.display === 'none'){
details.style.display = 'block';
arrow.style.transform = 'rotate(180deg)';
} else {
details.style.display = 'none';
arrow.style.transform = 'rotate(0deg)';
}
}

function toggleCBSuite(){
const details = document.getElementById('cbsuite-details');
const arrow = document.getElementById('arrow-cbsuite');
if(details.style.display === 'none'){
details.style.display = 'block';
arrow.style.transform = 'rotate(180deg)';
} else {
details.style.display = 'none';
arrow.style.transform = 'rotate(0deg)';
}
}

function toggleInactiveAgents(){
const show = document.getElementById('showInactiveAgents').checked;
document.querySelectorAll('.inactive-agent').forEach(row => {
row.style.display = show ? '' : 'none';
});
}

// Apri tab da hash URL
window.addEventListener('DOMContentLoaded', function(){
const hash = window.location.hash;
if(hash && hash.startsWith('#tab-')){
const tabName = hash.replace('#tab-', '');
currentTab = tabName;
document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(content=>content.classList.remove('active'));
const targetBtn = document.querySelector(`.tab-btn[onclick*="'${tabName}'"]`);
const targetContent = document.getElementById('tab-'+tabName);
if(targetBtn && targetContent){
targetBtn.classList.add('active');
targetContent.classList.add('active');
}
}
});

const editBtn = document.getElementById('editBtn');
if(editBtn) {
editBtn.addEventListener('click', function(){
window.location.href = 'agenzia_edit.php?code=<?= urlencode($agency['code']) ?>&return_tab=' + currentTab;
});
}
</script>

<?php require_once 'footer.php'; ?>

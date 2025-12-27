<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();
$code = $_GET['code'] ?? '';

if (!$code) {
    header('Location: agenzie.php');
    exit;
}

// Dati agenzia
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = ?");
$stmt->execute([$code]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

// Agenti dell'agenzia
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email_personal, mobile, role, status FROM agents WHERE agency_id = ? ORDER BY last_name ASC");
$stmt->execute([$agency['id']]);
$agents = $stmt->fetchAll();

$user = $_SESSION['crm_user'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($agency['name']) ?> - CRM Coldwell Banker</title>
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
.page-header{background:white;border-radius:12px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left-content{display:flex;align-items:center;gap:1rem}
.btn-back{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.btn-back:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.page-title-content h1{font-size:1.5rem;font-weight:600;margin-bottom:.25rem}
.agency-meta{display:flex;gap:1rem;align-items:center;font-size:.875rem;color:var(--cb-gray)}
.status-badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.status-badge.active{background:#D1FAE5;color:#065F46}
.status-badge.closed{background:#FEE2E2;color:#991B1B}
.status-badge.opening{background:#FEF3C7;color:#92400E}
.btn-edit{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem}
.btn-edit:hover{background:var(--cb-blue)}
.tabs-container{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden}
.tabs-nav{display:flex;border-bottom:2px solid #F3F4F6;overflow-x:auto}
.tab-button{background:transparent;border:none;padding:1rem 1.5rem;cursor:pointer;font-size:.95rem;font-weight:500;color:var(--cb-gray);transition:all .2s;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap}
.tab-button:hover{color:var(--cb-bright-blue)}
.tab-button.active{color:var(--cb-bright-blue);border-bottom-color:var(--cb-bright-blue)}
.tab-content{padding:2rem;display:none}
.tab-content.active{display:block;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem}
.info-item{padding:1rem;background:var(--bg);border-radius:8px}
.info-label{font-size:.875rem;color:var(--cb-gray);margin-bottom:.5rem;font-weight:500}
.info-value{font-size:1rem;color:var(--cb-midnight);font-weight:500}
.info-value.empty{color:var(--cb-gray);font-style:italic}
.section-title{font-size:1.125rem;font-weight:600;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid #F3F4F6}
.services-list{display:grid;gap:1rem}
.service-item{background:var(--bg);padding:1.25rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center;gap:1rem}
.service-info h4{font-size:1rem;font-weight:600;margin-bottom:.25rem}
.service-dates{font-size:.875rem;color:var(--cb-gray)}
.service-toggle{position:relative;width:48px;height:24px;background:#E5E7EB;border-radius:12px;cursor:pointer;transition:background .3s}
.service-toggle.active{background:var(--success)}
.service-toggle::after{content:'';position:absolute;width:20px;height:20px;background:white;border-radius:50%;top:2px;left:2px;transition:left .3s}
.service-toggle.active::after{left:26px}
.agents-table{width:100%;border-collapse:collapse}
.agents-table th{text-align:left;padding:1rem;background:var(--bg);font-size:.875rem;font-weight:600;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em}
.agents-table td{padding:1rem;border-bottom:1px solid #F3F4F6}
.agents-table tr:last-child td{border-bottom:none}
.agents-table tr:hover{background:var(--bg)}
.agent-name{font-weight:600;color:var(--cb-blue);cursor:pointer}
.agent-name:hover{text-decoration:underline}
.btn-add-agent{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem;margin-bottom:1.5rem}
.btn-add-agent:hover{background:var(--cb-blue)}
.empty-agents{text-align:center;padding:3rem;color:var(--cb-gray)}
@media(max-width:768px){
.hamburger{display:block}
.header-left{gap:1rem}
.main-nav,.user-menu{display:none}
.container{padding:1rem}
.page-header{flex-direction:column;align-items:flex-start}
.tabs-nav{flex-wrap:nowrap;overflow-x:auto}
.tab-content{padding:1rem}
.info-grid{grid-template-columns:1fr}
.agents-table{font-size:.875rem}
.agents-table th,.agents-table td{padding:.75rem .5rem}
}
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
<div class="page-header">
<div class="header-left-content">
<a href="agenzie.php" class="btn-back">← Torna</a>
<div class="page-title-content">
<h1><?= htmlspecialchars($agency['name']) ?></h1>
<div class="agency-meta">
<span><?= htmlspecialchars($agency['code']) ?></span>
<span class="status-badge <?= strtolower($agency['status']) ?>"><?= htmlspecialchars($agency['status']) ?></span>
</div>
</div>
</div>
<a href="agenzia_edit.php?code=<?= urlencode($agency['code']) ?>" class="btn-edit">✏️ Modifica</a>
</div>
<div class="tabs-container">
<div class="tabs-nav">
<button class="tab-button active" onclick="switchTab('info')">📊 Info Base</button>
<button class="tab-button" onclick="switchTab('contract')">📝 Contrattuale</button>
<button class="tab-button" onclick="switchTab('services')">⚙️ Servizi</button>
<button class="tab-button" onclick="switchTab('agents')">👥 Agenti (<?= count($agents) ?>)</button>
</div>
<div class="tab-content active" id="tab-info">
<h2 class="section-title">Informazioni Generali</h2>
<div class="info-grid">
<div class="info-item">
<div class="info-label">Codice Agenzia</div>
<div class="info-value"><?= htmlspecialchars($agency['code']) ?></div>
</div>
<div class="info-item">
<div class="info-label">Nome Agenzia</div>
<div class="info-value"><?= htmlspecialchars($agency['name']) ?></div>
</div>
<div class="info-item">
<div class="info-label">Tipo</div>
<div class="info-value"><?= htmlspecialchars($agency['type'] ?: 'Non specificato') ?></div>
</div>
<div class="info-item">
<div class="info-label">Broker Manager</div>
<div class="info-value"><?= htmlspecialchars($agency['broker_manager'] ?: 'Non assegnato') ?></div>
</div>
<div class="info-item">
<div class="info-label">Indirizzo</div>
<div class="info-value"><?= htmlspecialchars($agency['address'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">Città</div>
<div class="info-value"><?= htmlspecialchars($agency['city']) ?><?= $agency['province'] ? ' (' . htmlspecialchars($agency['province']) . ')' : '' ?></div>
</div>
<div class="info-item">
<div class="info-label">CAP</div>
<div class="info-value"><?= htmlspecialchars($agency['zip_code'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">Email</div>
<div class="info-value"><?= htmlspecialchars($agency['email'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">Telefono</div>
<div class="info-value"><?= htmlspecialchars($agency['phone'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">P.IVA</div>
<div class="info-value"><?= htmlspecialchars($agency['vat_number'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">Codice Fiscale</div>
<div class="info-value"><?= htmlspecialchars($agency['tax_code'] ?: 'Non disponibile') ?></div>
</div>
<div class="info-item">
<div class="info-label">Codice SDI</div>
<div class="info-value"><?= htmlspecialchars($agency['sdi_code'] ?: 'Non disponibile') ?></div>
</div>
</div>
</div>
<div class="tab-content" id="tab-contract">
<h2 class="section-title">Informazioni Contrattuali</h2>
<div class="info-grid">
<div class="info-item">
<div class="info-label">Data Attivazione</div>
<div class="info-value"><?= $agency['activation_date'] ? date('d/m/Y', strtotime($agency['activation_date'])) : 'Non disponibile' ?></div>
</div>
<div class="info-item">
<div class="info-label">Data Chiusura</div>
<div class="info-value"><?= $agency['closed_date'] ? date('d/m/Y', strtotime($agency['closed_date'])) : 'Non applicabile' ?></div>
</div>
<div class="info-item">
<div class="info-label">Data Firma Contratto</div>
<div class="info-value"><?= $agency['sold_date'] ? date('d/m/Y', strtotime($agency['sold_date'])) : 'Non disponibile' ?></div>
</div>
<div class="info-item">
<div class="info-label">Durata Contratto (anni)</div>
<div class="info-value"><?= $agency['contract_duration_years'] ?: 'Non specificata' ?></div>
</div>
<div class="info-item">
<div class="info-label">Scadenza Contratto</div>
<div class="info-value"><?= $agency['contract_expiry'] ? date('d/m/Y', strtotime($agency['contract_expiry'])) : 'Non disponibile' ?></div>
</div>
<div class="info-item">
<div class="info-label">Tech Fee</div>
<div class="info-value"><?= $agency['tech_fee'] ? '€ ' . number_format($agency['tech_fee'], 2, ',', '.') : 'Non specificata' ?></div>
</div>
</div>
<h2 class="section-title" style="margin-top:2rem">Note Contrattuali</h2>
<div class="info-item">
<div class="info-value"><?= $agency['contract_notes'] ? nl2br(htmlspecialchars($agency['contract_notes'])) : 'Nessuna nota' ?></div>
</div>
</div>
<div class="tab-content" id="tab-services">
<h2 class="section-title">Servizi Attivi</h2>
<p style="color:var(--cb-gray);margin-bottom:2rem">Gestione servizi disponibile in Fase 2</p>
<div class="services-list">
<div class="service-item">
<div class="service-info">
<h4>📧 Email Aziendale</h4>
<div class="service-dates">Non configurato</div>
</div>
<div class="service-toggle"></div>
</div>
<div class="service-item">
<div class="service-info">
<h4>🌐 Sito Web</h4>
<div class="service-dates">Non configurato</div>
</div>
<div class="service-toggle"></div>
</div>
<div class="service-item">
<div class="service-info">
<h4>📱 Social Media</h4>
<div class="service-dates">Non configurato</div>
</div>
<div class="service-toggle"></div>
</div>
</div>
</div>
<div class="tab-content" id="tab-agents">
<button class="btn-add-agent" onclick="window.location.href='agente_add.php?agency_id=<?= $agency['id'] ?>'">➕ Aggiungi Agente</button>
<?php if (empty($agents)): ?>
<div class="empty-agents">
<div style="font-size:3rem;margin-bottom:1rem">👥</div>
<h3>Nessun agente registrato</h3>
<p>Aggiungi il primo agente per questa agenzia</p>
</div>
<?php else: ?>
<table class="agents-table">
<thead>
<tr>
<th>Nome</th>
<th>Email</th>
<th>Telefono</th>
<th>Ruolo</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($agents as $agent): ?>
<tr>
<td><span class="agent-name" onclick="window.location.href='agente_detail.php?id=<?= $agent['id'] ?>'"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></span></td>
<td><?= htmlspecialchars($agent['email_personal'] ?: 'Non disponibile') ?></td>
<td><?= htmlspecialchars($agent['mobile'] ?: 'Non disponibile') ?></td>
<td><?= htmlspecialchars($agent['role'] ?: 'Non specificato') ?></td>
<td><span class="status-badge <?= strtolower($agent['status']) ?>"><?= htmlspecialchars($agent['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
</div>
<script>
const hamburger=document.getElementById('hamburger');
const mobileSidebar=document.getElementById('mobileSidebar');
const sidebarBackdrop=document.getElementById('sidebarBackdrop');
const sidebarClose=document.getElementById('sidebarClose');

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

function switchTab(tabName){
document.querySelectorAll('.tab-button').forEach(btn=>btn.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(content=>content.classList.remove('active'));
document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
document.getElementById('tab-'+tabName).classList.add('active');
}
</script>
</body>
</html>

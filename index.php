<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Solo ATTIVI nei totali
$agenciesStats = $pdo->query("SELECT COUNT(*) as total FROM agencies WHERE status = 'Active'")->fetch();
$agentsStats = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'Active'")->fetch();
$ticketsOpen = 0;

$recentAgencies = $pdo->query("SELECT name, city, created_at FROM agencies WHERE status = 'Active' ORDER BY created_at DESC LIMIT 5")->fetchAll();
$user = $_SESSION['crm_user'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - CRM Coldwell Banker</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--cb-blue:#012169;--cb-bright-blue:#1F69FF;--cb-midnight:#0A1730;--cb-gray:#6D7180;--bg:#F5F7FA}
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
.dropdown-item:first-child{border-radius:8px 8px 0 0}
.dropdown-item:last-child{border-radius:0 0 8px 8px}
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
.welcome{margin-bottom:2rem}
.welcome h1{font-size:1.75rem;font-weight:600;margin-bottom:.5rem}
.welcome p{color:var(--cb-gray);font-size:.95rem}
.search-container{position:relative;background:white;border-radius:12px;padding:1rem 1.5rem;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.search-container input{width:100%;border:none;font-size:1rem;outline:none;background:transparent}
.search-results{position:absolute;top:100%;left:0;right:0;margin-top:.5rem;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:400px;overflow-y:auto;display:none;z-index:1000}
.search-results.active{display:block}
.search-item{padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;margin-bottom:.25rem;color:var(--cb-midnight)}
.search-item-meta{font-size:.875rem;color:var(--cb-gray)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;margin-bottom:2rem}
.stat-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid var(--cb-bright-blue);position:relative}
.stat-card h3{font-size:.875rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);margin-bottom:.75rem;font-weight:600}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--cb-blue);margin-bottom:.5rem}
.btn-csv{position:absolute;top:1rem;right:1rem;background:var(--cb-bright-blue);color:white;border:none;padding:.5rem 1rem;border-radius:6px;font-size:.8rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-block}
.btn-csv:hover{background:var(--cb-blue)}
.widgets-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:2rem}
.widget{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.widget-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #E5E7EB}
.widget-icon{font-size:1.5rem}
.widget-title{font-size:1.125rem;font-weight:600}
.widget-placeholder{text-align:center;padding:2rem 1rem;color:var(--cb-gray)}
.widget-placeholder-icon{font-size:3rem;margin-bottom:.75rem;opacity:.3}
.recent-activity{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.recent-activity h2{font-size:1.125rem;font-weight:600;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #E5E7EB}
.activity-item{padding:.75rem 0;border-bottom:1px solid #F3F4F6}
.activity-item:last-child{border-bottom:none}
.activity-title{font-weight:500;margin-bottom:.25rem}
.activity-meta{font-size:.875rem;color:var(--cb-gray)}
.footer{background:white;border-top:1px solid #E5E7EB;margin-top:3rem;padding:1.5rem 0}
.footer-content{max-width:1400px;margin:0 auto;padding:0 1.5rem;text-align:center;color:var(--cb-gray);font-size:.875rem}
@media(max-width:768px){
.hamburger{display:block}
.header-left{gap:1rem}
.main-nav,.user-menu{display:none}
.container{padding:1rem}
.welcome h1{font-size:1.5rem}
.stats-grid,.widgets-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="mobile-sidebar" id="mobileSidebar">
<div class="sidebar-header">
<button class="sidebar-close" id="sidebarClose">✕</button>
<div style="clear:both"></div>
</div>
<div class="sidebar-nav">
<div class="sidebar-item">
<button class="sidebar-button" onclick="toggleSidebarDropdown('gestione')">GESTIONE ▼</button>
<div class="sidebar-dropdown" id="dropdown-gestione">
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
<a href="sviluppo.php" class="sidebar-button" style="display:block">SVILUPPO</a>
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
<a href="https://coldwellbankeritaly.tech/repository/dashboard/" class="sidebar-dropdown-item">🏠 Dashboard CB Italia</a>
<a href="logout.php" class="sidebar-dropdown-item">🚪 Logout</a>
</div>
</div>
<div class="header">
<div class="header-content">
<div class="header-left">
<button class="hamburger" id="hamburger">☰</button>
<img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker" class="logo">
<nav class="main-nav">
<div class="nav-item">
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
<a href="https://coldwellbankeritaly.tech/repository/dashboard/" class="dropdown-item">🏠 Dashboard CB Italia</a>
<a href="logout.php" class="dropdown-item">🚪 Logout</a>
</div>
</div>
</div>
</div>
<div class="container">
<div class="welcome">
<h1>👋 Ciao, <?= htmlspecialchars($user['name']) ?></h1>
<p>Overview del network Coldwell Banker Italy</p>
</div>
<div class="search-container">
<input type="text" id="searchInput" placeholder="🔍 Cerca agenzie, agenti..." autocomplete="off">
<div class="search-results" id="searchResults"></div>
</div>
<div class="stats-grid">
<div class="stat-card">
<a href="export_agencies.php" class="btn-csv">📥 CSV</a>
<h3>Agenzie Attive</h3>
<div class="stat-value"><?= $agenciesStats['total'] ?></div>
</div>
<div class="stat-card">
<a href="export_agents.php" class="btn-csv">📥 CSV</a>
<h3>Agenti Attivi</h3>
<div class="stat-value"><?= $agentsStats['total'] ?></div>
</div>
<div class="stat-card">
<h3>Ticket</h3>
<div class="stat-value"><?= $ticketsOpen ?></div>
<div class="stat-subtitle">Aperti</div>
</div>
</div>
<div class="widgets-grid">
<div class="widget">
<div class="widget-header">
<span class="widget-icon">📅</span>
<h3 class="widget-title">Prossimi 7 Giorni</h3>
</div>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">🚧</div>
<p>Calendario eventi<br><small>Disponibile in Fase 2</small></p>
</div>
</div>
<div class="widget">
<div class="widget-header">
<span class="widget-icon">🎫</span>
<h3 class="widget-title">Ticket Urgenti</h3>
</div>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">🚧</div>
<p>Sistema ticketing<br><small>Disponibile in Fase 2</small></p>
</div>
</div>
<div class="widget">
<div class="widget-header">
<span class="widget-icon">📰</span>
<h3 class="widget-title">News Recenti</h3>
</div>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">🚧</div>
<p>Integrazione News API<br><small>Disponibile in Fase 2</small></p>
</div>
</div>
</div>
<div class="recent-activity">
<h2>📈 Ultime Agenzie Aggiunte</h2>
<?php foreach($recentAgencies as $agency): ?>
<div class="activity-item">
<div class="activity-title"><?= htmlspecialchars($agency['name']) ?></div>
<div class="activity-meta"><?= htmlspecialchars($agency['city']) ?> · <?= date('d/m/Y H:i',strtotime($agency['created_at'])) ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="footer">
<div class="footer-content">
© <?= date('Y') ?> Coldwell Banker Italy - CRM Network · v1.0
</div>
</div>
<script>
const hamburger=document.getElementById('hamburger');
const mobileSidebar=document.getElementById('mobileSidebar');
const sidebarBackdrop=document.getElementById('sidebarBackdrop');
const sidebarClose=document.getElementById('sidebarClose');

hamburger.addEventListener('click',()=>{
mobileSidebar.classList.add('open');
sidebarBackdrop.classList.add('open');
});

sidebarClose.addEventListener('click',closeSidebar);
sidebarBackdrop.addEventListener('click',closeSidebar);

function closeSidebar(){
mobileSidebar.classList.remove('open');
sidebarBackdrop.classList.remove('open');
}

function toggleSidebarDropdown(id){
const dropdown=document.getElementById('dropdown-'+id);
dropdown.classList.toggle('open');
}

const searchInput=document.getElementById('searchInput');
const searchResults=document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim();
if(query.length<2){
searchResults.classList.remove('active');
return;
}
searchTimeout=setTimeout(()=>{
fetch('search_api.php?q='+encodeURIComponent(query))
.then(r=>r.json())
.then(data=>{
if(data.length===0){
searchResults.innerHTML='<div style="padding:1rem;text-align:center;color:#6D7180">Nessun risultato</div>';
}else{
searchResults.innerHTML=data.map(item=>`
<div class="search-item" onclick="window.location.href='${item.url}'">
<div class="search-item-title">${item.title}</div>
<div class="search-item-meta">${item.meta}</div>
</div>
`).join('');
}
searchResults.classList.add('active');
});
},300);
});

document.addEventListener('click',function(e){
if(!e.target.closest('.search-container')){
searchResults.classList.remove('active');
}
});

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
</script>
</body>
</html>

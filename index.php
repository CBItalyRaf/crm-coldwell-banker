<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pageTitle = "Dashboard - CRM Coldwell Banker";
$pdo = getDB();

// Solo ATTIVI nei totali
$agenciesStats = $pdo->query("SELECT COUNT(*) as total FROM agencies WHERE status = 'Active'")->fetch();
$agentsStats = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'Active'")->fetch();
$ticketsOpen = 0;

$recentAgencies = $pdo->query("SELECT name, city, created_at FROM agencies WHERE status = 'Active' ORDER BY created_at DESC LIMIT 5")->fetchAll();

require_once 'header.php';
?>

<style>
.welcome{margin-bottom:2rem}
.welcome h1{font-size:1.75rem;font-weight:600;margin-bottom:.5rem}
.welcome p{color:var(--cb-gray);font-size:.95rem}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;margin-bottom:2rem}
.stat-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid var(--cb-bright-blue);position:relative}
.stat-card h3{font-size:.875rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);margin-bottom:.75rem;font-weight:600}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--cb-blue);margin-bottom:.5rem}
.stat-subtitle{font-size:.85rem;color:var(--cb-gray);margin-top:.5rem}
.btn-csv{position:absolute;top:1rem;right:1rem;background:var(--cb-bright-blue);color:white;border:none;padding:.5rem 1rem;border-radius:6px;font-size:.8rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-block}
.btn-csv:hover{background:var(--cb-blue)}
.widgets-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:2rem}
.widget{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.widget-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #E5E7EB}
.widget-icon{font-size:1.5rem}
.widget-title{font-size:1.125rem;font-weight:600}
.widget-placeholder{text-align:center;padding:2rem 1rem;color:var(--cb-gray)}
.widget-placeholder-icon{font-size:3rem;margin-bottom:.75rem;opacity:.3}
.widget-content{padding:1.5rem}
.recent-item{padding:1rem;border-bottom:1px solid #f3f4f6;transition:background .2s}
.recent-item:last-child{border-bottom:none}
.recent-item:hover{background:var(--bg)}
.recent-item-name{font-weight:600;margin-bottom:.25rem}
.recent-item-meta{font-size:.85rem;color:var(--cb-gray)}
@media (max-width:768px){
.stats-grid,.widgets-grid{grid-template-columns:1fr}
}
</style>

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

<div class="widget">
<div class="widget-header">
<span class="widget-icon">🏢</span>
<h3 class="widget-title">Ultime Agenzie</h3>
</div>
<div class="widget-content" style="padding:0">
<?php foreach($recentAgencies as $agency): ?>
<div class="recent-item">
<div class="recent-item-name"><?= htmlspecialchars($agency['name']) ?></div>
<div class="recent-item-meta"><?= htmlspecialchars($agency['city']) ?> • <?= date('d/m/Y', strtotime($agency['created_at'])) ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<script>
// Autocomplete search per index.php
const searchInput=document.getElementById('searchInput');
const searchResults=document.getElementById('searchResults');
let searchTimeout;

if(searchInput && searchResults){
searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim();
if(query.length<2){
searchResults.classList.remove('active');
return;
}
searchTimeout=setTimeout(()=>{
fetch('https://admin.mycb.it/search_api.php?q='+encodeURIComponent(query))
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
}
</script>

<?php require_once 'footer.php'; ?>

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
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;margin-bottom:2rem}
.stat-card{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);position:relative}
.stat-card h3{font-size:.9rem;color:var(--cb-gray);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em}
.stat-value{font-size:2rem;font-weight:700;color:var(--cb-bright-blue)}
.stat-subtitle{font-size:.85rem;color:var(--cb-gray);margin-top:.5rem}
.btn-csv{position:absolute;top:1rem;right:1rem;background:var(--cb-bright-blue);color:white;border:none;padding:.5rem 1rem;border-radius:6px;font-size:.8rem;cursor:pointer;text-decoration:none;display:inline-block}
.btn-csv:hover{opacity:.9}
.widgets-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem}
.widget{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden}
.widget-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;gap:1rem}
.widget-icon{font-size:1.5rem}
.widget-title{font-size:1.1rem;font-weight:600}
.widget-content{padding:1.5rem}
.widget-placeholder{padding:3rem;text-align:center;color:var(--cb-gray)}
.widget-placeholder-icon{font-size:3rem;margin-bottom:1rem;opacity:.3}
.recent-item{padding:1rem;border-bottom:1px solid #f3f4f6;transition:background .2s}
.recent-item:last-child{border-bottom:none}
.recent-item:hover{background:var(--bg)}
.recent-item-name{font-weight:600;margin-bottom:.25rem}
.recent-item-meta{font-size:.85rem;color:var(--cb-gray)}
@media (max-width:768px){
.stats-grid{grid-template-columns:1fr}
}
</style>

<div class="welcome">
<h1>👋 Ciao, <?= htmlspecialchars($user['name']) ?></h1>
<p>Overview del network Coldwell Banker Italy</p>
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

<?php require_once 'footer.php'; ?>

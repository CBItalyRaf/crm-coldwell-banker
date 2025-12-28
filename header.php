<?php
// Header comune per tutte le pagine, fixato
// Variabili richieste: $pageTitle (opzionale, default "CRM Coldwell Banker")
$pageTitle = $pageTitle ?? 'CRM Coldwell Banker';
$user = $_SESSION['crm_user'] ?? null;

if (!$user) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--cb-blue:#012169;--cb-bright-blue:#1F69FF;--cb-midnight:#0A1730;--cb-gray:#6D7180;--bg:#F5F7FA}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--cb-midnight);line-height:1.6}
.header{background:var(--cb-blue);color:white;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header-content{max-width:1400px;margin:0 auto;padding:0 1.5rem;display:flex;justify-content:space-between;align-items:center;min-height:70px}
.header-left{display:flex;align-items:center;gap:3rem}
.logo{height:32px}
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
.container{max-width:1400px;margin:0 auto;padding:2rem 1.5rem}
.search-container{position:relative;background:white;border-radius:12px;padding:1rem 1.5rem;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.search-container input{width:100%;border:none;font-size:1rem;outline:none;background:transparent}
.search-results{position:absolute;top:100%;left:0;right:0;margin-top:.5rem;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:400px;overflow-y:auto;display:none;z-index:1000}
.search-results.active{display:block}
.search-item{padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;margin-bottom:.25rem}
.search-item-meta{font-size:.85rem;color:var(--cb-gray)}
.hamburger{display:none;background:transparent;border:none;color:white;font-size:1.5rem;cursor:pointer;padding:.5rem}
@media (max-width:768px){
.hamburger{display:block}
.main-nav{display:none}
.header-left{gap:1rem}
}
</style>
</head>
<body>
<div class="header">
<div class="header-content">
<div class="header-left">
<button class="hamburger" id="hamburger">☰</button>
<a href="index.php"><img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker" class="logo"></a>
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
<div class="search-container">
<input type="text" id="searchInput" placeholder="🔍 Cerca agenzie, agenti..." autocomplete="off">
<div class="search-results" id="searchResults"></div>
</div>

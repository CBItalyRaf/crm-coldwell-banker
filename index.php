<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';

$pageTitle = "Dashboard - CRM Coldwell Banker";
$pdo = getDB();

// Solo ATTIVI nei totali
$agenciesStats = $pdo->query("SELECT COUNT(*) as total FROM agencies WHERE status = 'Active'")->fetch();
$agentsStats = $pdo->query("SELECT COUNT(*) as total FROM agents WHERE status = 'Active'")->fetch();

// Ticket per dashboard
$ticketsOpen = 0;
$ticketsPersonali = 0;
$ticketsPrivati = 0;
$recentTickets = [];

// Carica ticket solo se la tabella esiste
try {
    $ticketsOpen = $pdo->query("SELECT COUNT(*) FROM tickets WHERE stato IN ('nuovo','in_lavorazione','in_attesa')")->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assegnato_a_email = ? AND stato NOT IN ('risolto')");
    $stmt->execute([$user['email']]);
    $ticketsPersonali = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE is_privato = 1 AND creato_da_email = ? AND stato NOT IN ('risolto')");
    $stmt->execute([$user['email']]);
    $ticketsPrivati = $stmt->fetchColumn();
    
    // Ultimi 5 ticket
    $stmt = $pdo->prepare("
        SELECT t.*, tc.nome as categoria_nome, tc.colore as categoria_colore, tc.icona as categoria_icona,
               ag.name as agenzia_name, ag.code as agenzia_code
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.categoria_id = tc.id
        LEFT JOIN agencies ag ON t.agenzia_id = ag.id
        WHERE t.stato NOT IN ('risolto')
        ORDER BY 
            CASE WHEN t.assegnato_a_email = ? THEN 1 ELSE 2 END,
            CASE WHEN t.is_privato = 1 AND t.creato_da_email = ? THEN 1 ELSE 3 END,
            t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['email'], $user['email']]);
    $recentTickets = $stmt->fetchAll();
} catch (Exception $e) {
    // Tabella tickets non esiste ancora, ignora
}

$recentAgencies = $pdo->query("SELECT name, city, created_at FROM agencies WHERE status = 'Active' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Onboarding attivi
$onboardings = $pdo->query("
    SELECT o.*, a.name as agency_name, a.code as agency_code,
    (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id) as total_tasks,
    (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id AND is_completed = 1) as completed_tasks
    FROM onboardings o
    JOIN agencies a ON o.agency_id = a.id
    WHERE o.status = 'active'
    ORDER BY o.started_at DESC
    LIMIT 5
")->fetchAll();

// Ultime 5 news
$latestNews = getNewsArticles(5);
$newsArticles = $latestNews['data'] ?? [];

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
.widgets-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:2rem}
.widget-news-full{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
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
.onboarding-item{padding:1rem;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .2s}
.onboarding-item:last-child{border-bottom:none}
.onboarding-item:hover{background:var(--bg)}
.onboarding-item.on{border-left:4px solid #10B981}
.onboarding-item.off{border-left:4px solid #EF4444}
.onboarding-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.onboarding-name{font-weight:600;color:var(--cb-midnight)}
.onboarding-badge{padding:.25rem .5rem;border-radius:6px;font-size:.75rem;font-weight:600}
.onboarding-badge.on{background:#D1FAE5;color:#065F46}
.onboarding-badge.off{background:#FEE2E2;color:#991B1B}
.onboarding-progress{height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden;margin-top:.5rem}
.onboarding-progress-bar{height:100%;background:#10B981;transition:width .3s}
.onboarding-progress-bar.off{background:#EF4444}
.onboarding-meta{font-size:.85rem;color:var(--cb-gray);margin-top:.25rem}
.calendar-event{padding:.75rem 1rem;border-left:4px solid;cursor:pointer;transition:all .2s;margin-bottom:.5rem;border-radius:4px}
.calendar-event:hover{background:var(--bg);transform:translateX(4px)}
.calendar-event-title{font-weight:600;font-size:.9rem;margin-bottom:.25rem;color:var(--cb-midnight)}
.calendar-event-date{font-size:.8rem;color:var(--cb-gray);display:flex;align-items:center;gap:.5rem}
.calendar-event-agency{font-size:.85rem;color:var(--cb-bright-blue);margin-top:.25rem}
.no-events{text-align:center;padding:2rem;color:var(--cb-gray)}
.search-container{position:relative;margin-bottom:2rem}
.search-container input{width:100%;padding:1rem 1.25rem;border:2px solid #E5E7EB;border-radius:10px;font-size:1rem;transition:border .2s}
.search-container input:focus{outline:none;border-color:var(--cb-bright-blue)}
.search-results{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #E5E7EB;border-radius:10px;margin-top:.5rem;max-height:400px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:1000;display:none}
.search-results.active{display:block}
.search-item{padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6;cursor:pointer;transition:background .2s}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:var(--bg)}
.search-item-title{font-weight:600;color:var(--cb-midnight);margin-bottom:.25rem;font-size:.95rem}
.search-item-meta{font-size:.85rem;color:var(--cb-gray)}
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

<?php
// Widget scadenze (se file esiste e utente ha preferenza attiva)
if (file_exists(__DIR__ . '/widgets/scadenze_dashboard.php')) {
    include __DIR__ . '/widgets/scadenze_dashboard.php';
}
?>

<div class="widgets-grid">
<div class="widget">
<div class="widget-header">
<span class="widget-icon">📅</span>
<h3 class="widget-title">Prossimi 7 Giorni</h3>
<a href="team_calendar.php" style="margin-left:auto;font-size:.85rem;color:var(--cb-bright-blue);text-decoration:none;font-weight:600">Vedi tutto →</a>
</div>
<div id="calendarWidget" style="padding:0">
<div class="widget-placeholder">
<div class="widget-placeholder-icon">⏳</div>
<p>Caricamento eventi...</p>
</div>
</div>
</div>

<div class="widget">
<div class="widget-header">
<span class="widget-icon">🎫</span>
<h3 class="widget-title">Ticket</h3>
<a href="tickets.php" style="margin-left:auto;font-size:.85rem;color:var(--cb-bright-blue);text-decoration:none;font-weight:600">Vedi tutti →</a>
</div>
<div class="widget-content" style="padding:0">
<?php if(empty($recentTickets)): ?>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">✅</div>
<p>Nessun ticket aperto</p>
</div>
<?php else: ?>
<?php foreach($recentTickets as $ticket): 
$isPersonale = ($ticket['assegnato_a_email'] == $user['email']);
$isPrivato = ($ticket['is_privato'] == 1 && $ticket['creato_da_email'] == $user['email']);
$bgColor = $isPrivato ? '#FEF3C7' : ($isPersonale ? '#DBEAFE' : '#F9FAFB');
?>
<div class="onboarding-item" style="background:<?= $bgColor ?>;cursor:pointer" onclick="window.location.href='ticket_detail.php?id=<?= $ticket['id'] ?>'">
<div class="onboarding-header">
<div class="onboarding-name">
<?= $isPrivato ? '🔒' : '🎫' ?> <?= htmlspecialchars($ticket['titolo']) ?>
</div>
<span class="onboarding-badge" style="background:<?= $ticket['categoria_colore'] ?>20;color:<?= $ticket['categoria_colore'] ?>">
<?= $ticket['categoria_icona'] ?> <?= htmlspecialchars($ticket['categoria_nome']) ?>
</span>
</div>
<div class="onboarding-meta">
<?php if($ticket['agenzia_name']): ?>
🏢 <?= htmlspecialchars($ticket['agenzia_name']) ?> • 
<?php else: ?>
📝 Task Interno • 
<?php endif; ?>
<?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
<?php if($isPersonale): ?>
<span style="color:#1F69FF;font-weight:600"> • I MIEI</span>
<?php endif; ?>
<?php if($isPrivato): ?>
<span style="color:#F59E0B;font-weight:600"> • PRIVATO</span>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<div class="widget">
<div class="widget-header">
<span class="widget-icon">🔄</span>
<h3 class="widget-title">Onboarding & Offboarding</h3>
</div>
<div class="widget-content" style="padding:0">
<?php if(empty($onboardings)): ?>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">✅</div>
<p>Nessun onboarding attivo</p>
</div>
<?php else: ?>
<?php foreach($onboardings as $onb): 
$progress = $onb['total_tasks'] > 0 ? round(($onb['completed_tasks'] / $onb['total_tasks']) * 100) : 0;
?>
<div class="onboarding-item on" onclick="window.location.href='onboarding_detail.php?agency_id=<?= $onb['agency_id'] ?>'">
<div class="onboarding-header">
<div class="onboarding-name">➕ <?= htmlspecialchars($onb['agency_name']) ?></div>
<span class="onboarding-badge on">In Corso</span>
</div>
<div class="onboarding-meta"><?= htmlspecialchars($onb['agency_code']) ?> • Avviato <?= date('d/m/Y', strtotime($onb['started_at'])) ?></div>
<div class="onboarding-progress">
<div class="onboarding-progress-bar" style="width:<?= $progress ?>%"></div>
</div>
<div class="onboarding-meta" style="text-align:right;margin-top:.25rem"><?= $onb['completed_tasks'] ?>/<?= $onb['total_tasks'] ?> (<?= $progress ?>%)</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>

<!-- Widget News Full Width -->
<div class="widget-news-full">
<div class="widget-header" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid #E5E7EB">
<span class="widget-icon" style="font-size:1.5rem">📰</span>
<h3 class="widget-title" style="font-size:1.125rem;font-weight:600;flex:1">News Recenti CB Italia</h3>
<a href="news.php" style="font-size:.85rem;color:var(--cb-bright-blue);text-decoration:none;font-weight:600">Vedi tutte →</a>
</div>

<?php if(empty($newsArticles)): ?>
<div class="widget-placeholder">
<div class="widget-placeholder-icon">📰</div>
<p>Nessuna news disponibile</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem">
<?php foreach(array_slice($newsArticles, 0, 5) as $article): 
$isInternal = ($article['visibility'] ?? 'public') === 'internal';
?>
<div onclick="window.location.href='news_detail.php?id=<?= $article['id'] ?>'" style="cursor:pointer;padding:1.25rem;border:1px solid #E5E7EB;border-radius:8px;transition:all .2s;<?= $isInternal ? 'background:#EFF6FF;border-color:#3B82F6;' : 'background:white;' ?>;display:flex;gap:1rem;align-items:start" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,.1)';this.style.transform='translateX(4px)'" onmouseout="this.style.boxShadow='none';this.style.transform='translateX(0)'">
<?php 
$imageUrl = getFullImageUrl($article['image_url'] ?? null);
if($imageUrl): 
?>
<img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($article['title']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:6px;flex-shrink:0">
<?php endif; ?>
<div style="flex:1">
<div style="display:flex;align-items:center;gap:.5rem;font-weight:600;margin-bottom:.5rem;font-size:1rem">
<?= $isInternal ? '🔒' : '📰' ?>
<span style="flex:1"><?= htmlspecialchars($article['title']) ?></span>
</div>
<?php if(!empty($article['summary']) || !empty($article['excerpt'])): ?>
<p style="font-size:.9rem;color:var(--cb-gray);line-height:1.6;margin-bottom:.75rem">
<?= htmlspecialchars(substr($article['summary'] ?? $article['excerpt'], 0, 200)) ?>...
</p>
<?php endif; ?>
<div style="font-size:.85rem;color:var(--cb-gray);display:flex;gap:1rem;flex-wrap:wrap">
<span>📅 <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?></span>
<?php if(!empty($article['category'])): ?>
<span>🏷️ <?= htmlspecialchars($article['category']['name']) ?></span>
<?php endif; ?>
<?php if($isInternal): ?>
<span style="color:#3B82F6;font-weight:600">Solo CB</span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
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
console.log('Search results:', data);
if(data.length===0){
searchResults.innerHTML='<div style="padding:1rem;text-align:center;color:#6D7180">Nessun risultato</div>';
}else{
searchResults.innerHTML=data.map((item,idx)=>`
<div class="search-item" data-url="${item.url}" data-idx="${idx}">
<div class="search-item-title">${item.title}</div>
<div class="search-item-meta">${item.meta}</div>
</div>
`).join('');

// Aggiungi click listener a ogni risultato
searchResults.querySelectorAll('.search-item').forEach(el=>{
el.addEventListener('click',function(){
window.location.href=this.dataset.url;
});
});
}
searchResults.classList.add('active');
console.log('Active class added');
})
.catch(error=>{
console.error('Search error:', error);
searchResults.innerHTML='<div style="padding:1rem;text-align:center;color:#EF4444">Errore di ricerca</div>';
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

// Carica eventi calendario widget
function loadCalendarWidget() {
    fetch('api/team_calendar.php')
        .then(r => r.json())
        .then(events => {
            const container = document.getElementById('calendarWidget');
            if (!container) return;
            
            // Filtra solo prossimi 7 giorni
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            const sevenDays = new Date(now);
            sevenDays.setDate(sevenDays.getDate() + 7);
            
            const upcomingEvents = events.filter(e => {
                const eventDate = new Date(e.start);
                return eventDate >= now && eventDate <= sevenDays;
            }).sort((a, b) => new Date(a.start) - new Date(b.start));
            
            if (upcomingEvents.length === 0) {
                container.innerHTML = `
                    <div class="no-events">
                        <div style="font-size:2rem;margin-bottom:.5rem">✅</div>
                        <p>Nessun evento nei prossimi 7 giorni</p>
                    </div>
                `;
                return;
            }
            
            // Prendi solo primi 5 eventi
            const displayEvents = upcomingEvents.slice(0, 5);
            
            container.innerHTML = displayEvents.map(event => {
                const eventDate = new Date(event.start);
                const daysUntil = Math.ceil((eventDate - now) / (1000 * 60 * 60 * 24));
                const dateStr = eventDate.toLocaleDateString('it-IT', { day: '2-digit', month: 'short' });
                
                let daysText = '';
                if (daysUntil === 0) daysText = 'Oggi';
                else if (daysUntil === 1) daysText = 'Domani';
                else daysText = `tra ${daysUntil} giorni`;
                
                // Determina icona in base al tipo
                let icon = '📅';
                let subtitle = '';
                
                if (event.extendedProps.type === 'leave') {
                    const leaveIcons = {
                        'ferie': '🏖️',
                        'malattia': '🤒',
                        'permesso': '📋',
                        'smartworking': '🏠',
                        'altro': '📅'
                    };
                    icon = leaveIcons[event.extendedProps.leave_type] || '📅';
                    subtitle = event.extendedProps.leave_type.charAt(0).toUpperCase() + event.extendedProps.leave_type.slice(1);
                } else if (event.extendedProps.type === 'event') {
                    icon = '📆';
                    subtitle = event.extendedProps.location || 'Evento';
                }
                
                return `
                    <div class="calendar-event" style="border-left-color:${event.color}">
                        <div class="calendar-event-title">${icon} ${event.title}</div>
                        <div class="calendar-event-date">
                            📅 ${dateStr} • ${daysText}
                        </div>
                        ${subtitle ? `<div class="calendar-event-agency">${subtitle}</div>` : ''}
                    </div>
                `;
            }).join('');
            
            // Se ci sono più eventi, aggiungi link
            if (upcomingEvents.length > 5) {
                container.innerHTML += `
                    <div style="text-align:center;padding:1rem;border-top:1px solid #E5E7EB">
                        <a href="team_calendar.php" style="color:var(--cb-bright-blue);text-decoration:none;font-weight:600;font-size:.9rem">
                            Altri ${upcomingEvents.length - 5} eventi →
                        </a>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Errore caricamento calendario:', err);
            const container = document.getElementById('calendarWidget');
            if (container) {
                container.innerHTML = `
                    <div class="no-events">
                        <div style="font-size:2rem;margin-bottom:.5rem;color:#EF4444">⚠️</div>
                        <p>Errore nel caricamento</p>
                    </div>
                `;
            }
        });
}

// Carica al load
if (document.getElementById('calendarWidget')) {
    loadCalendarWidget();
}
</script>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/user_preferences.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Calendario Scadenze";

// Verifica che l'utente abbia almeno una preferenza scadenze attiva
$userPrefs = getUserPreferences($pdo, $user['email']);
$hasAccess = $userPrefs['notify_scadenze_email'] || 
             $userPrefs['notify_scadenze_badge'] || 
             $userPrefs['notify_scadenze_dashboard'];

require_once 'header.php';
?>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<style>
.calendar-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.calendar-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.calendar-legend{display:flex;gap:1.5rem;flex-wrap:wrap}
.legend-item{display:flex;align-items:center;gap:.5rem;font-size:.85rem}
.legend-dot{width:12px;height:12px;border-radius:50%}
.calendar-container{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}

/* FullCalendar custom styling */
.fc{--fc-border-color:#E5E7EB;--fc-button-bg-color:#012169;--fc-button-border-color:#012169;--fc-button-hover-bg-color:#1F69FF;--fc-button-hover-border-color:#1F69FF;--fc-button-active-bg-color:#0A1730;--fc-button-active-border-color:#0A1730;--fc-today-bg-color:rgba(31,105,255,.1)}
.fc .fc-button{text-transform:uppercase;font-size:.85rem;font-weight:600;letter-spacing:.05em}
.fc .fc-toolbar-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight)}
.fc-event{cursor:pointer;border:none;padding:.25rem .5rem}
.fc-event:hover{opacity:.8}
.fc-daygrid-day-number{color:var(--cb-midnight);font-weight:500}
.fc-col-header-cell-cushion{color:var(--cb-gray);font-weight:600;text-transform:uppercase;font-size:.75rem}

.no-access{text-align:center;padding:4rem 2rem;background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.no-access-icon{font-size:4rem;margin-bottom:1rem;opacity:.3}
.no-access h2{color:var(--cb-midnight);margin-bottom:1rem}
.no-access p{color:var(--cb-gray);margin-bottom:2rem}
.no-access a{background:var(--cb-bright-blue);color:white;padding:1rem 2rem;border-radius:8px;text-decoration:none;display:inline-block;font-weight:600}
.no-access a:hover{background:var(--cb-blue)}
</style>

<?php if (!$hasAccess): ?>
<div class="no-access">
    <div class="no-access-icon">🔒</div>
    <h2>Accesso Negato</h2>
    <p>Per visualizzare il calendario scadenze devi attivare almeno una preferenza di notifica.</p>
    <a href="user_settings.php">⚙️ Vai alle Impostazioni</a>
</div>
<?php else: ?>

<div class="calendar-header">
    <h1 class="calendar-title">📅 Calendario Scadenze Servizi</h1>
    <div class="calendar-legend">
        <div class="legend-item">
            <span class="legend-dot" style="background:#EF4444"></span>
            <span>Scade entro 7 giorni</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#F97316"></span>
            <span>Scade entro 14 giorni</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#F59E0B"></span>
            <span>Scade oltre 14 giorni</span>
        </div>
    </div>
</div>

<div class="calendar-container">
    <div id="calendar"></div>
</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales/it.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'it',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,listMonth'
        },
        buttonText: {
            today: 'Oggi',
            month: 'Mese',
            week: 'Settimana',
            list: 'Lista'
        },
        height: 'auto',
        events: 'api/scadenze_calendar.php',
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function(info) {
            // Tooltip con info aggiuntive
            const props = info.event.extendedProps;
            if (props.days_remaining !== undefined) {
                const days = props.days_remaining;
                let tooltip = props.service_name + '\n';
                tooltip += days > 0 ? `Scade tra ${days} giorni` : 'SCADUTO';
                info.el.title = tooltip;
            }
        }
    });
    
    calendar.render();
});
</script>

<?php endif; ?>

<?php require_once 'footer.php'; ?>

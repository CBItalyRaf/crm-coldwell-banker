<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Calendario Team - Eventi & Ferie";

require_once 'header.php';
?>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<style>
.calendar-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.calendar-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0 0 1rem 0}
.calendar-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem}
.legend-item{display:flex;align-items:center;gap:.5rem;font-size:.85rem}
.legend-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
.calendar-container{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}

/* FullCalendar custom styling */
.fc{--fc-border-color:#E5E7EB;--fc-button-bg-color:#012169;--fc-button-border-color:#012169;--fc-button-hover-bg-color:#1F69FF;--fc-button-hover-border-color:#1F69FF;--fc-button-active-bg-color:#0A1730;--fc-button-active-border-color:#0A1730;--fc-today-bg-color:rgba(31,105,255,.1)}
.fc .fc-button{text-transform:uppercase;font-size:.85rem;font-weight:600;letter-spacing:.05em}
.fc .fc-toolbar-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight)}
.fc-event{cursor:pointer;border:none;padding:.25rem .5rem}
.fc-event:hover{opacity:.8}
.fc-daygrid-day-number{color:var(--cb-midnight);font-weight:500}
.fc-col-header-cell-cushion{color:var(--cb-gray);font-weight:600;text-transform:uppercase;font-size:.75rem}
</style>

<div class="calendar-header">
    <h1 class="calendar-title">📅 Calendario Team - Eventi & Ferie</h1>
    <div class="calendar-legend">
        <div style="margin-bottom:.5rem;width:100%;font-weight:600;color:var(--cb-midnight);font-size:.9rem">Ferie & Assenze:</div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#10B981"></span>
            <span>🏖️ Ferie</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#EF4444"></span>
            <span>🤒 Malattia</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#F59E0B"></span>
            <span>📋 Permesso</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#3B82F6"></span>
            <span>🏠 Smart Working</span>
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#6B7280"></span>
            <span>📅 Altro</span>
        </div>
        
        <div style="margin:.5rem 0 .5rem 0;width:100%;font-weight:600;color:var(--cb-midnight);font-size:.9rem">Eventi:</div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#012169"></span>
            <span>📆 Eventi da Booking</span>
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
        events: 'api/team_calendar.php',
        eventDidMount: function(info) {
            // Tooltip con info aggiuntive
            const props = info.event.extendedProps;
            let tooltip = info.event.title + '\n';
            
            if (props.type === 'leave') {
                tooltip += '━'.repeat(20) + '\n';
                tooltip += 'Tipo: ' + props.leave_type.charAt(0).toUpperCase() + props.leave_type.slice(1) + '\n';
                tooltip += 'Dal: ' + new Date(info.event.start).toLocaleDateString('it-IT') + '\n';
                tooltip += 'Al: ' + new Date(info.event.end).toLocaleDateString('it-IT') + '\n';
                if (props.notes) {
                    tooltip += 'Note: ' + props.notes;
                }
            } else if (props.type === 'event') {
                tooltip += '━'.repeat(20) + '\n';
                if (props.description) tooltip += props.description + '\n';
                if (props.location) tooltip += '📍 ' + props.location + '\n';
                tooltip += 'Creato da: ' + props.created_by;
            }
            
            if (tooltip) {
                info.el.title = tooltip;
            }
        }
    });
    
    calendar.render();
});
</script>

<?php require_once 'footer.php'; ?>

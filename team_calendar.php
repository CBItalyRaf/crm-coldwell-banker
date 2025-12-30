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

.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.2)}
.modal-header{padding:1.5rem;border-bottom:2px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600;color:var(--cb-midnight)}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray);padding:.25rem}
.modal-close:hover{color:var(--cb-midnight)}
.modal-body{padding:1.5rem}
.form-group{margin-bottom:1.5rem}
.form-group label{display:block;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem;font-size:.9rem}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;font-family:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-group textarea{resize:vertical;min-height:100px}
.modal-footer{padding:1.5rem;border-top:2px solid #F3F4F6;display:flex;gap:1rem;justify-content:flex-end}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-submit{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600}
.btn-submit:hover{background:var(--cb-blue)}
</style>

<div class="calendar-header">
    <h1 class="calendar-title">📅 Calendario Team - Eventi & Ferie</h1>
    <div style="display:flex;gap:1rem;margin-bottom:1rem">
        <button onclick="openEventModal()" style="background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:.5rem">
            ➕ Nuovo Evento
        </button>
        <button onclick="openLeaveModal()" style="background:#10B981;color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:.5rem">
            🌴 Richiedi Ferie
        </button>
    </div>
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
        },
        eventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            
            // Solo eventi (non ferie) possono essere cancellati da qui
            if (props.type !== 'event') return;
            
            // BLOCCA eventi da Booking - nessuno può cancellarli
            if (props.created_by === 'Booking API') {
                alert('ℹ️ Gli eventi da Booking non possono essere cancellati dal calendario.\nContatta Raf per modifiche.');
                return;
            }
            
            // Eventi creati da utenti → TUTTI possono cancellare
            const eventId = event.id.replace('event_', '');
            const confirmMsg = `Cancellare questo evento?\n\n${event.title}\n${event.start.toLocaleDateString('it-IT')}`;
            
            if (confirm(confirmMsg)) {
                fetch('api/delete_event.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({event_id: eventId})
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        event.remove();
                        alert('✅ Evento cancellato');
                    } else {
                        alert('❌ Errore: ' + result.error);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('❌ Errore durante la cancellazione');
                });
            }
        }
    });
    
    calendar.render();
});

function openEventModal() {
    document.getElementById('eventModal').classList.add('active');
}

function closeEventModal() {
    document.getElementById('eventModal').classList.remove('active');
    document.getElementById('eventForm').reset();
}

document.getElementById('eventForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        title: formData.get('title'),
        description: formData.get('description'),
        start_datetime: formData.get('start_date') + ' ' + formData.get('start_time') + ':00',
        end_datetime: formData.get('end_date') + ' ' + formData.get('end_time') + ':00',
        location: formData.get('location'),
        event_type: formData.get('event_type'),
        color: formData.get('color')
    };
    
    fetch('api/calendar_events.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeEventModal();
            calendar.refetchEvents();
            alert('✅ Evento creato con successo!');
        } else {
            alert('❌ Errore: ' + result.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Errore durante il salvataggio');
    });
});

// Chiudi modal cliccando fuori
document.getElementById('eventModal').addEventListener('click', function(e) {
    if (e.target === this) closeEventModal();
});
</script>

<!-- Modal Nuovo Evento -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">➕ Nuovo Evento</h3>
            <button class="modal-close" onclick="closeEventModal()">✕</button>
        </div>
        <form id="eventForm">
            <div class="modal-body">
                <div class="form-group">
                    <label>Titolo *</label>
                    <input type="text" name="title" required placeholder="Es: Meeting con cliente">
                </div>
                
                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="description" placeholder="Dettagli evento..."></textarea>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label>Data Inizio *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>Ora Inizio *</label>
                        <input type="time" name="start_time" required value="10:00">
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label>Data Fine *</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label>Ora Fine *</label>
                        <input type="time" name="end_time" required value="12:00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Luogo</label>
                    <input type="text" name="location" placeholder="Es: Sede Milano, Webinar, etc.">
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label>Tipo Evento</label>
                        <select name="event_type">
                            <option value="generic">Generico</option>
                            <option value="meeting">Meeting</option>
                            <option value="training">Formazione</option>
                            <option value="webinar">Webinar</option>
                            <option value="conference">Conferenza</option>
                            <option value="deadline">Scadenza</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Colore</label>
                        <select name="color">
                            <option value="#012169">🔵 Blu CB</option>
                            <option value="#1F69FF">💙 Azzurro</option>
                            <option value="#10B981">🟢 Verde</option>
                            <option value="#F59E0B">🟡 Giallo</option>
                            <option value="#EF4444">🔴 Rosso</option>
                            <option value="#8B5CF6">🟣 Viola</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEventModal()">Annulla</button>
                <button type="submit" class="btn-submit">💾 Salva Evento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Richiesta Ferie -->
<div id="leaveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">🌴 Richiedi Ferie</h3>
            <button class="modal-close" onclick="closeLeaveModal()">✕</button>
        </div>
        <form id="leaveForm">
            <div class="modal-body">
                <div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:1rem;border-radius:8px;margin-bottom:1.5rem">
                    <p style="margin:0;font-size:.9rem;color:#92400E">
                        💡 La richiesta sarà inviata per approvazione. Riceverai una notifica quando sarà gestita.
                    </p>
                </div>
                
                <div class="form-group">
                    <label>Tipo Assenza *</label>
                    <select name="leave_type" required>
                        <option value="ferie">🏖️ Ferie</option>
                        <option value="permesso">📋 Permesso</option>
                        <option value="malattia">🤒 Malattia</option>
                        <option value="smartworking">🏠 Smart Working</option>
                        <option value="altro">📅 Altro</option>
                    </select>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label>Data Inizio *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>Data Fine *</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" placeholder="Eventuali dettagli o note..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeLeaveModal()">Annulla</button>
                <button type="submit" class="btn-submit">📤 Invia Richiesta</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal Ferie
function openLeaveModal() {
    document.getElementById('leaveModal').classList.add('active');
}

function closeLeaveModal() {
    document.getElementById('leaveModal').classList.remove('active');
    document.getElementById('leaveForm').reset();
}

document.getElementById('leaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        leave_type: formData.get('leave_type'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        notes: formData.get('notes')
    };
    
    fetch('api/request_leave.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeLeaveModal();
            alert('✅ Richiesta inviata! Attendi approvazione.');
        } else {
            alert('❌ Errore: ' + result.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert("❌ Errore durante l'invio della richiesta");
    });
});

// Chiudi modal cliccando fuori
document.getElementById('leaveModal').addEventListener('click', function(e) {
    if (e.target === this) closeLeaveModal();
});
</script>

<?php require_once 'footer.php'; ?>

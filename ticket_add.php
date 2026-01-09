<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

// Carica helper ticket solo se esiste
if (file_exists(__DIR__ . '/helpers/ticket_functions.php')) {
    require_once 'helpers/ticket_functions.php';
}

if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: tickets.php');
    exit;
}

$pageTitle = "Nuovo Ticket - CRM Coldwell Banker";
$pdo = getDB();

if (!$pdo) {
    die("Errore: impossibile connettersi al database");
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Genera numero ticket (semplice, senza lock)
        $year = date('Y');
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)), 0) + 1 as next_num
            FROM tickets 
            WHERE numero LIKE ?
        ");
        $stmt->execute(["TCK-$year-%"]);
        $num = $stmt->fetchColumn();
        $ticketNumero = sprintf('TCK-%s-%04d', $year, $num);
        
        // Inserisci ticket
        $stmt = $pdo->prepare("
            INSERT INTO tickets 
            (numero, titolo, descrizione, categoria_id, priorita, stato, 
             creato_da_email, creato_da_tipo, agenzia_id, assegnato_a_email,
             is_privato, destinatario_portal_user_id, destinatario_ruolo)
            VALUES (?, ?, ?, ?, ?, 'nuovo', ?, 'staff', ?, ?, ?, ?, ?)
        ");
        
        $agenziaId = !empty($_POST['agenzia_id']) ? $_POST['agenzia_id'] : null;
        $categoriaId = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
        
        $stmt->execute([
            $ticketNumero,
            $_POST['titolo'],
            $_POST['descrizione'],
            $categoriaId, // Corretto: NULL se vuoto
            $_POST['priorita'],
            $_SESSION['crm_user']['email'],
            $agenziaId, // Può essere NULL per task interni
            $_POST['assegnato_a'] ?: null,
            isset($_POST['is_privato']) && $agenziaId ? 1 : 0, // Privato solo se c'è agenzia
            $_POST['destinatario_id'] ?: null,
            $_POST['destinatario_ruolo'] ?: null
        ]);
        
        $ticketId = $pdo->lastInsertId();
        
        // Inserisci primo messaggio
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, staff_email, mittente_tipo, messaggio)
            VALUES (?, ?, 'staff', ?)
        ");
        $stmt->execute([$ticketId, $_SESSION['crm_user']['email'], $_POST['descrizione']]);
        
        // Gestione upload allegati
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                           'image/jpeg', 'image/png', 'image/gif', 'application/zip'];
            
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['attachments']['tmp_name'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    $fileType = $_FILES['attachments']['type'][$key];
                    
                    // Validazione
                    if ($fileSize > 10 * 1024 * 1024) { // 10MB
                        throw new Exception("File troppo grande: $filename (max 10MB)");
                    }
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Tipo file non supportato: $filename");
                    }
                    
                    // Genera nome sicuro
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $safeName = 'ticket_' . $ticketId . '_' . uniqid() . '.' . $ext;
                    $filePath = $uploadDir . $safeName;
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        // Salva in DB
                        $stmt = $pdo->prepare("
                            INSERT INTO ticket_attachments (ticket_id, filename, original_filename, filepath, filesize, uploaded_by_email)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $ticketId,
                            $safeName,
                            $filename,
                            'uploads/tickets/' . $safeName,
                            $fileSize,
                            $_SESSION['crm_user']['email']
                        ]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        // Invia notifica (solo se helper esiste) - non deve bloccare il salvataggio
        try {
            if (function_exists('sendTicketNotification')) {
                sendTicketNotification($pdo, $ticketId, 'new');
            }
        } catch (Exception $notifError) {
            error_log("Errore notifica ticket: " . $notifError->getMessage());
            // Ignora errore notifica, ticket già salvato
        }
        
        header("Location: tickets.php?success=created&ticket_id=$ticketId");
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Errore creazione ticket: " . $e->getMessage();
        error_log("ERRORE TICKET_ADD: " . $e->getMessage() . " | " . $e->getTraceAsString());
    }
}

// Carica dati
$categories = $pdo->query("SELECT * FROM ticket_categories WHERE attivo = 1 ORDER BY ordine")->fetchAll();
$agencies = $pdo->query("SELECT id, code, name FROM agencies WHERE status IN ('Active','Opening') ORDER BY name")->fetchAll();

// Carica staff da user_preferences (lista utenti che hanno fatto login)
$staff = $pdo->query("SELECT DISTINCT user_email as email FROM user_preferences ORDER BY user_email")->fetchAll();

require_once 'header.php';
?>

<style>
.form-card{background:white;border-radius:12px;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:900px;margin:2rem auto}
.form-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid var(--bg)}
.form-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.form-section h3{font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field input,.form-field select,.form-field textarea{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field textarea{min-height:150px;resize:vertical}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;text-decoration:none}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer}
.checkbox-field{display:flex;align-items:center;gap:.75rem;padding:1rem;background:var(--bg);border-radius:8px;cursor:pointer}
.checkbox-field input[type="checkbox"]{width:20px;height:20px;cursor:pointer}
.privato-options{display:none;margin-top:1rem;padding:1rem;background:#FEF3C7;border-radius:8px;border-left:4px solid #F59E0B}
</style>

<div class="form-card">
<h1 style="font-size:1.5rem;font-weight:600;margin-bottom:2rem">📝 Nuovo Ticket</h1>

<?php if (isset($error)): ?>
<div style="background:#FEE2E2;border-left:4px solid #EF4444;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#991B1B">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="form-section">
<h3>Informazioni Base</h3>
<div class="form-grid">
<div class="form-field" style="grid-column:1/-1">
<label>Titolo <span style="color:#EF4444">*</span></label>
<input type="text" name="titolo" required maxlength="255" placeholder="Oggetto del ticket">
</div>
<div class="form-field" style="grid-column:1/-1">
<label>Descrizione <span style="color:#EF4444">*</span></label>
<textarea name="descrizione" required placeholder="Descrivi il problema o la richiesta..."></textarea>
</div>
<div class="form-field">
<label>Categoria</label>
<select name="categoria_id">
<option value="">Seleziona categoria</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= $cat['id'] ?>"><?= $cat['icona'] ?> <?= htmlspecialchars($cat['nome']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-field">
<label>Priorità</label>
<select name="priorita">
<option value="media" selected>Media</option>
<option value="alta">Alta</option>
<option value="bassa">Bassa</option>
</select>
</div>
</div>
</div>

<div class="form-section">
<h3>Destinatario</h3>
<div class="form-grid">
<div class="form-field" style="grid-column:1/-1">
<label>Agenzia <span style="color:#999">(opzionale - lascia vuoto per task interni)</span></label>
<input type="text" 
       id="agencySearch" 
       placeholder="🔍 Cerca agenzia per codice o nome..."
       autocomplete="off">
<input type="hidden" name="agenzia_id" id="agenziaIdHidden">
<div id="agencyResults" style="display:none;position:absolute;background:white;border:1px solid #E5E7EB;border-radius:8px;margin-top:.5rem;max-height:300px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:100;width:calc(100% - 3rem)"></div>
<div id="selectedAgency" style="display:none;margin-top:.5rem;padding:.75rem;background:var(--bg);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
<span id="selectedAgencyText"></span>
<button type="button" onclick="clearAgency()" style="background:#EF4444;color:white;border:none;padding:.25rem .75rem;border-radius:6px;cursor:pointer;font-size:.85rem">✕ Rimuovi</button>
</div>
</div>
<div class="form-field">
<label>Assegna a</label>
<select name="assegnato_a">
<option value="">Non assegnato</option>
<?php foreach ($staff as $user): ?>
<option value="<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div id="destinatarioSection" style="display:none;margin-top:1.5rem">
<label class="checkbox-field" onclick="togglePrivato()">
<input type="checkbox" name="is_privato" id="privatoCheck">
<div>
<div style="font-weight:600;color:var(--cb-midnight)">🔒 Invia a destinatario specifico</div>
<div style="font-size:.85rem;color:var(--cb-gray)">Altrimenti visibile a tutti dell'agenzia</div>
</div>
</label>

<div id="privatoOptions" class="privato-options">
<div class="form-field">
<label>Destinatario</label>
<select name="destinatario_ruolo" id="destinatarioRuolo">
<option value="">Seleziona tipo</option>
<option value="broker_manager">Broker Manager</option>
<option value="assistente">Assistente</option>
<option value="agente">Agente specifico</option>
</select>
</div>
<div id="agenteSelectContainer" style="display:none;margin-top:1rem">
<label>Seleziona Agente</label>
<select name="destinatario_id" id="agenteSelect">
<option value="">Prima seleziona un'agenzia...</option>
</select>
</div>
</div>
</div>
</div>

<div class="form-section">
<h3>📎 Allegati</h3>
<div class="form-field">
<label>Carica file <span style="color:#999">(opzionale - max 10MB per file)</span></label>
<input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip" style="padding:.5rem">
<div style="font-size:.85rem;color:var(--cb-gray);margin-top:.5rem">
Formati supportati: PDF, Word, Excel, Immagini, ZIP
</div>
</div>
</div>

<div class="form-actions">
<a href="tickets.php" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Crea Ticket</button>
</div>
</form>
</div>

<script>
// Autocomplete agenzie
const agencySearch = document.getElementById('agencySearch');
const agencyResults = document.getElementById('agencyResults');
const agenziaIdHidden = document.getElementById('agenziaIdHidden');
const selectedAgency = document.getElementById('selectedAgency');
const selectedAgencyText = document.getElementById('selectedAgencyText');
const destinatarioSection = document.getElementById('destinatarioSection');
let agencies = <?= json_encode($agencies) ?>;
let searchTimeout;

agencySearch.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim().toLowerCase();
    
    if (query.length < 2) {
        agencyResults.style.display = 'none';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        const filtered = agencies.filter(ag => 
            ag.code.toLowerCase().includes(query) || 
            ag.name.toLowerCase().includes(query)
        ).slice(0, 10); // Max 10 risultati
        
        if (filtered.length > 0) {
            agencyResults.innerHTML = filtered.map(ag => `
                <div onclick="selectAgency(${ag.id}, '${ag.code}', '${ag.name.replace(/'/g, "\\'")}')}" 
                     style="padding:1rem;border-bottom:1px solid #F3F4F6;cursor:pointer;transition:background .2s"
                     onmouseover="this.style.background='var(--bg)'"
                     onmouseout="this.style.background='white'">
                    <div style="font-weight:600">${ag.code}</div>
                    <div style="font-size:.85rem;color:var(--cb-gray)">${ag.name}</div>
                </div>
            `).join('');
            agencyResults.style.display = 'block';
        } else {
            agencyResults.style.display = 'none';
        }
    }, 300);
});

function selectAgency(id, code, name) {
    agenziaIdHidden.value = id;
    agencySearch.style.display = 'none';
    agencyResults.style.display = 'none';
    selectedAgency.style.display = 'flex';
    selectedAgencyText.textContent = `${code} - ${name}`;
    destinatarioSection.style.display = 'block';
    
    // Carica agenti dell'agenzia
    loadAgents(id);
}

function clearAgency() {
    agenziaIdHidden.value = '';
    agencySearch.value = '';
    agencySearch.style.display = 'block';
    selectedAgency.style.display = 'none';
    destinatarioSection.style.display = 'none';
    document.getElementById('agenteSelect').innerHTML = '<option value="">Prima seleziona un\'agenzia...</option>';
}

function loadAgents(agencyId) {
    fetch(`get_agency_agents.php?agency_id=${agencyId}`)
        .then(r => r.json())
        .then(agents => {
            const select = document.getElementById('agenteSelect');
            select.innerHTML = '<option value="">Seleziona agente</option>';
            agents.forEach(agent => {
                select.innerHTML += `<option value="${agent.id}">${agent.full_name}</option>`;
            });
        });
}

// Chiudi dropdown quando clicchi fuori
document.addEventListener('click', function(e) {
    if (!e.target.closest('#agencySearch') && !e.target.closest('#agencyResults')) {
        agencyResults.style.display = 'none';
    }
});

function togglePrivato() {
    const check = document.getElementById('privatoCheck');
    check.checked = !check.checked;
    document.getElementById('privatoOptions').style.display = check.checked ? 'block' : 'none';
}

document.getElementById('destinatarioRuolo').addEventListener('change', function() {
    document.getElementById('agenteSelectContainer').style.display = 
        this.value === 'agente' ? 'block' : 'none';
});
</script>

<?php require_once 'footer.php'; ?>

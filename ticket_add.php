<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/ticket_functions.php';

if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: tickets.php');
    exit;
}

$pageTitle = "Nuovo Ticket - CRM Coldwell Banker";
$pdo = getDB();

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Inserisci ticket
        $stmt = $pdo->prepare("
            INSERT INTO tickets 
            (titolo, descrizione, categoria_id, priorita, stato, 
             creato_da_user_id, creato_da_tipo, agenzia_id, assegnato_a_user_id,
             is_privato, destinatario_portal_user_id, destinatario_ruolo)
            VALUES (?, ?, ?, ?, 'nuovo', ?, 'staff', ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['titolo'],
            $_POST['descrizione'],
            $_POST['categoria_id'] ?: null,
            $_POST['priorita'],
            $_SESSION['crm_user']['id'],
            $_POST['agenzia_id'],
            $_POST['assegnato_a'] ?: null,
            isset($_POST['is_privato']) ? 1 : 0,
            $_POST['destinatario_id'] ?: null,
            $_POST['destinatario_ruolo'] ?: null
        ]);
        
        $ticketId = $pdo->lastInsertId();
        
        // Inserisci primo messaggio
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, mittente_tipo, messaggio)
            VALUES (?, ?, 'staff', ?)
        ");
        $stmt->execute([$ticketId, $_SESSION['crm_user']['id'], $_POST['descrizione']]);
        
        $pdo->commit();
        
        // Invia notifica
        sendTicketNotification($pdo, $ticketId, 'new');
        
        header("Location: ticket_detail.php?id=$ticketId&success=created");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Errore: " . $e->getMessage();
    }
}

// Carica dati
$categories = $pdo->query("SELECT * FROM ticket_categories WHERE attivo = 1 ORDER BY ordine")->fetchAll();
$agencies = $pdo->query("SELECT id, code, name FROM agencies WHERE status IN ('Active','Opening') ORDER BY name")->fetchAll();
$staff = $pdo->query("SELECT id, name, email FROM crm_users WHERE active = 1 ORDER BY name")->fetchAll();

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

<form method="POST">
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
<label>Agenzia <span style="color:#EF4444">*</span></label>
<select name="agenzia_id" id="agenziaSelect" required>
<option value="">Seleziona agenzia</option>
<?php foreach ($agencies as $ag): ?>
<option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['code']) ?> - <?= htmlspecialchars($ag['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-field">
<label>Assegna a</label>
<select name="assegnato_a">
<option value="">Non assegnato</option>
<?php foreach ($staff as $user): ?>
<option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<label class="checkbox-field" onclick="togglePrivato()">
<input type="checkbox" name="is_privato" id="privatoCheck">
<div>
<div style="font-weight:600;color:var(--cb-midnight)">🔒 Ticket Privato</div>
<div style="font-size:.85rem;color:var(--cb-gray)">Visibile solo al destinatario specifico</div>
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
<option value="">Carica prima un'agenzia...</option>
</select>
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
function togglePrivato() {
    const check = document.getElementById('privatoCheck');
    check.checked = !check.checked;
    document.getElementById('privatoOptions').style.display = check.checked ? 'block' : 'none';
}

document.getElementById('destinatarioRuolo').addEventListener('change', function() {
    document.getElementById('agenteSelectContainer').style.display = 
        this.value === 'agente' ? 'block' : 'none';
});

document.getElementById('agenziaSelect').addEventListener('change', function() {
    const agenziaId = this.value;
    if (!agenziaId) return;
    
    // Carica agenti dell'agenzia
    fetch(`get_agency_agents.php?agency_id=${agenziaId}`)
        .then(r => r.json())
        .then(agents => {
            const select = document.getElementById('agenteSelect');
            select.innerHTML = '<option value="">Seleziona agente</option>';
            agents.forEach(agent => {
                select.innerHTML += `<option value="${agent.id}">${agent.full_name}</option>`;
            });
        });
});
</script>

<?php require_once 'footer.php'; ?>

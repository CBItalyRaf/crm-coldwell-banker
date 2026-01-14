<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/log_functions.php';

// Solo admin e editor
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Nuovo Agente - CRM Coldwell Banker";
$pdo = getDB();

$agencyCode = $_GET['agency'] ?? '';
$agency = null;

if ($agencyCode) {
    // Carica agenzia specifica
    $stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = :code");
    $stmt->execute(['code' => $agencyCode]);
    $agency = $stmt->fetch();
    
    if (!$agency) {
        header('Location: agenzie.php');
        exit;
    }
}

// Carica tutte le agenzie per dropdown (se agency non specificata)
$allAgencies = [];
if (!$agency) {
    $stmt = $pdo->query("SELECT id, code, name FROM agencies WHERE status = 'Active' ORDER BY name ASC");
    $allAgencies = $stmt->fetchAll();
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Funzione helper per validare date
        function validateDate($date) {
            if (empty($date)) return null;
            $date = trim($date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
            $parts = explode('-', $date);
            if (checkdate($parts[1], $parts[2], $parts[0])) {
                return $date;
            }
            return null;
        }
        
        $agencyId = $_POST['agency_id'];
        
        // Verifica che l'agenzia esista
        $stmt = $pdo->prepare("SELECT code FROM agencies WHERE id = :id");
        $stmt->execute(['id' => $agencyId]);
        $agencyData = $stmt->fetch();
        
        if (!$agencyData) {
            throw new Exception("Agenzia non valida");
        }
        
        // Gestione ruoli multipli
        $rolesArray = $_POST['roles'] ?? [];
        $rolesJson = !empty($rolesArray) ? json_encode($rolesArray) : null;
        
        // Valida date
        $emailActivationDate = validateDate($_POST['email_activation_date'] ?? '');
        $emailExpiryDate = validateDate($_POST['email_expiry_date'] ?? '');
        $insertedAt = validateDate($_POST['inserted_at'] ?? '') ?: date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO agents 
            (agency_id, first_name, last_name, mobile, email_corporate, email_personal, 
             m365_plan, m365_account_type, email_activation_date, email_expiry_date, role, status, notes, inserted_at)
            VALUES 
            (:agency_id, :first_name, :last_name, :mobile, :email_corporate, :email_personal,
             :m365_plan, :m365_account_type, :email_activation_date, :email_expiry_date, :role, :status, :notes, :inserted_at)
        ");
        
        $stmt->execute([
            'agency_id' => $agencyId,
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'mobile' => $_POST['mobile'] ?: null,
            'email_corporate' => $_POST['email_corporate'] ?: null,
            'email_personal' => $_POST['email_personal'] ?: null,
            'm365_plan' => $_POST['m365_plan'] ?: null,
            'm365_account_type' => $_POST['m365_account_type'] ?? 'agente',
            'email_activation_date' => $emailActivationDate,
            'email_expiry_date' => $emailExpiryDate,
            'role' => $rolesJson,
            'status' => $_POST['status'] ?? 'Active',
            'notes' => $_POST['notes'] ?: null,
            'inserted_at' => $insertedAt
        ]);
        
        $agentId = $pdo->lastInsertId();
        
        // Log creazione (protetto, non blocca mai)
        safeLogActivity(
            $pdo,
            $_SESSION['crm_user']['id'] ?? null,
            $_SESSION['crm_user']['email'] ?? 'unknown',
            'INSERT',
            'agents',
            $agentId
        );
        
        header("Location: agenzia_detail.php?code=" . urlencode($agencyData['code']) . "&success=agent_created#tab-agenti");
        exit();
        
    } catch (Exception $e) {
        error_log("Errore in agente_add: " . $e->getMessage());
        $error = "Errore durante la creazione: " . $e->getMessage();
    }
}

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.form-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid var(--cb-bright-blue);padding:2rem}
.form-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid #F3F4F6}
.form-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.form-section h3{font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field label .required{color:#EF4444}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-field textarea{min-height:100px;resize:vertical}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
.alert-error{background:#FEE2E2;border:1px solid #EF4444;color:#991B1B;padding:1rem;border-radius:8px;margin-bottom:1.5rem}
.role-checkbox{display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;background:white;transition:all .2s}
.role-checkbox:hover{border-color:var(--cb-bright-blue);background:var(--bg)}
.role-checkbox input[type="checkbox"]{cursor:pointer;width:18px;height:18px}
.role-checkbox span{font-weight:500;font-size:.95rem}
</style>

<div class="page-header">
<div>
<h1 class="page-title">👤 Nuovo Agente</h1>
<?php if ($agency): ?>
<div style="color:var(--cb-bright-blue);font-weight:600;margin-top:.5rem"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
<?php endif; ?>
</div>
<a href="<?= $agency ? 'agenzia_detail.php?code=' . urlencode($agency['code']) . '#tab-agenti' : 'agenti.php' ?>" class="back-btn">← Torna</a>
</div>

<?php if (isset($error)): ?>
<div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<div class="form-card">

<?php if (!$agency): ?>
<div class="form-section">
<h3>Agenzia</h3>
<div class="form-field">
<label>Seleziona Agenzia <span class="required">*</span></label>
<input type="text" 
    id="agencySearch" 
    list="agenciesList" 
    placeholder="Cerca per codice o nome agenzia..."
    autocomplete="off"
    required>
<datalist id="agenciesList">
<?php foreach ($allAgencies as $ag): ?>
<option value="<?= htmlspecialchars($ag['code']) ?>" data-id="<?= $ag['id'] ?>">
<?= htmlspecialchars($ag['code']) ?> - <?= htmlspecialchars($ag['name']) ?>
</option>
<?php endforeach; ?>
</datalist>
<input type="hidden" name="agency_id" id="agencyIdInput" required>
<div id="agencyPreview" style="margin-top:.5rem;padding:.5rem;background:#F3F4F6;border-radius:6px;display:none;font-size:.9rem;color:var(--cb-gray)"></div>
</div>
</div>
<?php else: ?>
<input type="hidden" name="agency_id" value="<?= $agency['id'] ?>">
<?php endif; ?>

<div class="form-section">
<h3>Dati Anagrafici</h3>
<div class="form-grid">
<div class="form-field">
<label>Nome <span class="required">*</span></label>
<input type="text" name="first_name" required maxlength="100">
</div>
<div class="form-field">
<label>Cognome <span class="required">*</span></label>
<input type="text" name="last_name" required maxlength="100">
</div>
</div>
<div class="form-grid">
<div class="form-field">
<label>Telefono</label>
<input type="tel" name="mobile" maxlength="20" placeholder="+39 123 456 7890">
</div>
<div class="form-field">
<label>Status</label>
<select name="status">
<option value="Active" selected>Active</option>
<option value="Inactive">Inactive</option>
</select>
</div>
</div>
<div class="form-grid">
<div class="form-field">
<label>Data Inserimento</label>
<input type="date" name="inserted_at" value="<?= date('Y-m-d') ?>">
</div>
<div></div>
</div>
<div class="form-grid">
<div class="form-field" style="grid-column:1/-1">
<label>Ruoli</label>
<div style="display:flex;flex-wrap:wrap;gap:1rem;padding:1rem 0">
<?php
$allRoles = [
    'broker' => 'Broker',
    'broker_manager' => 'Broker Manager',
    'legale_rappresentante' => 'Legale Rappresentante',
    'preposto' => 'Preposto',
    'global_luxury' => 'Global Luxury'
];
foreach ($allRoles as $roleKey => $roleLabel):
?>
<label class="role-checkbox">
<input type="checkbox" name="roles[]" value="<?= $roleKey ?>">
<span><?= $roleLabel ?></span>
</label>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

<div class="form-section">
<h3>Email & Microsoft 365</h3>
<div class="form-grid">
<div class="form-field">
<label>Email Aziendale</label>
<input type="email" name="email_corporate" maxlength="255" placeholder="nome.cognome@coldwellbanker.it">
</div>
<div class="form-field">
<label>Email Personale</label>
<input type="email" name="email_personal" maxlength="255">
</div>
</div>
<div class="form-grid">
<div class="form-field">
<label>Tipo Account</label>
<select name="m365_account_type">
<option value="agente" selected>👤 Agente</option>
<option value="agenzia">🏢 Agenzia</option>
<option value="servizio">⚙️ Servizio</option>
<option value="master">⭐ Master</option>
</select>
</div>
<div class="form-field">
<label>Piano M365</label>
<select name="m365_plan">
<option value="">Nessun piano</option>
<option value="Starter">Starter</option>
<option value="Business Basic">Business Basic</option>
<option value="Business Standard">Business Standard</option>
<option value="Business Premium">Business Premium</option>
<option value="Shared Mailbox">Shared Mailbox</option>
</select>
</div>
</div>
<div class="form-grid">
<div class="form-field">
<label>Data Attivazione Email</label>
<input type="date" name="email_activation_date">
</div>
<div class="form-field">
<label>Data Scadenza Email</label>
<input type="date" name="email_expiry_date">
</div>
</div>
</div>

<div class="form-section">
<h3>Note</h3>
<div class="form-field">
<label>Note Aggiuntive</label>
<textarea name="notes" placeholder="Informazioni aggiuntive sull'agente..."></textarea>
</div>
</div>

<div class="form-actions">
<a href="<?= $agency ? 'agenzia_detail.php?code=' . urlencode($agency['code']) . '#tab-agenti' : 'agenti.php' ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Crea Agente</button>
</div>

</div>
</form>

<?php if (!$agency): ?>
<script>
const agencySearch = document.getElementById('agencySearch');
const agencyIdInput = document.getElementById('agencyIdInput');
const agencyPreview = document.getElementById('agencyPreview');
const agenciesData = <?= json_encode(array_map(function($ag) {
    return ['id' => $ag['id'], 'code' => $ag['code'], 'name' => $ag['name']];
}, $allAgencies)) ?>;

agencySearch.addEventListener('input', function() {
    const value = this.value.trim().toUpperCase();
    
    // Cerca per codice esatto o nome
    const found = agenciesData.find(ag => 
        ag.code.toUpperCase() === value || 
        ag.code.toUpperCase().includes(value)
    );
    
    if (found) {
        agencyIdInput.value = found.id;
        agencyPreview.textContent = '✅ ' + found.code + ' - ' + found.name;
        agencyPreview.style.display = 'block';
        agencyPreview.style.background = '#D1FAE5';
        agencyPreview.style.color = '#065F46';
    } else {
        agencyIdInput.value = '';
        if (value.length > 0) {
            agencyPreview.textContent = '❌ Agenzia non trovata';
            agencyPreview.style.display = 'block';
            agencyPreview.style.background = '#FEE2E2';
            agencyPreview.style.color = '#991B1B';
        } else {
            agencyPreview.style.display = 'none';
        }
    }
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

// Solo admin e editor possono modificare
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenti.php');
    exit;
}

$pageTitle = "Modifica Agente - CRM Coldwell Banker";
$pdo = getDB();

$id = $_GET['id'] ?? '';

if (!$id) {
    header('Location: agenti.php');
    exit;
}

// Gestione POST - salva modifiche
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Carica dati vecchi prima della modifica
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql = "UPDATE agents SET 
            agency_id = :agency_id,
            first_name = :first_name,
            last_name = :last_name,
            mobile = :mobile,
            email_corporate = :email_corporate,
            email_personal = :email_personal,
            m365_plan = :m365_plan,
            email_activation_date = :email_activation_date,
            email_expiry_date = :email_expiry_date,
            email_disabled_date = :email_disabled_date,
            role = :role,
            status = :status,
            notes = :notes
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'agency_id' => $_POST['agency_id'] ?: null,
        'first_name' => $_POST['first_name'] ?: null,
        'last_name' => $_POST['last_name'] ?: null,
        'mobile' => $_POST['mobile'] ?: null,
        'email_corporate' => $_POST['email_corporate'] ?: null,
        'email_personal' => $_POST['email_personal'] ?: null,
        'm365_plan' => $_POST['m365_plan'] ?: null,
        'email_activation_date' => $_POST['email_activation_date'] ?: null,
        'email_expiry_date' => $_POST['email_expiry_date'] ?: null,
        'email_disabled_date' => $_POST['email_disabled_date'] ?: null,
        'role' => $_POST['role'] ?: null,
        'status' => $_POST['status'],
        'notes' => $_POST['notes'] ?: null,
        'id' => $id
    ]);
    
    // Log modifiche
    $newData = [
        'agency_id' => $_POST['agency_id'] ?: null,
        'first_name' => $_POST['first_name'] ?: null,
        'last_name' => $_POST['last_name'] ?: null,
        'mobile' => $_POST['mobile'] ?: null,
        'email_corporate' => $_POST['email_corporate'] ?: null,
        'email_personal' => $_POST['email_personal'] ?: null,
        'm365_plan' => $_POST['m365_plan'] ?: null,
        'email_activation_date' => $_POST['email_activation_date'] ?: null,
        'email_expiry_date' => $_POST['email_expiry_date'] ?: null,
        'email_disabled_date' => $_POST['email_disabled_date'] ?: null,
        'role' => $_POST['role'] ?: null,
        'status' => $_POST['status'],
        'notes' => $_POST['notes'] ?: null
    ];
    
    $changes = getChangedFields($oldData, $newData);
    
    if (!empty($changes)) {
        $userId = $_SESSION['crm_user']['id'] ?? null;
        if ($userId) {
            logAudit(
                $pdo,
                $userId,
                $_SESSION['crm_user']['email'] ?? 'unknown',
                'agents',
                $oldData['id'],
                'UPDATE',
                $changes
            );
        }
    }
    
    header("Location: agente_detail.php?id=" . urlencode($id) . "&success=1");
    exit();
}

// Carica dati agente
$stmt = $pdo->prepare("SELECT * FROM agents WHERE id = :id");
$stmt->execute(['id' => $id]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: agenti.php');
    exit;
}

// Carica lista agenzie per dropdown
$agencies = $pdo->query("SELECT id, code, name FROM agencies WHERE status != 'Prospect' ORDER BY name ASC")->fetchAll();

require_once 'header.php';
?>

<style>
.edit-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left{display:flex;align-items:center;gap:1.5rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agent-name{color:var(--cb-bright-blue);font-weight:600;font-size:1rem}
.form-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid var(--cb-bright-blue);padding:2rem}
.form-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid #F3F4F6}
.form-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.form-section h3{font-size:1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field input,.form-field select,.form-field textarea{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
</style>

<div class="edit-header">
<div class="header-left">
<a href="agente_detail.php?id=<?= $agent['id'] ?>" class="back-btn">← Torna</a>
<div>
<h1 class="page-title">✏️ Modifica Agente</h1>
<div class="agent-name"><?= htmlspecialchars($agent['full_name']) ?></div>
</div>
</div>
</div>

<form method="POST">
<div class="form-card">

<div class="form-section">
<h3>Anagrafica</h3>
<div class="form-grid">
<div class="form-field">
<label>Agenzia</label>
<select name="agency_id">
<option value="">Nessuna agenzia</option>
<?php foreach($agencies as $ag): ?>
<option value="<?= $ag['id'] ?>" <?= $agent['agency_id'] == $ag['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($ag['code']) ?> - <?= htmlspecialchars($ag['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-field">
<label>Nome</label>
<input type="text" name="first_name" value="<?= htmlspecialchars($agent['first_name'] ?: '') ?>">
</div>
<div class="form-field">
<label>Cognome</label>
<input type="text" name="last_name" value="<?= htmlspecialchars($agent['last_name'] ?: '') ?>">
</div>
<div class="form-field">
<label>Ruolo</label>
<input type="text" name="role" value="<?= htmlspecialchars($agent['role'] ?: '') ?>" placeholder="es. Agente, Team Leader">
</div>
<div class="form-field">
<label>Status</label>
<select name="status">
<option value="Active" <?= $agent['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
<option value="Inactive" <?= $agent['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
</select>
</div>
</div>
</div>

<div class="form-section">
<h3>Contatti</h3>
<div class="form-grid">
<div class="form-field">
<label>Cellulare</label>
<input type="tel" name="mobile" value="<?= htmlspecialchars($agent['mobile'] ?: '') ?>">
</div>
<div class="form-field">
<label>Email Aziendale</label>
<input type="email" name="email_corporate" value="<?= htmlspecialchars($agent['email_corporate'] ?: '') ?>">
</div>
<div class="form-field">
<label>Email Personale</label>
<input type="email" name="email_personal" value="<?= htmlspecialchars($agent['email_personal'] ?: '') ?>">
</div>
</div>
</div>

<div class="form-section">
<h3>Microsoft 365</h3>
<div class="form-grid">
<div class="form-field">
<label>Piano M365</label>
<input type="text" name="m365_plan" value="<?= htmlspecialchars($agent['m365_plan'] ?: '') ?>" placeholder="es. Business Basic, Business Standard">
</div>
<div class="form-field">
<label>Data Attivazione Email</label>
<input type="date" name="email_activation_date" value="<?= $agent['email_activation_date'] ?>">
</div>
<div class="form-field">
<label>Data Scadenza Email</label>
<input type="date" name="email_expiry_date" value="<?= $agent['email_expiry_date'] ?>">
</div>
<div class="form-field">
<label>Data Disabilitazione Email</label>
<input type="date" name="email_disabled_date" value="<?= $agent['email_disabled_date'] ?>">
</div>
</div>
</div>

<div class="form-section">
<h3>Note</h3>
<div class="form-field">
<textarea name="notes" rows="4" style="resize:vertical"><?= htmlspecialchars($agent['notes'] ?: '') ?></textarea>
</div>
</div>

<div class="form-actions">
<a href="agente_detail.php?id=<?= $agent['id'] ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Salva Modifiche</button>
</div>

</div>
</form>

<?php require_once 'footer.php'; ?>

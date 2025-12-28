<?php
require_once 'check_auth.php';
require_once 'config/database.php';

// Solo admin e editor possono modificare
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Modifica Agenzia - CRM Coldwell Banker";
$pdo = getDB();

$code = $_GET['code'] ?? '';

if (!$code) {
    header('Location: agenzie.php');
    exit;
}

// Gestione POST - salva modifiche
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE agencies SET 
            name = :name,
            type = :type,
            broker_manager = :broker_manager,
            broker_mobile = :broker_mobile,
            legal_representative = :legal_representative,
            company_name = :company_name,
            rea = :rea,
            address = :address,
            city = :city,
            province = :province,
            zip_code = :zip_code,
            email = :email,
            phone = :phone,
            pec = :pec,
            website = :website,
            vat_number = :vat_number,
            tax_code = :tax_code,
            sdi_code = :sdi_code,
            activation_date = :activation_date,
            contract_expiry = :contract_expiry,
            contract_duration_years = :contract_duration_years,
            tech_fee = :tech_fee,
            closed_date = :closed_date,
            status = :status
            WHERE code = :code";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name' => $_POST['name'],
        'type' => $_POST['type'],
        'broker_manager' => $_POST['broker_manager'] ?: null,
        'broker_mobile' => $_POST['broker_mobile'] ?: null,
        'legal_representative' => $_POST['legal_representative'] ?: null,
        'company_name' => $_POST['company_name'] ?: null,
        'rea' => $_POST['rea'] ?: null,
        'address' => $_POST['address'] ?: null,
        'city' => $_POST['city'] ?: null,
        'province' => $_POST['province'] ?: null,
        'zip_code' => $_POST['zip_code'] ?: null,
        'email' => $_POST['email'] ?: null,
        'phone' => $_POST['phone'] ?: null,
        'pec' => $_POST['pec'] ?: null,
        'website' => $_POST['website'] ?: null,
        'vat_number' => $_POST['vat_number'] ?: null,
        'tax_code' => $_POST['tax_code'] ?: null,
        'sdi_code' => $_POST['sdi_code'] ?: null,
        'activation_date' => $_POST['activation_date'] ?: null,
        'contract_expiry' => $_POST['contract_expiry'] ?: null,
        'contract_duration_years' => $_POST['contract_duration_years'] ?: null,
        'tech_fee' => $_POST['tech_fee'] ?: null,
        'closed_date' => $_POST['closed_date'] ?: null,
        'status' => $_POST['status'],
        'code' => $code
    ]);
    
    // Gestione servizi - prima cancella tutti
    $stmtDel = $pdo->prepare("DELETE FROM agency_services WHERE agency_id = (SELECT id FROM agencies WHERE code = :code)");
    $stmtDel->execute(['code' => $code]);
    
    // Poi inserisci quelli selezionati
    if (!empty($_POST['services'])) {
        $stmtIns = $pdo->prepare("INSERT INTO agency_services (agency_id, service_name, is_active) VALUES ((SELECT id FROM agencies WHERE code = :code), :service, 1)");
        foreach ($_POST['services'] as $service) {
            $stmtIns->execute(['code' => $code, 'service' => $service]);
        }
    }
    
    header("Location: agenzia_detail.php?code=" . urlencode($code) . "&success=1");
    exit;
}

// Carica dati agenzia
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = :code");
$stmt->execute(['code' => $code]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

// Carica servizi dell'agenzia
$stmt = $pdo->prepare("SELECT service_name FROM agency_services WHERE agency_id = :agency_id AND is_active = 1");
$stmt->execute(['agency_id' => $agency['id']]);
$activeServices = array_column($stmt->fetchAll(), 'service_name');

require_once 'header.php';
?>

<style>
.edit-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.header-left{display:flex;align-items:center;gap:1.5rem}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.agency-code{color:var(--cb-bright-blue);font-weight:600;font-size:1rem}
.form-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:6px solid var(--cb-bright-blue);padding:2rem}
.form-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid #F3F4F6}
.form-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.form-section h3{font-size:1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field input,.form-field select{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus,.form-field select:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-field label input[type="checkbox"]{margin-right:.5rem}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
</style>

<div class="edit-header">
<div class="header-left">
<a href="agenzia_detail.php?code=<?= urlencode($agency['code']) ?>" class="back-btn">← Torna</a>
<div>
<h1 class="page-title">✏️ Modifica Agenzia</h1>
<div class="agency-code"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
</div>
</div>
</div>

<form method="POST">
<div class="form-card">

<div class="form-section">
<h3>Anagrafica</h3>
<div class="form-grid">
<div class="form-field">
<label>Nome Agenzia *</label>
<input type="text" name="name" value="<?= htmlspecialchars($agency['name']) ?>" required>
</div>
<div class="form-field">
<label>Tipo Agenzia</label>
<select name="type">
<option value="Standard" <?= $agency['type'] === 'Standard' ? 'selected' : '' ?>>Standard</option>
<option value="Satellite" <?= $agency['type'] === 'Satellite' ? 'selected' : '' ?>>Satellite</option>
<option value="Master" <?= $agency['type'] === 'Master' ? 'selected' : '' ?>>Master</option>
<option value="Commercial" <?= $agency['type'] === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
</select>
</div>
<div class="form-field">
<label>Status</label>
<select name="status">
<option value="Active" <?= $agency['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
<option value="Opening" <?= $agency['status'] === 'Opening' ? 'selected' : '' ?>>Opening</option>
<option value="Closed" <?= $agency['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
<option value="Prospect" <?= $agency['status'] === 'Prospect' ? 'selected' : '' ?>>Prospect</option>
</select>
</div>
<div class="form-field">
<label>Broker Manager</label>
<input type="text" name="broker_manager" value="<?= htmlspecialchars($agency['broker_manager'] ?: '') ?>">
</div>
<div class="form-field">
<label>Cellulare Broker</label>
<input type="tel" name="broker_mobile" value="<?= htmlspecialchars($agency['broker_mobile'] ?: '') ?>">
</div>
<div class="form-field">
<label>Rappresentante Legale</label>
<input type="text" name="legal_representative" value="<?= htmlspecialchars($agency['legal_representative'] ?: '') ?>">
</div>
<div class="form-field">
<label>Ragione Sociale</label>
<input type="text" name="company_name" value="<?= htmlspecialchars($agency['company_name'] ?: '') ?>">
</div>
<div class="form-field">
<label>REA</label>
<input type="text" name="rea" value="<?= htmlspecialchars($agency['rea'] ?: '') ?>">
</div>
</div>
</div>

<div class="form-section">
<h3>Sede</h3>
<div class="form-grid">
<div class="form-field" style="grid-column:1/-1">
<label>Indirizzo</label>
<input type="text" name="address" value="<?= htmlspecialchars($agency['address'] ?: '') ?>">
</div>
<div class="form-field">
<label>Città</label>
<input type="text" name="city" value="<?= htmlspecialchars($agency['city'] ?: '') ?>">
</div>
<div class="form-field">
<label>Provincia</label>
<input type="text" name="province" value="<?= htmlspecialchars($agency['province'] ?: '') ?>" maxlength="2">
</div>
<div class="form-field">
<label>CAP</label>
<input type="text" name="zip_code" value="<?= htmlspecialchars($agency['zip_code'] ?: '') ?>" maxlength="10">
</div>
</div>
</div>

<div class="form-section">
<h3>Contatti</h3>
<div class="form-grid">
<div class="form-field">
<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($agency['email'] ?: '') ?>">
</div>
<div class="form-field">
<label>Telefono</label>
<input type="tel" name="phone" value="<?= htmlspecialchars($agency['phone'] ?: '') ?>">
</div>
<div class="form-field">
<label>PEC</label>
<input type="email" name="pec" value="<?= htmlspecialchars($agency['pec'] ?: '') ?>">
</div>
<div class="form-field">
<label>Sito Web</label>
<input type="url" name="website" value="<?= htmlspecialchars($agency['website'] ?: '') ?>">
</div>
</div>
</div>

<div class="form-section">
<h3>Dati Fiscali</h3>
<div class="form-grid">
<div class="form-field">
<label>Partita IVA</label>
<input type="text" name="vat_number" value="<?= htmlspecialchars($agency['vat_number'] ?: '') ?>">
</div>
<div class="form-field">
<label>Codice Fiscale</label>
<input type="text" name="tax_code" value="<?= htmlspecialchars($agency['tax_code'] ?: '') ?>">
</div>
<div class="form-field">
<label>Codice SDI</label>
<input type="text" name="sdi_code" value="<?= htmlspecialchars($agency['sdi_code'] ?: '') ?>">
</div>
</div>
</div>

<div class="form-section">
<h3>Servizi</h3>
<div class="form-grid">
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="cb_suite" <?= in_array('cb_suite', $activeServices) ? 'checked' : '' ?>>
CB Suite
</label>
</div>
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="canva" <?= in_array('canva', $activeServices) ? 'checked' : '' ?>>
Canva
</label>
</div>
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="regold" <?= in_array('regold', $activeServices) ? 'checked' : '' ?>>
Regold
</label>
</div>
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="james_edition" <?= in_array('james_edition', $activeServices) ? 'checked' : '' ?>>
James Edition
</label>
</div>
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="docudrop" <?= in_array('docudrop', $activeServices) ? 'checked' : '' ?>>
Docudrop
</label>
</div>
<div class="form-field">
<label>
<input type="checkbox" name="services[]" value="unique" <?= in_array('unique', $activeServices) ? 'checked' : '' ?>>
Unique
</label>
</div>
</div>
</div>

<div class="form-section">
<h3>Dati Contrattuali</h3>
<div class="form-grid">
<div class="form-field">
<label>Data Attivazione</label>
<input type="date" name="activation_date" value="<?= $agency['activation_date'] ?>">
</div>
<div class="form-field">
<label>Scadenza Contratto</label>
<input type="date" name="contract_expiry" value="<?= $agency['contract_expiry'] ?>">
</div>
<div class="form-field">
<label>Durata Contratto (anni)</label>
<input type="number" name="contract_duration_years" value="<?= $agency['contract_duration_years'] ?: '' ?>" min="0">
</div>
<div class="form-field">
<label>Tech Fee (€)</label>
<input type="number" name="tech_fee" value="<?= $agency['tech_fee'] ?: '' ?>" step="0.01" min="0">
</div>
<div class="form-field">
<label>Data Chiusura</label>
<input type="date" name="closed_date" value="<?= $agency['closed_date'] ?>">
</div>
</div>
</div>

<div class="form-actions">
<a href="agenzia_detail.php?code=<?= urlencode($agency['code']) ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Salva Modifiche</button>
</div>

</div>
</form>

<?php require_once 'footer.php'; ?>

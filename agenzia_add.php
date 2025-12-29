<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

// Solo admin e editor possono creare
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Nuova Agenzia - CRM Coldwell Banker";
$pdo = getDB();

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica che il codice non esista già
    $stmt = $pdo->prepare("SELECT id FROM agencies WHERE code = :code");
    $stmt->execute(['code' => $_POST['code']]);
    if ($stmt->fetch()) {
        $error = "Il codice agenzia esiste già!";
    } else {
        
        $sql = "INSERT INTO agencies (
            code, name, type, broker_manager, broker_mobile, legal_representative,
            company_name, rea, address, city, province, zip_code, email, phone,
            pec, website, vat_number, tax_code, sdi_code, activation_date,
            contract_expiry, contract_duration_years, tech_fee, closed_date, status
        ) VALUES (
            :code, :name, :type, :broker_manager, :broker_mobile, :legal_representative,
            :company_name, :rea, :address, :city, :province, :zip_code, :email, :phone,
            :pec, :website, :vat_number, :tax_code, :sdi_code, :activation_date,
            :contract_expiry, :contract_duration_years, :tech_fee, :closed_date, :status
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'code' => $_POST['code'],
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
            'status' => $_POST['status']
        ]);
        
        $agency_id = $pdo->lastInsertId();
        
        // Log
        logAudit($pdo, $_SESSION['crm_user']['id'] ?? 0, $_SESSION['crm_user']['email'], 'agencies', $agency_id, 'INSERT', []);
        
        header("Location: agenzia_detail.php?code=" . urlencode($_POST['code']) . "&success=created");
        exit;
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
.form-section h3{font-size:1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1.5rem 0;text-transform:uppercase;letter-spacing:.05em}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem}
.form-field{display:flex;flex-direction:column;gap:.5rem}
.form-field label{font-size:.875rem;font-weight:600;color:var(--cb-gray)}
.form-field input,.form-field select{padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus,.form-field select:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
.error-message{background:#FEE2E2;border-left:4px solid #EF4444;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#991B1B}
</style>

<div class="page-header">
<h1 class="page-title">➕ Nuova Agenzia</h1>
<a href="agenzie.php" class="back-btn">← Torna</a>
</div>

<?php if(isset($error)): ?>
<div class="error-message">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<div class="form-card">

<div class="form-section">
<h3>Anagrafica</h3>
<div class="form-grid">
<div class="form-field">
<label>Codice Agenzia *</label>
<input type="text" name="code" placeholder="es. CBI001" required>
</div>
<div class="form-field">
<label>Nome Agenzia *</label>
<input type="text" name="name" required>
</div>
<div class="form-field">
<label>Tipo Agenzia *</label>
<select name="type" required>
<option value="Standard">Standard</option>
<option value="Satellite">Satellite</option>
<option value="Master">Master</option>
<option value="Commercial">Commercial</option>
</select>
</div>
<div class="form-field">
<label>Broker Manager</label>
<input type="text" name="broker_manager">
</div>
<div class="form-field">
<label>Cellulare Broker</label>
<input type="tel" name="broker_mobile">
</div>
<div class="form-field">
<label>Status *</label>
<select name="status" required>
<option value="Active">Active</option>
<option value="Opening">Opening</option>
<option value="In Onboarding">In Onboarding</option>
<option value="Closed">Closed</option>
</select>
</div>
</div>
</div>

<div class="form-section">
<h3>Dati Legali</h3>
<div class="form-grid">
<div class="form-field">
<label>Rappresentante Legale</label>
<input type="text" name="legal_representative">
</div>
<div class="form-field">
<label>Ragione Sociale</label>
<input type="text" name="company_name">
</div>
<div class="form-field">
<label>REA</label>
<input type="text" name="rea">
</div>
<div class="form-field">
<label>Partita IVA</label>
<input type="text" name="vat_number">
</div>
<div class="form-field">
<label>Codice Fiscale</label>
<input type="text" name="tax_code">
</div>
<div class="form-field">
<label>Codice SDI</label>
<input type="text" name="sdi_code">
</div>
</div>
</div>

<div class="form-section">
<h3>Contatti e Sede</h3>
<div class="form-grid">
<div class="form-field">
<label>Indirizzo</label>
<input type="text" name="address">
</div>
<div class="form-field">
<label>Città</label>
<input type="text" name="city">
</div>
<div class="form-field">
<label>Provincia</label>
<input type="text" name="province" maxlength="2">
</div>
<div class="form-field">
<label>CAP</label>
<input type="text" name="zip_code">
</div>
<div class="form-field">
<label>Email</label>
<input type="email" name="email">
</div>
<div class="form-field">
<label>Telefono</label>
<input type="tel" name="phone">
</div>
<div class="form-field">
<label>PEC</label>
<input type="email" name="pec">
</div>
<div class="form-field">
<label>Sito Web</label>
<input type="url" name="website" placeholder="https://">
</div>
</div>
</div>

<div class="form-section">
<h3>Contratto</h3>
<div class="form-grid">
<div class="form-field">
<label>Data Attivazione</label>
<input type="date" name="activation_date">
</div>
<div class="form-field">
<label>Scadenza Contratto</label>
<input type="date" name="contract_expiry">
</div>
<div class="form-field">
<label>Durata Contratto (anni)</label>
<input type="number" name="contract_duration_years" min="1" max="10">
</div>
<div class="form-field">
<label>Tech Fee (€)</label>
<input type="number" name="tech_fee" step="0.01" min="0">
</div>
<div class="form-field">
<label>Data Chiusura</label>
<input type="date" name="closed_date">
</div>
</div>
</div>

<div class="form-actions">
<a href="agenzie.php" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Crea Agenzia</button>
</div>

</div>
</form>

<?php require_once 'footer.php'; ?>

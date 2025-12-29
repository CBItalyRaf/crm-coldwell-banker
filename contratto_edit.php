<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'log_functions.php';

// Solo admin e editor
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Modifica Contratto - CRM Coldwell Banker";
$pdo = getDB();

$code = $_GET['code'] ?? '';

if (!$code) {
    header('Location: agenzie.php');
    exit;
}

// Carica agenzia
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = :code");
$stmt->execute(['code' => $code]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

// Gestione POST - Salva contratto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. Aggiorna Tech Fee in agencies
        $stmt = $pdo->prepare("UPDATE agencies SET tech_fee = :tech_fee WHERE id = :id");
        $stmt->execute([
            'tech_fee' => $_POST['tech_fee'] ?: 0,
            'id' => $agency['id']
        ]);
        
        // 2. Cancella tutti i servizi del contratto
        $stmt = $pdo->prepare("DELETE FROM agency_contract_services WHERE agency_id = :agency_id");
        $stmt->execute(['agency_id' => $agency['id']]);
        
        // 3. Inserisci servizi OBBLIGATORI
        if (!empty($_POST['mandatory_services'])) {
            $stmtIns = $pdo->prepare("INSERT INTO agency_contract_services 
                (agency_id, service_id, is_mandatory, custom_price, notes) 
                VALUES (:agency_id, :service_id, 1, NULL, :notes)");
            
            foreach ($_POST['mandatory_services'] as $serviceId) {
                $stmtIns->execute([
                    'agency_id' => $agency['id'],
                    'service_id' => $serviceId,
                    'notes' => $_POST['mandatory_notes_' . $serviceId] ?? null
                ]);
            }
        }
        
        // 4. Inserisci servizi FACOLTATIVI (solo quelli attivi in agency_services)
        if (!empty($_POST['optional_services'])) {
            $stmtIns = $pdo->prepare("INSERT INTO agency_contract_services 
                (agency_id, service_id, is_mandatory, custom_price, notes) 
                VALUES (:agency_id, :service_id, 0, :custom_price, :notes)");
            
            foreach ($_POST['optional_services'] as $serviceId) {
                $customPrice = $_POST['optional_price_' . $serviceId] ?? null;
                
                $stmtIns->execute([
                    'agency_id' => $agency['id'],
                    'service_id' => $serviceId,
                    'custom_price' => $customPrice ?: null,
                    'notes' => $_POST['optional_notes_' . $serviceId] ?? null
                ]);
            }
        }
        
        // Log
        $userId = $_SESSION['crm_user']['id'] ?? null;
        if ($userId) {
            logAudit($pdo, $userId, $_SESSION['crm_user']['email'] ?? 'unknown', 'agencies', $agency['id'], 'UPDATE', ['context' => 'contratto']);
        }
        
        $pdo->commit();
        header("Location: agenzia_detail.php?code=" . urlencode($code) . "&success=1#tab-contrattuale");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore in contratto_edit: " . $e->getMessage());
        die("Errore durante il salvataggio: " . $e->getMessage());
    }
}

// Carica tutti i servizi master
$stmt = $pdo->query("SELECT * FROM services_master WHERE is_active = 1 ORDER BY display_order ASC, service_name ASC");
$allServices = $stmt->fetchAll();

// Carica servizi già nel contratto
$stmt = $pdo->prepare("SELECT * FROM agency_contract_services WHERE agency_id = :agency_id");
$stmt->execute(['agency_id' => $agency['id']]);
$existingContract = [];
foreach ($stmt->fetchAll() as $row) {
    $existingContract[$row['service_id']] = $row;
}

// Carica servizi ATTIVI in agency_services (per sapere quali facoltativi mostrare)
$stmt = $pdo->prepare("
    SELECT DISTINCT sm.id, sm.service_name 
    FROM agency_services ags
    JOIN services_master sm ON sm.service_name = (
        CASE ags.service_name
            WHEN 'cb_suite' THEN 'CB Suite'
            WHEN 'canva' THEN 'Canva Pro'
            WHEN 'regold' THEN 'Regold'
            WHEN 'james_edition' THEN 'James Edition'
            WHEN 'docudrop' THEN 'Docudrop'
            WHEN 'unique' THEN 'Unique Estates'
            WHEN 'casella_mail_agenzia' THEN 'Casella Mail Agenzia'
            WHEN 'euromq' THEN 'EuroMq'
            WHEN 'gestim' THEN 'Gestim'
        END
    )
    WHERE ags.agency_id = :agency_id AND ags.is_active = 1
");
$stmt->execute(['agency_id' => $agency['id']]);
$activeServicesIds = array_column($stmt->fetchAll(), 'id');

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
.form-field{margin-bottom:1.5rem}
.form-field label{display:block;font-size:.875rem;font-weight:600;color:var(--cb-gray);margin-bottom:.5rem}
.form-field input{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.form-field input:focus{outline:none;border-color:var(--cb-bright-blue)}
.service-item{background:var(--bg);padding:1rem;border-radius:8px;margin-bottom:.75rem;border:2px solid transparent;transition:all .2s}
.service-item:hover{border-color:#E5E7EB}
.service-item.mandatory{background:#D1FAE5;border-color:#10B981}
.service-item.optional{background:#DBEAFE;border-color:#3B82F6}
.service-header{display:flex;align-items:center;gap:1rem;margin-bottom:.75rem}
.service-header input[type="checkbox"]{width:20px;height:20px;cursor:pointer}
.service-name{font-weight:600;color:var(--cb-midnight);flex:1}
.service-price{font-weight:600;color:var(--cb-bright-blue);margin-left:auto}
.service-fields{display:grid;grid-template-columns:200px 1fr;gap:1rem;margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(0,0,0,.1)}
.service-fields input{padding:.5rem;font-size:.9rem}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
.help-text{font-size:.85rem;color:var(--cb-gray);margin-top:.5rem;font-style:italic}
</style>

<div class="page-header">
<div>
<h1 class="page-title">📋 Modifica Contratto</h1>
<div style="color:var(--cb-bright-blue);font-weight:600;margin-top:.5rem"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
</div>
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>#tab-contrattuale" class="back-btn">← Torna</a>
</div>

<form method="POST">
<div class="form-card">

<div class="form-section">
<h3>Tech Fee (Forfait Mensile)</h3>
<div class="form-field">
<label>Tech Fee (€)</label>
<input type="number" name="tech_fee" step="0.01" min="0" value="<?= $agency['tech_fee'] ?? 0 ?>" required>
<div class="help-text">💡 Importo forfettario che copre tutti i servizi obbligatori</div>
</div>
</div>

<div class="form-section">
<h3>Servizi Obbligatori <span style="font-weight:400;font-size:.9rem;color:var(--cb-gray)">(coperti dalla Tech Fee)</span></h3>
<div class="help-text" style="margin-bottom:1rem">✅ Seleziona i servizi inclusi nel pacchetto contrattuale</div>

<?php foreach ($allServices as $service): 
$isChecked = isset($existingContract[$service['id']]) && $existingContract[$service['id']]['is_mandatory'] == 1;
?>
<div class="service-item <?= $isChecked ? 'mandatory' : '' ?>" id="mandatory-item-<?= $service['id'] ?>">
<div class="service-header">
<input type="checkbox" name="mandatory_services[]" value="<?= $service['id'] ?>" 
    <?= $isChecked ? 'checked' : '' ?>
    onchange="toggleMandatory(<?= $service['id'] ?>)">
<div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
<div class="service-price">€ <?= number_format($service['default_price'], 2, ',', '.') ?></div>
</div>
<div class="service-fields">
<label style="font-size:.85rem;font-weight:600;color:var(--cb-gray)">Note</label>
<input type="text" name="mandatory_notes_<?= $service['id'] ?>" 
    placeholder="Note aggiuntive..." 
    value="<?= isset($existingContract[$service['id']]) ? htmlspecialchars($existingContract[$service['id']]['notes']) : '' ?>">
</div>
</div>
<?php endforeach; ?>
</div>

<div class="form-section">
<h3>Servizi Facoltativi <span style="font-weight:400;font-size:.9rem;color:var(--cb-gray)">(costi aggiuntivi)</span></h3>
<div class="help-text" style="margin-bottom:1rem">💰 Gestisci solo i servizi che sono già ATTIVI nel tab "Servizi"</div>

<?php 
$hasOptional = false;
foreach ($allServices as $service): 
    // Mostra solo se è attivo in agency_services
    if (!in_array($service['id'], $activeServicesIds)) continue;
    $hasOptional = true;
    
    $isChecked = isset($existingContract[$service['id']]) && $existingContract[$service['id']]['is_mandatory'] == 0;
    $customPrice = isset($existingContract[$service['id']]) ? $existingContract[$service['id']]['custom_price'] : $service['default_price'];
?>
<div class="service-item <?= $isChecked ? 'optional' : '' ?>" id="optional-item-<?= $service['id'] ?>">
<div class="service-header">
<input type="checkbox" name="optional_services[]" value="<?= $service['id'] ?>" 
    <?= $isChecked ? 'checked' : '' ?>
    onchange="toggleOptional(<?= $service['id'] ?>)">
<div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
<div class="service-price">Default: € <?= number_format($service['default_price'], 2, ',', '.') ?></div>
</div>
<div class="service-fields">
<input type="number" name="optional_price_<?= $service['id'] ?>" 
    step="0.01" min="0" 
    placeholder="Prezzo custom (€)" 
    value="<?= $customPrice ?>">
<input type="text" name="optional_notes_<?= $service['id'] ?>" 
    placeholder="Note aggiuntive..." 
    value="<?= isset($existingContract[$service['id']]) ? htmlspecialchars($existingContract[$service['id']]['notes']) : '' ?>">
</div>
</div>
<?php endforeach; ?>

<?php if (!$hasOptional): ?>
<div style="text-align:center;padding:2rem;color:var(--cb-gray);background:var(--bg);border-radius:8px">
<div style="font-size:2rem;margin-bottom:.5rem">📦</div>
<p>Nessun servizio facoltativo attivo</p>
<p style="font-size:.85rem;margin-top:.5rem">Attiva i servizi nel tab "Servizi" per gestirli qui come facoltativi</p>
</div>
<?php endif; ?>
</div>

<div class="form-actions">
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>#tab-contrattuale" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Salva Contratto</button>
</div>

</div>
</form>

<script>
function toggleMandatory(id) {
    const item = document.getElementById('mandatory-item-' + id);
    const checkbox = item.querySelector('input[type="checkbox"]');
    
    if (checkbox.checked) {
        item.classList.add('mandatory');
    } else {
        item.classList.remove('mandatory');
    }
}

function toggleOptional(id) {
    const item = document.getElementById('optional-item-' + id);
    const checkbox = item.querySelector('input[type="checkbox"]');
    
    if (checkbox.checked) {
        item.classList.add('optional');
    } else {
        item.classList.remove('optional');
    }
}
</script>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: agenzie.php');
    exit;
}

$pdo = getDB();

// Carica agenzia
$stmt = $pdo->prepare("SELECT * FROM agencies WHERE code = :code");
$stmt->execute(['code' => $code]);
$agency = $stmt->fetch();

if (!$agency) {
    header('Location: agenzie.php');
    exit;
}

$pageTitle = "Gestisci Servizi - " . $agency['name'];

// Carica tutti i servizi master
$stmtMaster = $pdo->query("SELECT * FROM services_master ORDER BY service_name");
$allServices = $stmtMaster->fetchAll();

// Carica servizi agenzia
$stmtAgency = $pdo->prepare("SELECT * FROM agency_services WHERE agency_id = :agency_id");
$stmtAgency->execute(['agency_id' => $agency['id']]);
$agencyServices = [];
foreach ($stmtAgency->fetchAll() as $svc) {
    $agencyServices[$svc['service_name']] = $svc;
}

// Mappa service_name da services_master
$serviceNameMap = [
    'CB Suite' => 'cb_suite',
    'Canva Pro' => 'canva',
    'Regold' => 'regold',
    'James Edition' => 'james_edition',
    'Docudrop' => 'docudrop',
    'Unique Estates' => 'unique'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($allServices as $service) {
        $serviceId = $service['id'];
        $serviceName = $serviceNameMap[$service['service_name']] ?? null;
        
        if (!$serviceName) continue;
        
        $isActive = isset($_POST['service_' . $serviceId]) ? 1 : 0;
        $activationDate = $_POST['activation_date_' . $serviceId] ?? null;
        $deactivationDate = $_POST['deactivation_date_' . $serviceId] ?? null;
        $customPrice = $_POST['custom_price_' . $serviceId] ?? null;
        $notes = $_POST['notes_' . $serviceId] ?? null;
        
        // Check if service exists for this agency
        if (isset($agencyServices[$serviceName])) {
            // Update existing (sempre, anche se disattivato)
            $stmt = $pdo->prepare("
                UPDATE agency_services 
                SET is_active = :is_active,
                    activation_date = :activation_date,
                    expiration_date = :expiration_date,
                    custom_price = :custom_price,
                    notes = :notes
                WHERE agency_id = :agency_id AND service_name = :service_name
            ");
            $stmt->execute([
                'is_active' => $isActive,
                'activation_date' => $activationDate ?: null,
                'expiration_date' => $deactivationDate ?: null,
                'custom_price' => $customPrice ?: null,
                'notes' => $notes ?: null,
                'agency_id' => $agency['id'],
                'service_name' => $serviceName
            ]);
        } else if ($isActive) {
            // Insert new only if active
            $stmt = $pdo->prepare("
                INSERT INTO agency_services (agency_id, service_name, is_active, activation_date, expiration_date, custom_price, notes)
                VALUES (:agency_id, :service_name, 1, :activation_date, :expiration_date, :custom_price, :notes)
            ");
            $stmt->execute([
                'agency_id' => $agency['id'],
                'service_name' => $serviceName,
                'activation_date' => $activationDate ?: null,
                'expiration_date' => $deactivationDate ?: null,
                'custom_price' => $customPrice ?: null,
                'notes' => $notes ?: null
            ]);
        }
    }
    
    header('Location: agenzia_detail.php?code=' . urlencode($code));
    exit;
}

require_once 'header.php';
?>

<style>
.service-card{background:white;padding:1.5rem;border-radius:8px;margin-bottom:1rem;border:2px solid #E5E7EB;transition:all .2s}
.service-card.active{border-color:var(--cb-bright-blue);background:#F0F9FF}
.service-header{display:flex;align-items:center;gap:1rem;margin-bottom:1rem}
.service-toggle{width:60px;height:32px;background:#E5E7EB;border-radius:16px;position:relative;cursor:pointer;transition:background .2s}
.service-toggle.active{background:var(--cb-bright-blue)}
.service-toggle::after{content:'';position:absolute;width:24px;height:24px;background:white;border-radius:50%;top:4px;left:4px;transition:left .2s}
.service-toggle.active::after{left:32px}
.service-name{font-size:1.1rem;font-weight:600;flex:1}
.service-dates{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1rem}
.form-group label{display:block;font-size:.9rem;color:var(--cb-gray);margin-bottom:.5rem}
.form-group input{width:100%;padding:.5rem;border:1px solid #E5E7EB;border-radius:4px}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 2rem;border-radius:8px;cursor:pointer;font-weight:600}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 2rem;border-radius:8px;cursor:pointer}
</style>

<div style="max-width:900px;margin:0 auto;padding:2rem">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem">
<div>
<h1 style="margin:0;margin-bottom:.5rem">⚙️ Gestisci Servizi</h1>
<p style="color:var(--cb-gray);margin:0"><?= htmlspecialchars($agency['name']) ?> (<?= htmlspecialchars($agency['code']) ?>)</p>
</div>
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>" class="btn-cancel" style="text-decoration:none;display:inline-block">← Torna all'agenzia</a>
</div>

<form method="POST">
<?php foreach ($allServices as $service): ?>
<?php 
$serviceName = $serviceNameMap[$service['service_name']] ?? null;
$agencyService = $serviceName ? ($agencyServices[$serviceName] ?? null) : null;
$isActive = $agencyService ? $agencyService['is_active'] : 0;
?>
<div class="service-card <?= $isActive ? 'active' : '' ?>" data-service="<?= $service['id'] ?>">
<div class="service-header">
<label class="service-toggle <?= $isActive ? 'active' : '' ?>" onclick="toggleService(<?= $service['id'] ?>)">
<input type="checkbox" name="service_<?= $service['id'] ?>" <?= $isActive ? 'checked' : '' ?> style="display:none">
</label>
<div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
</div>

<div class="service-dates" id="dates-<?= $service['id'] ?>" style="display:<?= $isActive ? 'grid' : 'none' ?>">
<div class="form-group">
<label>Data Attivazione</label>
<input type="date" name="activation_date_<?= $service['id'] ?>" value="<?= $agencyService['activation_date'] ?? '' ?>">
</div>
<div class="form-group">
<label>Data Scadenza</label>
<input type="date" name="deactivation_date_<?= $service['id'] ?>" value="<?= $agencyService['expiration_date'] ?? '' ?>">
</div>
<div class="form-group">
<label>Prezzo Custom (€)</label>
<input type="number" step="0.01" name="custom_price_<?= $service['id'] ?>" value="<?= $agencyService['custom_price'] ?? '' ?>" placeholder="Default: <?= number_format($service['default_price'] ?? 0, 2, ',', '.') ?>" style="width:100%;padding:.5rem;border:1px solid #E5E7EB;border-radius:4px">
</div>
<div class="form-group" style="grid-column:1/-1">
<label>Note</label>
<textarea name="notes_<?= $service['id'] ?>" rows="2" style="width:100%;padding:.5rem;border:1px solid #E5E7EB;border-radius:4px;font-family:inherit"><?= htmlspecialchars($agencyService['notes'] ?? '') ?></textarea>
</div>
</div>
</div>
<?php endforeach; ?>

<div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem">
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>" class="btn-cancel" style="text-decoration:none">Annulla</a>
<button type="submit" class="btn-save">💾 Salva Modifiche</button>
</div>
</form>
</div>

<script>
function toggleService(serviceId) {
    const card = document.querySelector(`.service-card[data-service="${serviceId}"]`);
    const toggle = card.querySelector('.service-toggle');
    const checkbox = card.querySelector('input[type="checkbox"]');
    const dates = document.getElementById(`dates-${serviceId}`);
    
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        toggle.classList.add('active');
        card.classList.add('active');
        dates.style.display = 'grid';
    } else {
        toggle.classList.remove('active');
        card.classList.remove('active');
        dates.style.display = 'none';
    }
}
</script>

<?php require_once 'footer.php'; ?>

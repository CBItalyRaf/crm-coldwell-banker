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

$pageTitle = "Gestisci Entry Fee - CRM Coldwell Banker";
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

// Gestione POST - Salva entry fee e rate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. Aggiorna Entry Fee totale in agencies
        $stmt = $pdo->prepare("UPDATE agencies SET entry_fee = :entry_fee WHERE id = :id");
        $stmt->execute([
            'entry_fee' => $_POST['entry_fee'] ?: 0,
            'id' => $agency['id']
        ]);
        
        // 2. Cancella tutte le rate esistenti
        $stmt = $pdo->prepare("DELETE FROM entry_fee_installments WHERE agency_id = :agency_id");
        $stmt->execute(['agency_id' => $agency['id']]);
        
        // 3. Inserisci nuove rate
        if (!empty($_POST['installments'])) {
            $stmtIns = $pdo->prepare("INSERT INTO entry_fee_installments 
                (agency_id, installment_number, amount, due_date, payment_date, notes) 
                VALUES (:agency_id, :installment_number, :amount, :due_date, :payment_date, :notes)");
            
            foreach ($_POST['installments'] as $index => $installment) {
                if (empty($installment['amount'])) continue; // Salta rate vuote
                
                $stmtIns->execute([
                    'agency_id' => $agency['id'],
                    'installment_number' => $index + 1,
                    'amount' => $installment['amount'] ?: 0,
                    'due_date' => $installment['due_date'] ?: null,
                    'payment_date' => $installment['payment_date'] ?: null,
                    'notes' => $installment['notes'] ?: null
                ]);
            }
        }
        
        // Log
        $userId = $_SESSION['crm_user']['id'] ?? null;
        if ($userId) {
            logAudit($pdo, $userId, $_SESSION['crm_user']['email'] ?? 'unknown', 'agencies', $agency['id'], 'UPDATE', ['context' => 'entry_fee']);
        }
        
        $pdo->commit();
        header("Location: agenzia_detail.php?code=" . urlencode($code) . "&success=1#tab-contrattuale");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore in entry_fee_edit: " . $e->getMessage());
        die("Errore durante il salvataggio: " . $e->getMessage());
    }
}

// Carica rate esistenti
$stmt = $pdo->prepare("SELECT * FROM entry_fee_installments WHERE agency_id = :agency_id ORDER BY installment_number ASC");
$stmt->execute(['agency_id' => $agency['id']]);
$installments = $stmt->fetchAll();

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
.installment-row{background:var(--bg);padding:1rem;border-radius:8px;margin-bottom:.75rem;border:2px solid #E5E7EB;display:grid;grid-template-columns:80px 200px 200px 200px 1fr 40px;gap:1rem;align-items:start}
.installment-row.paid{border-color:#10B981;background:#D1FAE5}
.installment-header{display:grid;grid-template-columns:80px 200px 200px 200px 1fr 40px;gap:1rem;margin-bottom:.5rem;font-size:.85rem;font-weight:600;color:var(--cb-gray);padding:0 1rem}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;font-weight:600}
.btn-add:hover{background:var(--cb-blue)}
.btn-remove{background:#EF4444;color:white;border:none;width:32px;height:32px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:background .2s}
.btn-remove:hover{background:#DC2626}
.form-actions{display:flex;gap:1rem;justify-content:space-between;margin-top:2rem;padding-top:2rem;border-top:2px solid #F3F4F6}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-cancel:hover{border-color:var(--cb-gray)}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;transition:background .2s}
.btn-save:hover{background:var(--cb-blue)}
.total-summary{background:linear-gradient(135deg,var(--cb-blue) 0%,var(--cb-bright-blue) 100%);color:white;padding:1.5rem;border-radius:8px;margin-bottom:1.5rem}
.total-amount{font-size:2rem;font-weight:700}
.installment-field{display:flex;flex-direction:column;gap:.25rem}
.installment-field label{font-size:.75rem;font-weight:600;color:var(--cb-gray)}
.installment-field input,.installment-field textarea{padding:.5rem;border:1px solid #E5E7EB;border-radius:6px;font-size:.9rem}
.installment-field textarea{resize:vertical;min-height:60px}
.installment-number{background:var(--cb-bright-blue);color:white;font-weight:700;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
</style>

<div class="page-header">
<div>
<h1 class="page-title">💰 Gestisci Entry Fee</h1>
<div style="color:var(--cb-bright-blue);font-weight:600;margin-top:.5rem"><?= htmlspecialchars($agency['code']) ?> - <?= htmlspecialchars($agency['name']) ?></div>
</div>
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>#tab-contrattuale" class="back-btn">← Torna</a>
</div>

<form method="POST" id="entryFeeForm">
<div class="form-card">

<div class="form-section">
<h3>Entry Fee Totale</h3>
<div class="form-field">
<label>Importo Totale (€)</label>
<input type="number" name="entry_fee" id="totalAmount" step="0.01" min="0" value="<?= $agency['entry_fee'] ?? 0 ?>" required onchange="updateSummary()">
</div>
<div class="total-summary" id="summary" style="display:none">
<div style="display:flex;justify-content:space-between;align-items:center">
<div>
<div style="font-size:.9rem;opacity:.9">Entry Fee Totale</div>
<div class="total-amount" id="summaryTotal">€ 0,00</div>
</div>
<div style="text-align:right">
<div style="font-size:.9rem;opacity:.9">Rate Configurate</div>
<div style="font-size:1.5rem;font-weight:700" id="summaryCount">0</div>
</div>
<div style="text-align:right">
<div style="font-size:.9rem;opacity:.9">Totale Rate</div>
<div style="font-size:1.5rem;font-weight:700" id="summaryInstallments">€ 0,00</div>
</div>
</div>
</div>
</div>

<div class="form-section">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
<h3 style="margin:0">Rate di Pagamento</h3>
<button type="button" class="btn-add" onclick="addInstallment()">➕ Aggiungi Rata</button>
</div>

<div class="installment-header">
<div>Rata #</div>
<div>Importo (€)</div>
<div>Scadenza</div>
<div>Data Pagamento</div>
<div>Note</div>
<div></div>
</div>

<div id="installmentsContainer">
<?php if (empty($installments)): ?>
<div style="text-align:center;padding:2rem;color:var(--cb-gray);background:var(--bg);border-radius:8px">
<div style="font-size:2rem;margin-bottom:.5rem">📝</div>
<p>Nessuna rata configurata</p>
<p style="font-size:.85rem;margin-top:.5rem">Clicca su "Aggiungi Rata" per iniziare</p>
</div>
<?php else: ?>
<?php foreach ($installments as $inst): ?>
<div class="installment-row <?= $inst['payment_date'] ? 'paid' : '' ?>">
<div class="installment-number"><?= $inst['installment_number'] ?></div>
<div class="installment-field">
<input type="number" name="installments[<?= $inst['installment_number'] - 1 ?>][amount]" step="0.01" min="0" value="<?= $inst['amount'] ?>" placeholder="Importo" required onchange="updateSummary()">
</div>
<div class="installment-field">
<input type="date" name="installments[<?= $inst['installment_number'] - 1 ?>][due_date]" value="<?= $inst['due_date'] ?>">
</div>
<div class="installment-field">
<input type="date" name="installments[<?= $inst['installment_number'] - 1 ?>][payment_date]" value="<?= $inst['payment_date'] ?>" onchange="togglePaidStatus(this)">
</div>
<div class="installment-field">
<textarea name="installments[<?= $inst['installment_number'] - 1 ?>][notes]" placeholder="Note..."><?= htmlspecialchars($inst['notes'] ?? '') ?></textarea>
</div>
<button type="button" class="btn-remove" onclick="removeInstallment(this)" title="Rimuovi">✕</button>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<div class="form-actions">
<a href="agenzia_detail.php?code=<?= urlencode($code) ?>#tab-contrattuale" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-save">💾 Salva Entry Fee</button>
</div>

</div>
</form>

<script>
let installmentCount = <?= count($installments) ?>;

function addInstallment() {
    const container = document.getElementById('installmentsContainer');
    const emptyState = container.querySelector('[style*="text-align:center"]');
    if (emptyState) emptyState.remove();
    
    const row = document.createElement('div');
    row.className = 'installment-row';
    row.innerHTML = `
        <div class="installment-number">${installmentCount + 1}</div>
        <div class="installment-field">
            <input type="number" name="installments[${installmentCount}][amount]" step="0.01" min="0" placeholder="Importo" required onchange="updateSummary()">
        </div>
        <div class="installment-field">
            <input type="date" name="installments[${installmentCount}][due_date]">
        </div>
        <div class="installment-field">
            <input type="date" name="installments[${installmentCount}][payment_date]" onchange="togglePaidStatus(this)">
        </div>
        <div class="installment-field">
            <textarea name="installments[${installmentCount}][notes]" placeholder="Note..."></textarea>
        </div>
        <button type="button" class="btn-remove" onclick="removeInstallment(this)" title="Rimuovi">✕</button>
    `;
    container.appendChild(row);
    installmentCount++;
    updateSummary();
}

function removeInstallment(btn) {
    const row = btn.closest('.installment-row');
    row.remove();
    
    // Rinumera
    const rows = document.querySelectorAll('.installment-row');
    rows.forEach((row, index) => {
        const numberDiv = row.querySelector('.installment-number');
        if (numberDiv) numberDiv.textContent = index + 1;
        
        // Aggiorna name attributes
        row.querySelectorAll('[name^="installments"]').forEach(input => {
            const name = input.getAttribute('name');
            const newName = name.replace(/\[\d+\]/, `[${index}]`);
            input.setAttribute('name', newName);
        });
    });
    
    installmentCount = rows.length;
    updateSummary();
    
    // Se non ci sono più rate, mostra empty state
    if (installmentCount === 0) {
        const container = document.getElementById('installmentsContainer');
        container.innerHTML = `
            <div style="text-align:center;padding:2rem;color:var(--cb-gray);background:var(--bg);border-radius:8px">
                <div style="font-size:2rem;margin-bottom:.5rem">📝</div>
                <p>Nessuna rata configurata</p>
                <p style="font-size:.85rem;margin-top:.5rem">Clicca su "Aggiungi Rata" per iniziare</p>
            </div>
        `;
    }
}

function togglePaidStatus(input) {
    const row = input.closest('.installment-row');
    if (input.value) {
        row.classList.add('paid');
    } else {
        row.classList.remove('paid');
    }
}

function updateSummary() {
    const total = parseFloat(document.getElementById('totalAmount').value) || 0;
    const rows = document.querySelectorAll('.installment-row');
    let installmentsTotal = 0;
    
    rows.forEach(row => {
        const amount = parseFloat(row.querySelector('input[name*="[amount]"]')?.value) || 0;
        installmentsTotal += amount;
    });
    
    document.getElementById('summaryTotal').textContent = '€ ' + total.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summaryCount').textContent = rows.length;
    document.getElementById('summaryInstallments').textContent = '€ ' + installmentsTotal.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const summary = document.getElementById('summary');
    if (total > 0 || rows.length > 0) {
        summary.style.display = 'block';
    } else {
        summary.style.display = 'none';
    }
}

// Update summary on load
updateSummary();
</script>

<?php require_once 'footer.php'; ?>

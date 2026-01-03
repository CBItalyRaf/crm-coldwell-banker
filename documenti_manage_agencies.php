<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header('Location: documenti.php');
    exit;
}

$pdo = getDB();
$documentId = (int)$_GET['id'];

// Recupera documento
$stmt = $pdo->prepare("
    SELECT d.*, dc.name as category_name 
    FROM documents d 
    JOIN document_categories dc ON d.category_id = dc.id 
    WHERE d.id = ?
");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document || $document['type'] !== 'group') {
    header('Location: documenti.php?error=' . urlencode('Documento non valido o non di tipo gruppo.'));
    exit;
}

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' && !empty($_POST['agencies'])) {
            // Aggiungi agenzie
            $stmtAdd = $pdo->prepare("
                INSERT IGNORE INTO document_agencies (document_id, agency_code) 
                VALUES (?, ?)
            ");
            
            foreach ($_POST['agencies'] as $agencyCode) {
                $stmtAdd->execute([$documentId, $agencyCode]);
            }
            
            header("Location: documenti_manage_agencies.php?id={$documentId}&success=added");
            exit;
            
        } elseif ($action === 'remove' && !empty($_POST['agency_code'])) {
            // Rimuovi agenzia
            $stmtRemove = $pdo->prepare("
                DELETE FROM document_agencies 
                WHERE document_id = ? AND agency_code = ?
            ");
            $stmtRemove->execute([$documentId, $_POST['agency_code']]);
            
            header("Location: documenti_manage_agencies.php?id={$documentId}&success=removed");
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Carica agenzie assegnate
$assignedStmt = $pdo->prepare("
    SELECT a.code, a.name 
    FROM document_agencies da 
    JOIN agencies a ON da.agency_code = a.code 
    WHERE da.document_id = ? 
    ORDER BY a.name ASC
");
$assignedStmt->execute([$documentId]);
$assignedAgencies = $assignedStmt->fetchAll();

// Carica agenzie disponibili (non ancora assegnate)
$availableStmt = $pdo->prepare("
    SELECT code, name 
    FROM agencies 
    WHERE status != 'Prospect' 
    AND code NOT IN (
        SELECT agency_code 
        FROM document_agencies 
        WHERE document_id = ?
    )
    ORDER BY name ASC
");
$availableStmt->execute([$documentId]);
$availableAgencies = $availableStmt->fetchAll();

$pageTitle = "Gestisci Agenzie - " . htmlspecialchars($document['original_filename']);
require_once 'header.php';
?>

<style>
.back-link{display:inline-flex;align-items:center;gap:.5rem;color:var(--cb-bright-blue);text-decoration:none;margin-bottom:2rem;font-size:.95rem;font-weight:500}
.back-link:hover{text-decoration:underline}
.document-info{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.document-title{font-size:1.25rem;font-weight:600;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.document-meta{display:flex;gap:1rem;flex-wrap:wrap;font-size:.9rem;color:var(--cb-gray)}
.columns{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem}
.column{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.column-title{font-size:1.1rem;font-weight:600;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between}
.agencies-list{max-height:500px;overflow-y:auto}
.agency-item{display:flex;align-items:center;justify-content:space-between;padding:1rem;border-bottom:1px solid #F3F4F6;transition:background .2s}
.agency-item:last-child{border-bottom:none}
.agency-item:hover{background:var(--bg)}
.agency-info{flex:1}
.agency-name{font-weight:500;color:var(--cb-midnight)}
.agency-code{font-size:.85rem;color:var(--cb-gray)}
.btn-remove{background:transparent;border:1px solid var(--danger);color:var(--danger);padding:.5rem 1rem;border-radius:6px;font-size:.85rem;cursor:pointer;transition:all .2s}
.btn-remove:hover{background:var(--danger);color:white}
.filter-box{margin-bottom:1rem}
.filter-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.filter-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.agencies-checkboxes{max-height:400px;overflow-y:auto;border:1px solid #E5E7EB;border-radius:8px;padding:.5rem}
.checkbox-item{display:flex;align-items:center;gap:.75rem;padding:.75rem;border-radius:6px;cursor:pointer;transition:background .2s}
.checkbox-item:hover{background:var(--bg)}
.checkbox-item input{cursor:pointer}
.checkbox-item label{cursor:pointer;flex:1;font-size:.9rem}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;font-size:.95rem;cursor:pointer;transition:background .2s;width:100%;font-weight:500}
.btn-add:hover{background:var(--cb-blue)}
.btn-add:disabled{opacity:.5;cursor:not-allowed}
.select-all-btn{background:var(--bg);border:1px solid #E5E7EB;padding:.5rem 1rem;border-radius:6px;font-size:.85rem;cursor:pointer;margin-bottom:.5rem;width:100%}
.select-all-btn:hover{background:#E5E7EB}
.empty-state{text-align:center;padding:3rem 1rem;color:var(--cb-gray)}
.alert{padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem;font-size:.95rem}
.alert-success{background:#D1FAE5;color:#065F46;border:1px solid #10B981}
.alert-error{background:#FEE2E2;color:#991B1B;border:1px solid #EF4444}
@media(max-width:968px){
.columns{grid-template-columns:1fr}
}
</style>

<a href="documenti.php" class="back-link">← Torna ai Documenti</a>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
<?php if ($_GET['success'] === 'added'): ?>
✅ Agenzie aggiunte con successo!
<?php elseif ($_GET['success'] === 'removed'): ?>
✅ Agenzia rimossa con successo!
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-error">
❌ Errore: <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="document-info">
<div class="document-title">
📄 <?= htmlspecialchars($document['original_filename']) ?>
</div>
<div class="document-meta">
<span>📁 <?= htmlspecialchars($document['category_name']) ?></span>
<span>💾 <?= number_format($document['file_size'] / 1048576, 2) ?> MB</span>
<span>📅 <?= date('d/m/Y H:i', strtotime($document['uploaded_at'])) ?></span>
<span>👥 Tipo: Gruppo</span>
<span>👁️ Visibilità: <?= $document['visibility'] === 'public' ? 'Pubblico' : 'Solo Broker' ?></span>
</div>
</div>

<div class="columns">
<!-- Agenzie Assegnate -->
<div class="column">
<div class="column-title">
<span>✅ Agenzie Assegnate (<?= count($assignedAgencies) ?>)</span>
</div>
<?php if (empty($assignedAgencies)): ?>
<div class="empty-state">
<p>Nessuna agenzia assegnata</p>
</div>
<?php else: ?>
<div class="agencies-list">
<?php foreach ($assignedAgencies as $agency): ?>
<div class="agency-item">
<div class="agency-info">
<div class="agency-name"><?= htmlspecialchars($agency['name']) ?></div>
<div class="agency-code"><?= htmlspecialchars($agency['code']) ?></div>
</div>
<form method="POST" style="display:inline" onsubmit="return confirm('Rimuovere questa agenzia?')">
<input type="hidden" name="action" value="remove">
<input type="hidden" name="agency_code" value="<?= htmlspecialchars($agency['code']) ?>">
<button type="submit" class="btn-remove">🗑️ Rimuovi</button>
</form>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Aggiungi Agenzie -->
<div class="column">
<div class="column-title">
<span>➕ Aggiungi Agenzie</span>
</div>
<?php if (empty($availableAgencies)): ?>
<div class="empty-state">
<p>Tutte le agenzie sono già assegnate</p>
</div>
<?php else: ?>
<form method="POST">
<input type="hidden" name="action" value="add">
<div class="filter-box">
<input type="text" id="filterAvailable" placeholder="🔍 Filtra per nome..." onkeyup="filterAvailableAgencies()">
</div>
<button type="button" class="select-all-btn" onclick="selectAllFilteredAvailable()">
Seleziona tutte (filtrate)
</button>
<div class="agencies-checkboxes" id="availableList">
<?php foreach ($availableAgencies as $agency): ?>
<div class="checkbox-item" data-name="<?= strtolower($agency['name']) ?>">
<input type="checkbox" name="agencies[]" value="<?= htmlspecialchars($agency['code']) ?>" id="av-<?= htmlspecialchars($agency['code']) ?>">
<label for="av-<?= htmlspecialchars($agency['code']) ?>">
<div class="agency-name"><?= htmlspecialchars($agency['name']) ?></div>
<div class="agency-code"><?= htmlspecialchars($agency['code']) ?></div>
</label>
</div>
<?php endforeach; ?>
</div>
<button type="submit" class="btn-add" style="margin-top:1rem">➕ Aggiungi Selezionate</button>
</form>
<?php endif; ?>
</div>
</div>

<script>
// Filtro agenzie disponibili
function filterAvailableAgencies() {
    const filter = document.getElementById('filterAvailable').value.toLowerCase();
    const items = document.querySelectorAll('#availableList .checkbox-item');
    
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(filter)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Seleziona tutte filtrate
function selectAllFilteredAvailable() {
    const items = document.querySelectorAll('#availableList .checkbox-item');
    items.forEach(item => {
        if (item.style.display !== 'none') {
            item.querySelector('input').checked = true;
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>

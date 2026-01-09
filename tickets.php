<?php
require_once 'check_auth.php';
require_once 'config/database.php';

// Carica helper ticket solo se esiste
if (file_exists(__DIR__ . '/helpers/ticket_functions.php')) {
    require_once 'helpers/ticket_functions.php';
}

$pageTitle = "Ticket - CRM Coldwell Banker";
$pdo = getDB();

// Statistiche mese corrente
$thisMonth = date('Y-m-01');
$nextMonth = date('Y-m-01', strtotime('+1 month'));

$statsAperti = $pdo->query("SELECT COUNT(*) FROM tickets WHERE stato IN ('nuovo','in_lavorazione','in_attesa')")->fetchColumn();
$statsLavorazione = $pdo->query("SELECT COUNT(*) FROM tickets WHERE stato = 'in_lavorazione'")->fetchColumn();
$statsChiusiMese = $pdo->query("SELECT COUNT(*) FROM tickets WHERE stato = 'risolto' AND closed_at >= '$thisMonth' AND closed_at < '$nextMonth'")->fetchColumn();
$statsNuoviMese = $pdo->query("SELECT COUNT(*) FROM tickets WHERE created_at >= '$thisMonth' AND created_at < '$nextMonth'")->fetchColumn();

// Tempo medio risoluzione (in ore) - solo ticket risolti
$tempoMedioQuery = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as media_ore
    FROM tickets 
    WHERE stato = 'risolto' AND closed_at IS NOT NULL
");
$tempoMedioOre = $tempoMedioQuery->fetchColumn();
$tempoMedioGiorni = $tempoMedioOre ? round($tempoMedioOre / 24, 1) : 0;

// Filtri
$categoriaFilter = $_GET['categoria'] ?? '';
$statoFilter = $_GET['stato'] ?? '';
$prioritaFilter = $_GET['priorita'] ?? '';
$agenziaFilter = $_GET['agenzia'] ?? '';
$assegnatoFilter = $_GET['assegnato'] ?? '';
$search = $_GET['search'] ?? '';

// Query
$sql = "SELECT * FROM v_tickets_complete WHERE 1=1";
$params = [];

if ($categoriaFilter) {
    $sql .= " AND categoria_id = ?";
    $params[] = $categoriaFilter;
}
if ($statoFilter) {
    $sql .= " AND stato = ?";
    $params[] = $statoFilter;
}
if ($prioritaFilter) {
    $sql .= " AND priorita = ?";
    $params[] = $prioritaFilter;
}
if ($agenziaFilter) {
    $sql .= " AND agenzia_id = ?";
    $params[] = $agenziaFilter;
}
if ($assegnatoFilter) {
    if ($assegnatoFilter === 'me') {
        $sql .= " AND assegnato_a_email = ?";
        $params[] = $_SESSION['crm_user']['email'];
    } elseif ($assegnatoFilter === 'unassigned') {
        $sql .= " AND assegnato_a_email IS NULL";
    } elseif ($assegnatoFilter === 'internal') {
        $sql .= " AND agenzia_id IS NULL"; // Task interni = senza agenzia
    }
}
if ($search) {
    $sql .= " AND (numero LIKE ? OR titolo LIKE ? OR agenzia_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY 
    CASE WHEN agenzia_id IS NULL THEN 0 ELSE 1 END DESC,
    created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Carica liste per filtri
$categories = $pdo->query("SELECT * FROM ticket_categories WHERE attivo = 1 ORDER BY ordine")->fetchAll();
$agencies = $pdo->query("SELECT id, code, name FROM agencies ORDER BY name")->fetchAll();

require_once 'header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{background:white;padding:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid}
.stat-card.open{border-left-color:#3B82F6}
.stat-card.working{border-left-color:#F59E0B}
.stat-card.closed{border-left-color:#10B981}
.stat-card.new{border-left-color:#8B5CF6}
.stat-label{font-size:.875rem;color:var(--cb-gray);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.5rem}
.stat-value{font-size:2rem;font-weight:700;color:var(--cb-midnight)}
.btn-add{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500;transition:background .2s}
.btn-add:hover{background:var(--cb-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:1.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem}
.filter-field select,.filter-field input{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.tickets-grid{display:grid;gap:1rem}
.ticket-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid;cursor:pointer;transition:all .2s}
.ticket-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.15);transform:translateY(-2px)}
.ticket-card.ticket-risolto{opacity:.6;background:#F9FAFB}
.ticket-card.ticket-risolto:hover{opacity:.75;background:#F3F4F6}
.ticket-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.ticket-numero{font-size:.85rem;color:var(--cb-gray);font-weight:600}
.ticket-badges{display:flex;gap:.5rem;flex-wrap:wrap}
.badge{padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.badge.priorita-alta{background:#FEE2E2;color:#991B1B}
.badge.priorita-media{background:#FEF3C7;color:#92400E}
.badge.priorita-bassa{background:#DBEAFE;color:#1E40AF}
.badge.stato-nuovo{background:#DBEAFE;color:#1E3A8A}
.badge.stato-in_lavorazione{background:#FEF3C7;color:#92400E}
.badge.stato-risolto{background:#D1FAE5;color:#065F46}
.ticket-title{font-size:1.1rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem}
.ticket-desc{color:var(--cb-gray);font-size:.9rem;margin-bottom:1rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ticket-meta{display:flex;gap:1.5rem;font-size:.85rem;color:var(--cb-gray);flex-wrap:wrap}
.ticket-meta-item{display:flex;align-items:center;gap:.5rem}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.categoria-tag{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:8px;font-size:.85rem;font-weight:600}
@media(max-width:768px){
.stats-grid{grid-template-columns:repeat(2,1fr)}
.filters-grid{grid-template-columns:1fr}
}
</style>

<div class="page-header">
<h1 class="page-title">🎫 Ticket</h1>
<a href="ticket_add.php" class="btn-add">➕ Nuovo Ticket</a>
</div>

<?php if(isset($_GET['success']) && $_GET['success'] === 'created'): ?>
<div style="background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46">
✅ Ticket creato con successo!
</div>
<?php endif; ?>

<div class="stats-grid">
<div class="stat-card open">
<div class="stat-label">🔵 Aperti</div>
<div class="stat-value"><?= $statsAperti ?></div>
</div>
<div class="stat-card working">
<div class="stat-label">⚙️ In Lavorazione</div>
<div class="stat-value"><?= $statsLavorazione ?></div>
</div>
<div class="stat-card closed">
<div class="stat-label">✅ Chiusi (questo mese)</div>
<div class="stat-value"><?= $statsChiusiMese ?></div>
</div>
<div class="stat-card new">
<div class="stat-label">📥 Nuovi (questo mese)</div>
<div class="stat-value"><?= $statsNuoviMese ?></div>
</div>
<div class="stat-card" style="background:linear-gradient(135deg,#A78BFA 0%,#8B5CF6 100%)">
<div class="stat-label">⏱️ Tempo Medio Risoluzione</div>
<div class="stat-value"><?= $tempoMedioGiorni ?> gg</div>
</div>
</div>

<div class="filters-bar">
<div class="filters-grid">
<div class="filter-field">
<select name="stato" onchange="window.location.search='?stato='+this.value">
<option value="">Tutti gli stati</option>
<option value="nuovo" <?= $statoFilter === 'nuovo' ? 'selected' : '' ?>>Nuovo</option>
<option value="in_lavorazione" <?= $statoFilter === 'in_lavorazione' ? 'selected' : '' ?>>In lavorazione</option>
<option value="in_attesa" <?= $statoFilter === 'in_attesa' ? 'selected' : '' ?>>In attesa</option>
<option value="risolto" <?= $statoFilter === 'risolto' ? 'selected' : '' ?>>Risolto</option>
</select>
</div>
<div class="filter-field">
<select name="priorita" onchange="window.location.search='?priorita='+this.value">
<option value="">Tutte le priorità</option>
<option value="alta" <?= $prioritaFilter === 'alta' ? 'selected' : '' ?>>Alta</option>
<option value="media" <?= $prioritaFilter === 'media' ? 'selected' : '' ?>>Media</option>
<option value="bassa" <?= $prioritaFilter === 'bassa' ? 'selected' : '' ?>>Bassa</option>
</select>
</div>
<div class="filter-field">
<select name="assegnato" onchange="window.location.search='?assegnato='+this.value">
<option value="">Assegnazione</option>
<option value="me" <?= $assegnatoFilter === 'me' ? 'selected' : '' ?>>I miei</option>
<option value="unassigned" <?= $assegnatoFilter === 'unassigned' ? 'selected' : '' ?>>Non assegnati</option>
<option value="internal" <?= $assegnatoFilter === 'internal' ? 'selected' : '' ?>>Task Interni</option>
</select>
</div>
<div class="filter-field">
<input type="text" placeholder="🔍 Cerca..." value="<?= htmlspecialchars($search) ?>" onchange="window.location.search='?search='+this.value">
</div>
</div>
</div>

<?php if (empty($tickets)): ?>
<div class="empty-state">
<div style="font-size:4rem;margin-bottom:1rem">🎫</div>
<h3>Nessun ticket trovato</h3>
<p>Modifica i filtri o crea un nuovo ticket</p>
</div>
<?php else: ?>
<div class="tickets-grid">
<?php foreach ($tickets as $ticket): ?>
<div class="ticket-card <?= $ticket['stato'] === 'risolto' ? 'ticket-risolto' : '' ?>" style="border-left-color:<?= $ticket['categoria_colore'] ?? '#1F69FF' ?>" onclick="window.location.href='ticket_detail.php?id=<?= $ticket['id'] ?>'">
<div class="ticket-header">
<div>
<div class="ticket-numero"><?= htmlspecialchars($ticket['numero']) ?></div>
<?php if ($ticket['categoria_nome']): ?>
<span class="categoria-tag" style="background:<?= $ticket['categoria_colore'] ?>20;color:<?= $ticket['categoria_colore'] ?>">
<?= $ticket['categoria_icona'] ?> <?= htmlspecialchars($ticket['categoria_nome']) ?>
</span>
<?php endif; ?>
</div>
<div class="ticket-badges">
<span class="badge priorita-<?= $ticket['priorita'] ?>"><?= strtoupper($ticket['priorita']) ?></span>
<span class="badge stato-<?= $ticket['stato'] ?>"><?= str_replace('_', ' ', $ticket['stato']) ?></span>
<?php if ($ticket['is_privato']): ?>
<span class="badge" style="background:#FEE2E2;color:#991B1B">🔒 Privato</span>
<?php endif; ?>
</div>
</div>
<h3 class="ticket-title"><?= htmlspecialchars($ticket['titolo']) ?></h3>
<p class="ticket-desc"><?= htmlspecialchars($ticket['descrizione']) ?></p>
<div class="ticket-meta">
<?php if ($ticket['agenzia_name']): ?>
<div class="ticket-meta-item">
🏢 <?= htmlspecialchars($ticket['agenzia_name']) ?>
</div>
<?php else: ?>
<div class="ticket-meta-item">
📝 Task Interno
</div>
<?php endif; ?>
<?php if ($ticket['creato_da_email']): ?>
<div class="ticket-meta-item">
👤 Creato da: <?= htmlspecialchars($ticket['creato_da_email']) ?>
</div>
<?php endif; ?>
<?php if ($ticket['assegnato_a_email']): ?>
<div class="ticket-meta-item">
✋ Assegnato a: <?= htmlspecialchars($ticket['assegnato_a_email']) ?>
</div>
<?php endif; ?>
<div class="ticket-meta-item">
💬 <?= $ticket['num_messaggi'] ?> messaggi
</div>
<div class="ticket-meta-item">
📅 <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

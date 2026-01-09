<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$ticketId = $_GET['id'] ?? null;

if (!$ticketId) {
    header('Location: tickets.php');
    exit;
}

$pageTitle = "Dettaglio Ticket - CRM Coldwell Banker";
$pdo = getDB();

// Carica ticket
$stmt = $pdo->prepare("SELECT * FROM v_tickets_complete WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Gestione POST - nuovo messaggio o cambio stato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['messaggio'])) {
        // Nuovo messaggio
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, staff_email, mittente_tipo, messaggio, is_internal)
            VALUES (?, ?, 'staff', ?, ?)
        ");
        $stmt->execute([
            $ticketId,
            $_SESSION['crm_user']['email'],
            $_POST['messaggio'],
            isset($_POST['is_internal']) ? 1 : 0
        ]);
        
        header("Location: ticket_detail.php?id=$ticketId&success=message");
        exit;
    }
    
    if (isset($_POST['nuovo_stato'])) {
        // Cambio stato
        $stmt = $pdo->prepare("UPDATE tickets SET stato = ?, closed_at = ? WHERE id = ?");
        $closedAt = $_POST['nuovo_stato'] === 'risolto' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$_POST['nuovo_stato'], $closedAt, $ticketId]);
        
        header("Location: ticket_detail.php?id=$ticketId&success=status");
        exit;
    }
}

// Carica messaggi
$stmt = $pdo->prepare("
    SELECT tm.*, 
           pu.first_name as broker_first_name,
           pu.last_name as broker_last_name
    FROM ticket_messages tm
    LEFT JOIN portal_users pu ON tm.portal_user_id = pu.id
    WHERE tm.ticket_id = ?
    ORDER BY tm.created_at ASC
");
$stmt->execute([$ticketId]);
$messages = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
.ticket-detail-header{background:white;padding:2rem;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:2rem;border-left:6px solid <?= $ticket['categoria_colore'] ?? '#1F69FF' ?>}
.ticket-numero{font-size:.9rem;color:var(--cb-gray);font-weight:600;margin-bottom:.5rem}
.ticket-title{font-size:1.75rem;font-weight:700;color:var(--cb-midnight);margin-bottom:1rem}
.ticket-badges{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem}
.badge{padding:.5rem 1rem;border-radius:8px;font-size:.85rem;font-weight:600;text-transform:uppercase}
.badge.priorita-alta{background:#FEE2E2;color:#991B1B}
.badge.priorita-media{background:#FEF3C7;color:#92400E}
.badge.priorita-bassa{background:#DBEAFE;color:#1E40AF}
.badge.stato-nuovo{background:#DBEAFE;color:#1E3A8A}
.badge.stato-in_lavorazione{background:#FEF3C7;color:#92400E}
.badge.stato-in_attesa{background:#FED7AA;color:#9A3412}
.badge.stato-risolto{background:#D1FAE5;color:#065F46}
.badge.categoria{background:<?= $ticket['categoria_colore'] ?>20;color:<?= $ticket['categoria_colore'] ?>}
.ticket-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;padding:1.5rem;background:var(--bg);border-radius:8px}
.meta-item{display:flex;flex-direction:column;gap:.25rem}
.meta-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cb-gray);font-weight:600}
.meta-value{font-size:.95rem;color:var(--cb-midnight);font-weight:500}
.ticket-description{padding:1.5rem;background:var(--bg);border-radius:8px;margin-top:1.5rem}
.ticket-description p{line-height:1.8;color:var(--cb-gray)}
.conversation{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:2rem;margin-bottom:2rem}
.conversation-title{font-size:1.25rem;font-weight:600;margin-bottom:1.5rem;color:var(--cb-midnight)}
.message{padding:1.5rem;border-radius:12px;margin-bottom:1.5rem;position:relative}
.message.staff{background:#EFF6FF;border-left:4px solid #3B82F6}
.message.broker{background:#F3F4F6;border-left:4px solid var(--cb-gray)}
.message.internal{background:#FEF3C7;border-left:4px solid #F59E0B}
.message-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:.75rem}
.message-author{font-weight:600;color:var(--cb-midnight)}
.message-date{font-size:.85rem;color:var(--cb-gray)}
.message-body{line-height:1.8;color:var(--cb-gray)}
.message-internal-badge{display:inline-block;padding:.25rem .75rem;background:#F59E0B;color:white;font-size:.75rem;border-radius:12px;font-weight:600;margin-left:.5rem}
.reply-form{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:2rem}
.form-field{margin-bottom:1.5rem}
.form-field label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--cb-midnight)}
.form-field textarea{width:100%;min-height:150px;padding:1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;resize:vertical}
.form-field textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.checkbox-field{display:flex;align-items:center;gap:.75rem;padding:1rem;background:var(--bg);border-radius:8px;cursor:pointer}
.checkbox-field input{width:20px;height:20px;cursor:pointer}
.form-actions{display:flex;gap:1rem;justify-content:space-between;align-items:center}
.btn-back{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem}
.btn-send{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 2rem;border-radius:8px;cursor:pointer;font-weight:600;font-size:1rem}
.stato-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.stato-btn{padding:.5rem 1rem;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600;transition:opacity .2s}
.stato-btn:hover{opacity:.8}
.stato-btn.lavorazione{background:#FEF3C7;color:#92400E}
.stato-btn.attesa{background:#FED7AA;color:#9A3412}
.stato-btn.risolto{background:#D1FAE5;color:#065F46}
.success-msg{background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46}
</style>

<?php if(isset($_GET['success'])): ?>
<div class="success-msg">
✅ <?= $_GET['success'] === 'message' ? 'Messaggio inviato!' : 'Stato aggiornato!' ?>
</div>
<?php endif; ?>

<div class="ticket-detail-header">
<div class="ticket-numero"><?= htmlspecialchars($ticket['numero']) ?></div>
<h1 class="ticket-title"><?= htmlspecialchars($ticket['titolo']) ?></h1>

<div class="ticket-badges">
<span class="badge priorita-<?= $ticket['priorita'] ?>"><?= strtoupper($ticket['priorita']) ?></span>
<span class="badge stato-<?= $ticket['stato'] ?>"><?= str_replace('_', ' ', strtoupper($ticket['stato'])) ?></span>
<?php if ($ticket['categoria_nome']): ?>
<span class="badge categoria"><?= $ticket['categoria_icona'] ?> <?= htmlspecialchars($ticket['categoria_nome']) ?></span>
<?php endif; ?>
<?php if ($ticket['is_privato']): ?>
<span class="badge" style="background:#FEE2E2;color:#991B1B">🔒 PRIVATO</span>
<?php endif; ?>
</div>

<div class="ticket-meta-grid">
<?php if ($ticket['agenzia_name']): ?>
<div class="meta-item">
<div class="meta-label">Agenzia</div>
<div class="meta-value">🏢 <?= htmlspecialchars($ticket['agenzia_code']) ?> - <?= htmlspecialchars($ticket['agenzia_name']) ?></div>
</div>
<?php else: ?>
<div class="meta-item">
<div class="meta-label">Tipo</div>
<div class="meta-value">📝 Task Interno</div>
</div>
<?php endif; ?>
<div class="meta-item">
<div class="meta-label">Creato da</div>
<div class="meta-value">👤 <?= htmlspecialchars($ticket['creato_da_email']) ?></div>
</div>
<div class="meta-item">
<div class="meta-label">Assegnato a</div>
<div class="meta-value">✋ <?= $ticket['assegnato_a_email'] ? htmlspecialchars($ticket['assegnato_a_email']) : 'Non assegnato' ?></div>
</div>
<div class="meta-item">
<div class="meta-label">Creato il</div>
<div class="meta-value">📅 <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></div>
</div>
</div>

<?php if ($ticket['descrizione']): ?>
<div class="ticket-description">
<p><?= nl2br(htmlspecialchars($ticket['descrizione'])) ?></p>
</div>
<?php endif; ?>
</div>

<div class="conversation">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem">
<h2 class="conversation-title">💬 Conversazione (<?= count($messages) ?>)</h2>
<div class="stato-actions">
<?php if ($ticket['stato'] !== 'in_lavorazione'): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="nuovo_stato" value="in_lavorazione">
<button type="submit" class="stato-btn lavorazione">⚙️ In Lavorazione</button>
</form>
<?php endif; ?>
<?php if ($ticket['stato'] !== 'in_attesa'): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="nuovo_stato" value="in_attesa">
<button type="submit" class="stato-btn attesa">⏸️ In Attesa</button>
</form>
<?php endif; ?>
<?php if ($ticket['stato'] !== 'risolto'): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="nuovo_stato" value="risolto">
<button type="submit" class="stato-btn risolto">✅ Risolvi</button>
</form>
<?php endif; ?>
</div>
</div>

<?php if (empty($messages)): ?>
<div style="text-align:center;padding:3rem;color:var(--cb-gray)">
<div style="font-size:3rem;margin-bottom:1rem">💬</div>
<p>Nessun messaggio ancora. Inizia la conversazione!</p>
</div>
<?php else: ?>
<?php foreach ($messages as $msg): ?>
<div class="message <?= $msg['mittente_tipo'] ?> <?= $msg['is_internal'] ? 'internal' : '' ?>">
<div class="message-header">
<div class="message-author">
<?= $msg['mittente_tipo'] === 'staff' ? '👨‍💼 ' . htmlspecialchars($msg['staff_email']) : '👤 ' . htmlspecialchars($msg['broker_first_name'] . ' ' . $msg['broker_last_name']) ?>
<?php if ($msg['is_internal']): ?>
<span class="message-internal-badge">INTERNO</span>
<?php endif; ?>
</div>
<div class="message-date"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></div>
</div>
<div class="message-body"><?= nl2br(htmlspecialchars($msg['messaggio'])) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if ($ticket['stato'] !== 'risolto'): ?>
<div class="reply-form">
<h3 style="margin-bottom:1.5rem;font-size:1.1rem;font-weight:600">✍️ Rispondi</h3>
<form method="POST">
<div class="form-field">
<label>Messaggio</label>
<textarea name="messaggio" required placeholder="Scrivi il tuo messaggio..."></textarea>
</div>
<label class="checkbox-field">
<input type="checkbox" name="is_internal">
<div>
<div style="font-weight:600">🔒 Nota interna</div>
<div style="font-size:.85rem;color:var(--cb-gray)">Visibile solo allo staff, non al broker</div>
</div>
</label>
<div class="form-actions">
<a href="tickets.php" class="btn-back">← Torna alla lista</a>
<button type="submit" class="btn-send">📨 Invia Messaggio</button>
</div>
</form>
</div>
<?php else: ?>
<div style="background:var(--bg);padding:2rem;border-radius:12px;text-align:center">
<p style="color:var(--cb-gray)">✅ Ticket risolto. <a href="tickets.php" style="color:var(--cb-bright-blue);font-weight:600">← Torna alla lista</a></p>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

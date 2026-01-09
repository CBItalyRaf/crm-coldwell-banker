<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/user_preferences.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Impostazioni Notifiche";

// Controlla che email esista
if (empty($user['email'])) {
    die("Errore: Email utente non valida. Contatta l'amministratore.");
}

// Verifica ruolo (supporta sia 'role' che 'crm_role', sia 'admin' che 'Admin')
$userRole = strtolower($user['crm_role'] ?? $user['role'] ?? '');
$isAdmin = in_array($userRole, ['admin', 'editor']); // Admin e Editor vedono scadenze

// DEBUG (decommentare se non vedi sezioni)
// echo "<pre>User Role: $userRole | Is Admin: " . ($isAdmin ? 'SI' : 'NO') . "</pre>";
// echo "<pre>"; print_r($user); echo "</pre>";

// Salva preferenze
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Salva preferenze scadenze (solo se admin/editor)
        if ($isAdmin) {
            $savedScadenze = saveUserPreferences(
                $pdo,
                $user['email'],
                isset($_POST['notify_scadenze_email']) ? 1 : 0,
                isset($_POST['notify_scadenze_badge']) ? 1 : 0,
                isset($_POST['notify_scadenze_dashboard']) ? 1 : 0
            );
        } else {
            $savedScadenze = true; // Non admin, skip
        }
        
        // Salva preferenze ticket (per tutti)
        $stmt = $pdo->prepare("
            UPDATE user_preferences 
            SET notify_ticket_email = ?,
                notify_ticket_badge = ?,
                notify_ticket_dashboard = ?
            WHERE user_email = ?
        ");
        $savedTicket = $stmt->execute([
            isset($_POST['notify_ticket_email']) ? 1 : 0,
            isset($_POST['notify_ticket_badge']) ? 1 : 0,
            isset($_POST['notify_ticket_dashboard']) ? 1 : 0,
            $user['email']
        ]);
        
        if ($savedScadenze && $savedTicket) {
            header('Location: user_settings.php?saved=1');
            exit;
        } else {
            die("Errore: salvataggio fallito");
        }
    } catch (Exception $e) {
        die("ERRORE SALVATAGGIO: " . $e->getMessage());
    }
}

// Carica preferenze attuali
$userSettings = getUserPreferences($pdo, $user['email']);

require_once 'header.php';
?>

<style>
.settings-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:2rem;max-width:800px;margin:2rem auto}
.settings-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid var(--bg)}
.settings-section:last-child{border:none;margin-bottom:0;padding-bottom:0}
.settings-title{font-size:1.25rem;font-weight:600;color:var(--cb-midnight);margin:0 0 1.5rem 0}
.checkbox-item{display:flex;align-items:start;gap:1rem;padding:1rem;background:var(--bg);border-radius:8px;margin-bottom:.75rem;cursor:pointer;transition:background .2s}
.checkbox-item:hover{background:#E5E7EB}
.checkbox-item input[type="checkbox"]{width:20px;height:20px;cursor:pointer;margin-top:.25rem}
.checkbox-label{flex:1}
.checkbox-title{font-weight:600;color:var(--cb-midnight);margin-bottom:.25rem}
.checkbox-desc{font-size:.85rem;color:var(--cb-gray)}
.save-btn{background:var(--cb-bright-blue);color:white;border:none;padding:1rem 2rem;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:background .2s}
.save-btn:hover{background:var(--cb-blue)}
.success-msg{background:#D1FAE5;border-left:4px solid #10B981;padding:1rem;border-radius:8px;margin-bottom:2rem;color:#065F46}
</style>

<?php if(isset($_GET['saved'])): ?>
<div class="success-msg">✅ Preferenze salvate con successo!</div>
<?php endif; ?>

<div class="settings-card">
    <h1 class="settings-title">⚙️ Impostazioni Notifiche</h1>
    
    <form method="POST">
        <?php if($isAdmin): ?>
        <div class="settings-section">
            <h2 style="font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1rem 0">
                📅 Notifiche Scadenze Servizi
            </h2>
            <p style="font-size:.9rem;color:var(--cb-gray);margin-bottom:1rem">
                Riepilogo <strong>settimanale</strong> delle scadenze servizi agenzie (CB Suite, Canva, etc)
            </p>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_scadenze_email" <?= $userSettings['notify_scadenze_email'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📧 Email Settimanale</div>
                    <div class="checkbox-desc">Ricevi riepilogo email 1 volta a settimana con scadenze servizi imminenti (30 giorni)</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_scadenze_badge" <?= $userSettings['notify_scadenze_badge'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">🔔 Badge Header</div>
                    <div class="checkbox-desc">Mostra numero scadenze imminenti nella barra superiore</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_scadenze_dashboard" <?= $userSettings['notify_scadenze_dashboard'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📊 Widget Dashboard</div>
                    <div class="checkbox-desc">Mostra box "Scadenze Imminenti" nella homepage con calendario prossimi 30 giorni</div>
                </div>
            </label>
        </div>
        <?php endif; ?>
        
        <div class="settings-section">
            <h2 style="font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1rem 0">
                🎫 Notifiche Ticket
            </h2>
            <p style="font-size:.9rem;color:var(--cb-gray);margin-bottom:1rem">
                Ricevi notifiche <strong>immediate</strong> sui ticket assegnati a te
            </p>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_ticket_email" <?= $userSettings['notify_ticket_email'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📧 Email Immediata</div>
                    <div class="checkbox-desc">Ricevi email subito quando: ti viene assegnato un ticket, arriva una risposta, cambia stato</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_ticket_badge" <?= $userSettings['notify_ticket_badge'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">🎫 Badge Header</div>
                    <div class="checkbox-desc">Mostra numero di ticket non letti nella barra superiore (aggiornamento real-time)</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_ticket_dashboard" <?= $userSettings['notify_ticket_dashboard'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📊 Widget Dashboard</div>
                    <div class="checkbox-desc">Mostra box "Ticket" nella homepage con i tuoi ultimi 5 ticket aperti</div>
                </div>
            </label>
        </div>
        
        <button type="submit" class="save-btn">💾 Salva Preferenze</button>
    </form>
</div>

<script>
// Gestione interazione checkbox
document.querySelectorAll('.checkbox-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>

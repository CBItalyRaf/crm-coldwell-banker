<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/user_preferences.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Impostazioni Notifiche";

// Salva preferenze
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved = saveUserPreferences(
        $pdo,
        $user['id'],
        isset($_POST['notify_email']) ? 1 : 0,
        isset($_POST['notify_badge']) ? 1 : 0,
        isset($_POST['notify_dashboard']) ? 1 : 0
    );
    
    if ($saved) {
        header('Location: user_settings.php?saved=1');
        exit;
    }
}

// Carica preferenze attuali
$userSettings = getUserPreferences($pdo, $user['id']);

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
        <div class="settings-section">
            <h2 style="font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin:0 0 1rem 0">
                📅 Notifiche Scadenze Servizi
            </h2>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_email" <?= $userSettings['notify_scadenze_email'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📧 Email Settimanali</div>
                    <div class="checkbox-desc">Ricevi un riepilogo settimanale delle scadenze imminenti</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_badge" <?= $userSettings['notify_scadenze_badge'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">🔔 Badge Notifiche</div>
                    <div class="checkbox-desc">Mostra il numero di scadenze nell'header del CRM</div>
                </div>
            </label>
            
            <label class="checkbox-item">
                <input type="checkbox" name="notify_dashboard" <?= $userSettings['notify_scadenze_dashboard'] ? 'checked' : '' ?>>
                <div class="checkbox-label">
                    <div class="checkbox-title">📊 Widget Dashboard</div>
                    <div class="checkbox-desc">Mostra il box "Scadenze Imminenti" nella homepage</div>
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

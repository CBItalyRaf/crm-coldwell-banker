<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();
$pageTitle = "Impostazioni Email - CRM Coldwell Banker";

// Verifica tabella smtp_accounts esiste
try {
    $pdo->query("SELECT 1 FROM smtp_accounts LIMIT 1");
} catch(PDOException $e) {
    die('
    <div style="padding:2rem;max-width:800px;margin:2rem auto;background:#FEE2E2;border:2px solid #EF4444;border-radius:12px">
        <h2 style="color:#991B1B;margin-bottom:1rem">⚠️ Tabella Database Mancante</h2>
        <p style="margin-bottom:1rem">La tabella <code>smtp_accounts</code> non esiste nel database.</p>
        <p style="margin-bottom:1rem"><strong>SOLUZIONE:</strong></p>
        <ol style="margin-left:1.5rem;line-height:1.8">
            <li>Apri <strong>phpMyAdmin</strong></li>
            <li>Seleziona il database del CRM</li>
            <li>Click tab <strong>"SQL"</strong></li>
            <li>Copia e incolla il contenuto del file <code>smtp_accounts.sql</code></li>
            <li>Click <strong>"Esegui"</strong></li>
            <li>Ricarica questa pagina</li>
        </ol>
        <p style="margin-top:1.5rem">
            <a href="index.php" style="background:#3B82F6;color:white;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-block">← Torna alla Dashboard</a>
        </p>
    </div>
    ');
}

// Chiave criptazione (IMPORTANTE: cambiala in produzione!)
$encryption_key = 'CB_SMTP_2025_SECRET_KEY_CHANGE_ME';

// Salva account
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = $_POST['account_type'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $senderName = trim($_POST['sender_name'] ?? '');
    $currentUserEmail = $_POST['current_user_email'] ?? ''; // Email passata via hidden field
    
    // DEBUG LOG
    error_log("=== SMTP SAVE DEBUG ===");
    error_log("Current user email (from POST): $currentUserEmail");
    error_log("Account type: $accountType");
    error_log("Email to save: $email");
    error_log("Sender name: $senderName");
    
    if(empty($email) || empty($senderName)) {
        $error = "Compila email e nome mittente (la password è opzionale se già configurato)";
    } else {
        // Verifica permessi
        if($accountType === 'generic' && (!isset($user['crm_role']) || $user['crm_role'] !== 'admin')) {
            // Fallback: verifica da POST se $user è vuoto
            if(empty($currentUserEmail)) {
                $error = "Solo gli admin possono configurare l'account generico";
            }
        }
        
        if(!isset($error)) {
            // Usa email da POST (passata via hidden field)
            $userEmail = ($accountType === 'generic') ? NULL : $currentUserEmail;
            
            error_log("User email to save in DB: " . ($userEmail ?? 'NULL'));
            
            if(empty($password)) {
                // Verifica se account esiste già
                $checkStmt = $pdo->prepare("SELECT id FROM smtp_accounts WHERE " . 
                    ($userEmail ? "user_email = ?" : "user_email IS NULL") . 
                    " AND account_type = ?");
                $checkParams = $userEmail ? [$userEmail, $accountType] : [$accountType];
                $checkStmt->execute($checkParams);
                
                if(!$checkStmt->fetch()) {
                    $error = "Password obbligatoria per la prima configurazione";
                } else {
                    // Update senza password
                    $stmt = $pdo->prepare("
                        UPDATE smtp_accounts 
                        SET email = ?, sender_name = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE " . ($userEmail ? "user_email = ?" : "user_email IS NULL") . "
                        AND account_type = ?
                    ");
                    
                    $params = $userEmail ? [$email, $senderName, $userEmail, $accountType] : [$email, $senderName, $accountType];
                    
                    if($stmt->execute($params)) {
                        $success = "Account aggiornato con successo!";
                        error_log("SMTP Save - UPDATE SUCCESS");
                    } else {
                        $error = "Errore durante il salvataggio: " . print_r($stmt->errorInfo(), true);
                        error_log("SMTP Save - UPDATE ERROR: " . print_r($stmt->errorInfo(), true));
                    }
                }
            } else {
                // Cripta password
                $encryptedPassword = base64_encode(openssl_encrypt($password, 'AES-128-ECB', $encryption_key));
                
                error_log("SMTP Save - user_email to save: $userEmail, encrypted password length: " . strlen($encryptedPassword));
                
                // Salva in DB
                $stmt = $pdo->prepare("
                    INSERT INTO smtp_accounts (user_email, account_type, email, password, sender_name, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE
                        email = VALUES(email),
                        password = VALUES(password),
                        sender_name = VALUES(sender_name),
                        is_active = 1,
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                if($stmt->execute([$userEmail, $accountType, $email, $encryptedPassword, $senderName])) {
                    $success = "Account configurato con successo! (user_email salvato: $userEmail)";
                    error_log("SMTP Save - INSERT/UPDATE SUCCESS with user_email: " . $userEmail);
                } else {
                    $error = "Errore durante il salvataggio: " . print_r($stmt->errorInfo(), true);
                    error_log("SMTP Save - INSERT ERROR: " . print_r($stmt->errorInfo(), true));
                }
            }
        }
    }
}

// Carica account esistenti
$genericAccount = null;
$personalAccount = null;

// Account generico (solo admin vede)
if($user['crm_role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE account_type = 'generic' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $genericAccount = $stmt->fetch();
    error_log("SMTP Load - Generic account: " . ($genericAccount ? "FOUND (ID: {$genericAccount['id']})" : "NOT FOUND"));
}

// Account personale (usa email - FIX SSO: usa quello che c'è disponibile)
$userEmailForLoad = $user['email'] ?? $_SESSION['user']['email'] ?? null;
if($userEmailForLoad) {
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE user_email = ? AND account_type = 'personal' AND is_active = 1 LIMIT 1");
    $stmt->execute([$userEmailForLoad]);
    $personalAccount = $stmt->fetch();
    error_log("SMTP Load - Personal account for {$userEmailForLoad}: " . ($personalAccount ? "FOUND (ID: {$personalAccount['id']})" : "NOT FOUND"));
} else {
    $personalAccount = null;
    error_log("SMTP Load - Cannot load personal account: user email not available");
}

require_once 'header.php';
?>

<style>
.settings-container{max-width:800px;margin:0 auto}
.settings-header{margin-bottom:2rem}
.settings-title{font-size:1.75rem;font-weight:600;margin-bottom:.5rem}
.settings-subtitle{color:var(--cb-gray);font-size:1rem}
.account-card{background:white;padding:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.account-card.admin{border:2px solid var(--cb-bright-blue)}
.account-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:2px solid var(--bg)}
.account-icon{font-size:1.5rem}
.account-title{font-size:1.25rem;font-weight:600;flex:1}
.account-badge{background:var(--cb-bright-blue);color:white;padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem;font-size:.95rem}
.form-input{width:100%;padding:.875rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;transition:border-color .2s}
.form-input:focus{outline:none;border-color:var(--cb-bright-blue);box-shadow:0 0 0 3px rgba(31,105,255,.1)}
.form-hint{font-size:.85rem;color:var(--cb-gray);margin-top:.5rem}
.btn-save{background:var(--cb-bright-blue);color:white;border:none;padding:.875rem 2rem;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:background .2s;width:100%}
.btn-save:hover{background:var(--cb-blue)}
.alert{padding:1rem 1.5rem;border-radius:8px;margin-bottom:2rem}
.alert-success{background:#D1FAE5;border:1px solid #10B981;color:#065F46}
.alert-error{background:#FEE2E2;border:1px solid #EF4444;color:#991B1B}
.alert-info{background:#DBEAFE;border:1px solid #3B82F6;color:#1E40AF}
.info-box{background:var(--bg);padding:1.5rem;border-radius:8px;margin-bottom:2rem;border-left:4px solid var(--cb-bright-blue)}
.info-box h3{font-size:1rem;font-weight:600;margin-bottom:.75rem}
.info-box ul{margin:.5rem 0 0 1.5rem;line-height:1.8}
.status-indicator{display:inline-flex;align-items:center;gap:.5rem;font-size:.85rem;padding:.5rem 1rem;border-radius:6px;background:var(--bg)}
.status-indicator.configured{background:#D1FAE5;color:#065F46}
.status-indicator.not-configured{background:#FEE2E2;color:#991B1B}
@media(max-width:768px){
.account-card{padding:1.5rem}
}
</style>

<div class="settings-container">
<div class="settings-header">
<h1 class="settings-title">📧 Impostazioni Email</h1>
<p class="settings-subtitle">Configura gli account SMTP per l'invio delle newsletter</p>
</div>

<?php if(isset($success)): ?>
<div class="alert alert-success">
✅ <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="alert alert-error">
❌ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="info-box">
<h3>ℹ️ Come funziona</h3>
<ul>
<li><strong>Account Generico:</strong> Tutti possono usarlo per inviare newsletter (solo admin può configurarlo)</li>
<li><strong>Account Personale:</strong> Solo tu puoi usarlo, le email partono dal tuo indirizzo Office365</li>
<li>Le password sono criptate e salvate in modo sicuro nel database</li>
<li>Quando invii newsletter, scegli quale account usare dal dropdown "Invia da:"</li>
</ul>
</div>

<?php if($user['crm_role'] === 'admin'): ?>
<!-- Account Generico (solo admin) -->
<div class="account-card admin">
<div class="account-header">
<span class="account-icon">📧</span>
<h2 class="account-title">Account Generico</h2>
<span class="account-badge">Solo Admin</span>
</div>

<?php if($genericAccount): ?>
<div class="status-indicator configured">
✅ Account configurato e attivo
</div>
<p style="margin:1rem 0;color:var(--cb-gray);font-size:.9rem">
Email attuale: <strong><?= htmlspecialchars($genericAccount['email']) ?></strong><br>
Nome mittente: <strong><?= htmlspecialchars($genericAccount['sender_name']) ?></strong><br>
Ultimo aggiornamento: <?= date('d/m/Y H:i', strtotime($genericAccount['updated_at'])) ?>
</p>
<details>
<summary style="cursor:pointer;color:var(--cb-bright-blue);font-weight:600;margin-bottom:1rem">🔄 Modifica configurazione</summary>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="account_type" value="generic">
<input type="hidden" name="current_user_email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">

<div class="form-group">
<label class="form-label" for="generic_email">Email Office365 *</label>
<input type="email" id="generic_email" name="email" class="form-input" 
       value="<?= htmlspecialchars($genericAccount['email'] ?? '') ?>" 
       placeholder="newsletter@cbitaly.it" required>
<div class="form-hint">Indirizzo email Office365 da usare come mittente generico</div>
</div>

<div class="form-group">
<label class="form-label" for="generic_password">Password Office365 *</label>
<input type="password" id="generic_password" name="password" class="form-input" 
       placeholder="<?= $genericAccount ? '••••••••••••• (lascia vuoto per non modificare)' : 'Password account Office365' ?>" 
       <?= $genericAccount ? '' : 'required' ?>>
<div class="form-hint">
<?php if($genericAccount): ?>
Password attuale salvata. Compila solo se vuoi cambiarla.
<?php else: ?>
Se Office365 ha autenticazione a 2 fattori, serve una "password app" generata da Microsoft.
<?php endif; ?>
</div>
</div>

<div class="form-group">
<label class="form-label" for="generic_name">Nome Mittente *</label>
<input type="text" id="generic_name" name="sender_name" class="form-input" 
       value="<?= htmlspecialchars($genericAccount['sender_name'] ?? '') ?>" 
       placeholder="Coldwell Banker Italy" required>
<div class="form-hint">Nome visualizzato come mittente nelle email</div>
</div>

<button type="submit" class="btn-save">💾 Salva Account Generico</button>
</form>

<?php if($genericAccount): ?>
</details>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- Account Personale (tutti) -->
<div class="account-card">
<div class="account-header">
<span class="account-icon">👤</span>
<h2 class="account-title">Il Mio Account Personale</h2>
</div>

<?php if($personalAccount): ?>
<div class="status-indicator configured">
✅ Account configurato e attivo
</div>
<p style="margin:1rem 0;color:var(--cb-gray);font-size:.9rem">
Email: <strong><?= htmlspecialchars($personalAccount['email']) ?></strong><br>
Nome mittente: <strong><?= htmlspecialchars($personalAccount['sender_name']) ?></strong><br>
Ultimo aggiornamento: <?= date('d/m/Y H:i', strtotime($personalAccount['updated_at'])) ?>
</p>
<details>
<summary style="cursor:pointer;color:var(--cb-bright-blue);font-weight:600;margin-bottom:1rem">🔄 Modifica configurazione</summary>
<?php else: ?>
<div class="status-indicator not-configured">
⚠️ Account non ancora configurato
</div>
<p style="margin:1rem 0;color:var(--cb-gray)">
Configura il tuo account personale Office365 per poter inviare newsletter dal tuo indirizzo email.
</p>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="account_type" value="personal">
<input type="hidden" name="current_user_email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">

<div class="form-group">
<label class="form-label" for="personal_email">Email Office365 *</label>
<input type="email" id="personal_email" name="email" class="form-input" 
       value="<?= htmlspecialchars($personalAccount['email'] ?? $user['email']) ?>" 
       placeholder="<?= htmlspecialchars($user['email']) ?>" required>
<div class="form-hint">Il tuo indirizzo email Office365 personale</div>
</div>

<div class="form-group">
<label class="form-label" for="personal_password">Password Office365 *</label>
<input type="password" id="personal_password" name="password" class="form-input" 
       placeholder="<?= $personalAccount ? '••••••••••••• (lascia vuoto per non modificare)' : 'Password tuo account Office365' ?>" 
       <?= $personalAccount ? '' : 'required' ?>>
<div class="form-hint">
<?php if($personalAccount): ?>
Password attuale salvata. Compila solo se vuoi cambiarla.
<?php else: ?>
Se hai autenticazione a 2 fattori, genera una "password app" da Microsoft.
<?php endif; ?>
</div>
</div>

<div class="form-group">
<label class="form-label" for="personal_name">Nome Mittente *</label>
<input type="text" id="personal_name" name="sender_name" class="form-input" 
       value="<?= htmlspecialchars($personalAccount['sender_name'] ?? $user['name']) ?>" 
       placeholder="<?= htmlspecialchars($user['name']) ?>" required>
<div class="form-hint">Nome visualizzato come mittente nelle email</div>
</div>

<button type="submit" class="btn-save">💾 Salva Mio Account</button>
</form>

<?php if($personalAccount): ?>
</details>
<?php endif; ?>
</div>

<div class="alert alert-info">
<strong>💡 Suggerimento:</strong> Se Office365 ha autenticazione a 2 fattori (2FA), devi generare una "password app" specifica:
<ol style="margin:.75rem 0 0 1.5rem;line-height:1.8">
<li>Vai su <a href="https://account.microsoft.com/security" target="_blank" style="color:var(--cb-bright-blue)">account.microsoft.com/security</a></li>
<li>Clicca "Opzioni di verifica aggiuntive"</li>
<li>Seleziona "Crea password app"</li>
<li>Inserisci nome: "CRM Newsletter"</li>
<li>Copia la password generata e usala qui</li>
</ol>
</div>
</div>

<?php require_once 'footer.php'; ?>

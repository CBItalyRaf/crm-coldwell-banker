<?php
/**
 * Helper SMTP - Gestione account email da database
 */

// Chiave criptazione (IMPORTANTE: deve essere la stessa di settings_email.php)
define('SMTP_ENCRYPTION_KEY', 'CB_SMTP_2025_SECRET_KEY_CHANGE_ME');

/**
 * Ottieni account SMTP disponibili per l'utente (usa EMAIL invece di ID)
 */
function getAvailableSMTPAccounts($userEmail) {
    $pdo = getDB();
    $available = [];
    
    // Account generico - sempre disponibile se configurato
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE account_type = 'generic' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $generic = $stmt->fetch();
    
    if($generic) {
        $available[] = [
            'id' => 'smtp_' . $generic['id'],
            'label' => '📧 ' . $generic['sender_name'],
            'email' => $generic['email'],
            'is_personal' => false,
            'db_id' => $generic['id']
        ];
    }
    
    // Account personale - solo se configurato dall'utente
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE user_email = ? AND account_type = 'personal' AND is_active = 1 LIMIT 1");
    $stmt->execute([$userEmail]);
    $personal = $stmt->fetch();
    
    if($personal) {
        $available[] = [
            'id' => 'smtp_' . $personal['id'],
            'label' => '👤 Il tuo account (' . $personal['email'] . ')',
            'email' => $personal['email'],
            'is_personal' => true,
            'db_id' => $personal['id']
        ];
    }
    
    return $available;
}

/**
 * Ottieni credenziali SMTP per ID account (usa EMAIL per verifica permessi)
 */
function getSMTPCredentials($accountId, $userEmail) {
    if(!$accountId) return null;
    
    // Estrai DB ID da account ID (formato: smtp_123)
    $dbId = (int)str_replace('smtp_', '', $accountId);
    if(!$dbId) return null;
    
    $pdo = getDB();
    
    // Carica account
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE id = ? AND is_active = 1");
    $stmt->execute([$dbId]);
    $account = $stmt->fetch();
    
    if(!$account) return null;
    
    // Verifica permessi (usa EMAIL)
    if($account['account_type'] === 'personal' && $account['user_email'] !== $userEmail) {
        return null; // Non puoi usare account personale di altri
    }
    
    // Decripta password
    $decryptedPassword = openssl_decrypt(
        base64_decode($account['password']), 
        'AES-128-ECB', 
        SMTP_ENCRYPTION_KEY
    );
    
    if($decryptedPassword === false) {
        error_log("SMTP: Errore decriptazione password per account ID " . $account['id']);
        return null;
    }
    
    // Configurazione server Office365
    return [
        'email' => $account['email'],
        'password' => $decryptedPassword,
        'name' => $account['sender_name'],
        'server' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'auth' => true,
            'timeout' => 30
        ]
    ];
}

/**
 * Verifica se utente può usare account (usa EMAIL)
 */
function canUseSMTPAccount($accountId, $userEmail) {
    if(!$accountId) return false;
    
    $dbId = (int)str_replace('smtp_', '', $accountId);
    if(!$dbId) return false;
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE id = ? AND is_active = 1");
    $stmt->execute([$dbId]);
    $account = $stmt->fetch();
    
    if(!$account) return false;
    
    // Generico: tutti possono usare
    if($account['account_type'] === 'generic') return true;
    
    // Personale: solo proprietario (verifica via EMAIL)
    return ($account['user_email'] === $userEmail);
}

/**
 * Verifica se ci sono account SMTP configurati
 */
function hasSMTPAccounts() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) FROM smtp_accounts WHERE is_active = 1");
    return $stmt->fetchColumn() > 0;
}

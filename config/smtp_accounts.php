<?php
/**
 * CONFIGURAZIONE SMTP MULTI-ACCOUNT
 * 
 * ISTRUZIONI:
 * 1. Sostituisci i placeholder PASSWORD_XXX con le password reali
 * 2. Aggiungi account personali delle colleghe nella sezione $personal_accounts
 * 3. Salva il file
 * 
 * SICUREZZA:
 * - Account generico: tutti possono usarlo
 * - Account personali: solo il proprietario può usarlo
 */

// ========================================
// ACCOUNT GENERICO (tutti possono usare)
// ========================================
$generic_account = [
    'label' => 'Newsletter CB Italy',
    'email' => 'newsletter@cbitaly.it',
    'password' => 'PASSWORD_GENERICO_QUI',  // ← CAMBIA QUESTA
    'name' => 'Coldwell Banker Italy'
];

// ========================================
// ACCOUNT PERSONALI (solo proprietario)
// ========================================
$personal_accounts = [
    // Raffaella
    [
        'email' => 'raffaella.pace@cbitaly.it',
        'password' => 'PASSWORD_RAF_QUI',  // ← CAMBIA QUESTA
        'name' => 'Raffaella Pace'
    ],
    
    // Collega 1 - ESEMPIO, sostituisci con dati reali
    [
        'email' => 'collega1@cbitaly.it',
        'password' => 'PASSWORD_COLLEGA1_QUI',  // ← CAMBIA QUESTA
        'name' => 'Nome Collega 1'
    ],
    
    // Collega 2 - ESEMPIO, sostituisci con dati reali
    [
        'email' => 'collega2@cbitaly.it',
        'password' => 'PASSWORD_COLLEGA2_QUI',  // ← CAMBIA QUESTA
        'name' => 'Nome Collega 2'
    ],
    
    // AGGIUNGI ALTRE COLLEGHE QUI:
    // [
    //     'email' => 'altra@cbitaly.it',
    //     'password' => 'PASSWORD_ALTRA_QUI',
    //     'name' => 'Nome Altra'
    // ],
];

// ========================================
// CONFIGURAZIONE SERVER OFFICE365
// (uguale per tutti, NON modificare)
// ========================================
$smtp_server = [
    'host' => 'smtp.office365.com',
    'port' => 587,
    'encryption' => 'tls',
    'auth' => true,
    'timeout' => 30
];

// ========================================
// FUNZIONI HELPER
// ========================================

/**
 * Ottieni account disponibili per l'utente corrente
 */
function getAvailableAccounts($userEmail) {
    global $generic_account, $personal_accounts;
    
    $available = [];
    
    // Account generico - sempre disponibile
    $available[] = [
        'id' => 'generic',
        'label' => $generic_account['label'],
        'email' => $generic_account['email'],
        'is_personal' => false
    ];
    
    // Account personale - solo se email utente corrisponde
    foreach($personal_accounts as $account) {
        if(strtolower($account['email']) === strtolower($userEmail)) {
            $available[] = [
                'id' => 'personal_' . md5($account['email']),
                'label' => 'Il tuo account (' . $account['email'] . ')',
                'email' => $account['email'],
                'is_personal' => true
            ];
            break; // Solo un account personale per utente
        }
    }
    
    return $available;
}

/**
 * Ottieni credenziali SMTP per account selezionato
 */
function getSmtpCredentials($accountId, $userEmail) {
    global $generic_account, $personal_accounts, $smtp_server;
    
    // Account generico
    if($accountId === 'generic') {
        return [
            'email' => $generic_account['email'],
            'password' => $generic_account['password'],
            'name' => $generic_account['name'],
            'server' => $smtp_server
        ];
    }
    
    // Account personale - verifica permessi
    foreach($personal_accounts as $account) {
        $personalId = 'personal_' . md5($account['email']);
        if($accountId === $personalId && strtolower($account['email']) === strtolower($userEmail)) {
            return [
                'email' => $account['email'],
                'password' => $account['password'],
                'name' => $account['name'],
                'server' => $smtp_server
            ];
        }
    }
    
    // Account non trovato o non autorizzato
    return null;
}

/**
 * Verifica se utente può usare account
 */
function canUseAccount($accountId, $userEmail) {
    global $generic_account, $personal_accounts;
    
    // Generico: sempre OK
    if($accountId === 'generic') {
        return true;
    }
    
    // Personale: verifica proprietà
    foreach($personal_accounts as $account) {
        $personalId = 'personal_' . md5($account['email']);
        if($accountId === $personalId) {
            return strtolower($account['email']) === strtolower($userEmail);
        }
    }
    
    return false;
}

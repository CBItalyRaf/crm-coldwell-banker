<?php
/**
 * CRM - Sistema Autenticazione SSO
 * Verifica token SSO dalla Dashboard e gestisce sessione locale
 */

session_start();

// Se già loggato localmente nel CRM, ok
if (isset($_SESSION['crm_user'])) {
    return;
}

// Se arriva token SSO dalla Dashboard
if (isset($_GET['sso_token'])) {
    $token = $_GET['sso_token'];
    
    // URL Dashboard API per verifica token
    $dashboard_api = 'https://coldwellbankeritaly.tech/repository/dashboard/api/verify-sso-token.php';
    
    // Chiama Dashboard per verificare token
    $response = @file_get_contents($dashboard_api . '?token=' . urlencode($token));
    
    if ($response !== false) {
        $result = json_decode($response, true);
        
        // Token valido e user ha permesso CRM
        if ($result && isset($result['valid']) && $result['valid'] === true) {
            
            // Verifica che l'utente abbia accesso al CRM
            if (isset($result['user']['services']['crm'])) {
                
                // Salva dati user in sessione CRM
                $_SESSION['crm_user'] = [
                    'email' => $result['user']['email'],
                    'name' => $result['user']['name'],
                    'is_admin' => $result['user']['is_admin'] ?? false,
                    'services' => $result['user']['services'],
                    'crm_role' => $result['user']['services']['crm'] ?? 'viewer',
                    'logged_at' => date('Y-m-d H:i:s')
                ];
                
                // Redirect per rimuovere token dall'URL
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Non autenticato → redirect a Dashboard
header('Location: https://coldwellbankeritaly.tech/repository/dashboard/');
exit;

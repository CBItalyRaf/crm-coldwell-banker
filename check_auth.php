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
            } else {
                // User valido ma senza permesso CRM
                die('
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Accesso Negato</title>
                        <style>
                            body { font-family: Arial; background: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                            .error { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
                            h1 { color: #d32f2f; margin-bottom: 20px; }
                            p { color: #666; margin-bottom: 30px; }
                            a { background: #012169; color: white; padding: 10px 30px; text-decoration: none; border-radius: 5px; display: inline-block; }
                            a:hover { background: #0A3A7F; }
                        </style>
                    </head>
                    <body>
                        <div class="error">
                            <h1>🚫 Accesso Negato</h1>
                            <p>Non hai i permessi per accedere al CRM.<br>Contatta l\'amministratore.</p>
                            <a href="https://coldwellbankeritaly.tech/repository/dashboard/">← Torna alla Dashboard</a>
                        </div>
                    </body>
                    </html>
                ');
            }
        }
    }
    
    // Token non valido o errore API
    die('
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Errore Autenticazione</title>
            <style>
                body { font-family: Arial; background: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .error { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
                h1 { color: #d32f2f; margin-bottom: 20px; }
                p { color: #666; margin-bottom: 30px; }
                a { background: #012169; color: white; padding: 10px 30px; text-decoration: none; border-radius: 5px; display: inline-block; }
                a:hover { background: #0A3A7F; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>⚠️ Token Non Valido</h1>
                <p>Il token di autenticazione è scaduto o non valido.<br>Accedi nuovamente dalla Dashboard.</p>
                <a href="https://coldwellbankeritaly.tech/repository/dashboard/">← Vai alla Dashboard</a>
            </div>
        </body>
        </html>
    ');
}

// Nessun token e non loggato → redirect a Dashboard
header('Location: https://coldwellbankeritaly.tech/repository/dashboard/');
exit;
?>

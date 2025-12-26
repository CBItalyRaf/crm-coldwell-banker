<?php
session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DEBUG SSO</title></head><body>";
echo "<h1>🔍 DEBUG SSO</h1>";
echo "<p><strong>Session crm_user set:</strong> " . (isset($_SESSION['crm_user']) ? "✅ SÌ" : "❌ NO") . "</p>";

if (isset($_GET['sso_token'])) {
    $token = $_GET['sso_token'];
    echo "<p><strong>Token ricevuto:</strong> " . htmlspecialchars(substr($token, 0, 20)) . "...</p>";
    
    $dashboard_api = 'https://coldwellbankeritaly.tech/repository/dashboard/api/verify-sso-token.php';
    $url = $dashboard_api . '?token=' . urlencode($token);
    
    echo "<p><strong>Chiamata API a:</strong> " . htmlspecialchars($url) . "</p>";
    
    $response = @file_get_contents($url);
    echo "<p><strong>Risposta API:</strong> " . htmlspecialchars($response) . "</p>";
    
    if ($response !== false) {
        $result = json_decode($response, true);
        echo "<hr><h2>Dati Decodificati:</h2>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
        if ($result && isset($result['valid']) && $result['valid'] === true) {
            echo "<p style='color:green;font-size:20px;'>✅ TOKEN VALIDO</p>";
            
            if (isset($result['user']['services']['crm'])) {
                $_SESSION['crm_user'] = [
                    'email' => $result['user']['email'],
                    'name' => $result['user']['name'],
                    'is_admin' => $result['user']['is_admin'] ?? false,
                    'services' => $result['user']['services'],
                    'crm_role' => $result['user']['services']['crm'] ?? 'viewer',
                    'logged_at' => date('Y-m-d H:i:s')
                ];
                echo "<p style='color:green;font-size:20px;font-weight:bold;'>✅ SESSIONE CRM CREATA CON SUCCESSO!</p>";
                echo "<p><a href='index.php' style='font-size:18px;'>➡️ CLICCA QUI PER ANDARE A INDEX.PHP</a></p>";
            } else {
                echo "<p style='color:red;font-size:20px;'>❌ ERRORE: Permesso CRM mancante nei servizi utente</p>";
            }
        } else {
            echo "<p style='color:red;font-size:20px;'>❌ ERRORE: Token non valido o scaduto</p>";
        }
    } else {
        echo "<p style='color:red;font-size:20px;'>❌ ERRORE: Impossibile chiamare l'API Dashboard</p>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ Nessun token SSO ricevuto</p>";
    echo "<p>Dovresti arrivare qui con ?sso_token=... dalla Dashboard</p>";
}

echo "</body></html>";
?>

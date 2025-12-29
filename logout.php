<?php
/**
 * CRM - Logout
 * Distrugge sessione CRM e redirect a Dashboard
 */

session_start();

// Log logout prima di distruggere sessione
if (isset($_SESSION['crm_user']) && isset($_SESSION['login_time'])) {
    require_once 'config/database.php';
    require_once 'log_functions.php';
    
    $pdo = getDB();
    $duration = time() - $_SESSION['login_time'];
    
    // Trova l'ultimo login di questo utente
    $stmt = $pdo->prepare("SELECT id FROM login_logs WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['crm_user']['id'] ?? 0]);
    $loginLog = $stmt->fetch();
    
    if ($loginLog) {
        // Log logout
        logAccess(
            $pdo,
            $_SESSION['crm_user']['id'] ?? 0,
            $_SESSION['crm_user']['email'],
            'logout'
        );
        
        // Aggiorna durata sessione del login
        updateSessionDuration($pdo, $loginLog['id'], $duration);
    }
}

// Distruggi sessione CRM
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

session_destroy();

// Redirect a Dashboard
header('Location: https://coldwellbankeritaly.tech/repository/dashboard/');
exit;
?>

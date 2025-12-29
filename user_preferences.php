<?php
// helpers/user_preferences.php
// Funzioni per gestire preferenze utente

function getUserPreferences($pdo, $user_email) {
    // Se user_email è null/vuoto, ritorna preferenze default senza salvare
    if (empty($user_email)) {
        return [
            'user_email' => null,
            'notify_scadenze_email' => 0,
            'notify_scadenze_badge' => 0,
            'notify_scadenze_dashboard' => 0
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_email = :email");
    $stmt->execute(['email' => $user_email]);
    $prefs = $stmt->fetch();
    
    // Se non esistono preferenze, crea record con valori default (tutto 0)
    if (!$prefs) {
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_email, notify_scadenze_email, notify_scadenze_badge, notify_scadenze_dashboard) 
            VALUES (:email, 0, 0, 0)
        ");
        $stmt->execute(['email' => $user_email]);
        
        return [
            'user_email' => $user_email,
            'notify_scadenze_email' => 0,
            'notify_scadenze_badge' => 0,
            'notify_scadenze_dashboard' => 0
        ];
    }
    
    return $prefs;
}

function saveUserPreferences($pdo, $user_email, $email, $badge, $dashboard) {
    if (empty($user_email)) {
        return false;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_email, notify_scadenze_email, notify_scadenze_badge, notify_scadenze_dashboard) 
        VALUES (:user_email, :email, :badge, :dashboard)
        ON DUPLICATE KEY UPDATE 
            notify_scadenze_email = :email,
            notify_scadenze_badge = :badge,
            notify_scadenze_dashboard = :dashboard
    ");
    
    return $stmt->execute([
        'user_email' => $user_email,
        'email' => $email,
        'badge' => $badge,
        'dashboard' => $dashboard
    ]);
}

<?php
// helpers/user_preferences.php
// Funzioni per gestire preferenze utente

function getUserPreferences($pdo, $user_id) {
    // Se user_id è null/vuoto, ritorna preferenze default senza salvare
    if (empty($user_id)) {
        return [
            'user_id' => null,
            'notify_scadenze_email' => 0,
            'notify_scadenze_badge' => 0,
            'notify_scadenze_dashboard' => 0
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $prefs = $stmt->fetch();
    
    // Se non esistono preferenze, crea record con valori default (tutto 0)
    if (!$prefs) {
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, notify_scadenze_email, notify_scadenze_badge, notify_scadenze_dashboard) 
            VALUES (:user_id, 0, 0, 0)
        ");
        $stmt->execute(['user_id' => $user_id]);
        
        return [
            'user_id' => $user_id,
            'notify_scadenze_email' => 0,
            'notify_scadenze_badge' => 0,
            'notify_scadenze_dashboard' => 0
        ];
    }
    
    return $prefs;
}

function saveUserPreferences($pdo, $user_id, $email, $badge, $dashboard) {
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, notify_scadenze_email, notify_scadenze_badge, notify_scadenze_dashboard) 
        VALUES (:user_id, :email, :badge, :dashboard)
        ON DUPLICATE KEY UPDATE 
            notify_scadenze_email = :email,
            notify_scadenze_badge = :badge,
            notify_scadenze_dashboard = :dashboard
    ");
    
    return $stmt->execute([
        'user_id' => $user_id,
        'email' => $email,
        'badge' => $badge,
        'dashboard' => $dashboard
    ]);
}

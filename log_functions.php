<?php
// Funzioni per gestione log

/**
 * Log accesso/logout utente
 */
function logAccess($pdo, $userId, $username, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, username, action, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $username, $action, $ip, $userAgent]);
    
    return $pdo->lastInsertId();
}

/**
 * Aggiorna durata sessione al logout
 */
function updateSessionDuration($pdo, $loginLogId, $duration) {
    $stmt = $pdo->prepare("UPDATE login_logs SET session_duration = ? WHERE id = ?");
    $stmt->execute([$duration, $loginLogId]);
}

/**
 * Log modifica generica (INSERT/UPDATE/DELETE)
 * 
 * @param PDO $pdo
 * @param int $userId
 * @param string $username
 * @param string $tableName Nome tabella (es. 'agencies', 'agents')
 * @param int $recordId ID del record
 * @param string $action 'INSERT', 'UPDATE', 'DELETE'
 * @param array $changes Array associativo ['field_name' => ['old' => 'valore_vecchio', 'new' => 'valore_nuovo']]
 */
function logAudit($pdo, $userId, $username, $tableName, $recordId, $action, $changes = []) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    if ($action === 'INSERT' || $action === 'DELETE') {
        // Per INSERT e DELETE salviamo una singola riga senza dettagli campi
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, table_name, record_id, action, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $username, $tableName, $recordId, $action, $ip]);
    } else {
        // Per UPDATE salviamo una riga per ogni campo modificato
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, table_name, record_id, action, field_name, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($changes as $fieldName => $values) {
            $oldValue = $values['old'] ?? null;
            $newValue = $values['new'] ?? null;
            
            // Salta se il valore non è cambiato
            if ($oldValue === $newValue) {
                continue;
            }
            
            $stmt->execute([
                $userId,
                $username,
                $tableName,
                $recordId,
                $action,
                $fieldName,
                $oldValue,
                $newValue,
                $ip
            ]);
        }
    }
}

/**
 * Confronta array di dati vecchi e nuovi e restituisce solo i campi modificati
 * 
 * @param array $oldData Dati prima della modifica
 * @param array $newData Dati dopo la modifica
 * @return array Array di cambiamenti ['field' => ['old' => ..., 'new' => ...]]
 */
function getChangedFields($oldData, $newData) {
    $changes = [];
    
    foreach ($newData as $field => $newValue) {
        $oldValue = $oldData[$field] ?? null;
        
        // Normalizza null vs stringa vuota
        if ($oldValue === '' && $newValue === null) continue;
        if ($oldValue === null && $newValue === '') continue;
        
        // Se è cambiato, aggiungi
        if ($oldValue != $newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue
            ];
        }
    }
    
    return $changes;
}

/**
 * Formatta nome campo per visualizzazione user-friendly
 */
function formatFieldName($fieldName) {
    $labels = [
        'name' => 'Nome',
        'code' => 'Codice',
        'status' => 'Status',
        'broker_manager' => 'Broker Manager',
        'broker_mobile' => 'Cellulare Broker',
        'email' => 'Email',
        'phone' => 'Telefono',
        'city' => 'Città',
        'province' => 'Provincia',
        'address' => 'Indirizzo',
        'zip_code' => 'CAP',
        'tech_fee' => 'Tech Fee',
        'activation_date' => 'Data Attivazione',
        'contract_expiry' => 'Scadenza Contratto',
        'full_name' => 'Nome Completo',
        'mobile' => 'Cellulare',
        'email_corporate' => 'Email Aziendale',
        'email_personal' => 'Email Personale',
        'role' => 'Ruolo',
        'm365_plan' => 'Piano M365'
    ];
    
    return $labels[$fieldName] ?? ucfirst(str_replace('_', ' ', $fieldName));
}

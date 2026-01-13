<?php
/**
 * Activity Log Helper Functions
 * Usa tabella audit_log esistente
 */

/**
 * Logga un'azione nel sistema
 * 
 * @param PDO $pdo Connessione database
 * @param int|null $userId ID utente (può essere NULL)
 * @param string $username Email/username utente
 * @param string $actionType Tipo azione: INSERT, UPDATE, DELETE
 * @param string $tableName Nome tabella: agencies, agents, tickets, etc
 * @param int $recordId ID record
 * @param array|null $changes Array associativo con modifiche (solo per UPDATE)
 * @return bool Success
 */
function logActivity($pdo, $userId, $username, $actionType, $tableName, $recordId, $changes = null) {
    try {
        // Validazione parametri
        if (empty($username) || empty($actionType) || empty($tableName) || empty($recordId)) {
            error_log("logActivity: parametri mancanti");
            return false;
        }
        
        // Validazione action
        $validActions = ['INSERT', 'UPDATE', 'DELETE'];
        if (!in_array($actionType, $validActions)) {
            error_log("logActivity: action non valido: $actionType");
            return false;
        }
        
        // IP Address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Per INSERT e DELETE: un solo record senza field_name
        if ($actionType === 'INSERT' || $actionType === 'DELETE') {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log 
                (user_id, username, table_name, record_id, action, field_name, old_value, new_value, ip_address)
                VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, ?)
            ");
            
            return $stmt->execute([
                $userId,
                $username,
                $tableName,
                $recordId,
                $actionType,
                $ipAddress
            ]);
        }
        
        // Per UPDATE: un record per ogni campo modificato
        if ($actionType === 'UPDATE' && !empty($changes)) {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log 
                (user_id, username, table_name, record_id, action, field_name, old_value, new_value, ip_address)
                VALUES (?, ?, ?, ?, 'UPDATE', ?, ?, ?, ?)
            ");
            
            foreach ($changes as $fieldName => $values) {
                $oldValue = isset($values['old']) ? (string)$values['old'] : null;
                $newValue = isset($values['new']) ? (string)$values['new'] : null;
                
                $stmt->execute([
                    $userId,
                    $username,
                    $tableName,
                    $recordId,
                    $fieldName,
                    $oldValue,
                    $newValue,
                    $ipAddress
                ]);
            }
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("logActivity FAILED: " . $e->getMessage());
        return false;
    }
}

/**
 * Confronta due array e restituisce solo i campi modificati
 */
function getChangedFields($oldData, $newData) {
    $changes = [];
    
    foreach ($newData as $key => $newValue) {
        if (!array_key_exists($key, $oldData)) {
            continue;
        }
        
        $oldValue = $oldData[$key];
        
        // Normalizza NULL
        $oldValue = $oldValue === null ? '' : (string)$oldValue;
        $newValue = $newValue === null ? '' : (string)$newValue;
        
        if ($oldValue !== $newValue) {
            $changes[$key] = [
                'old' => $oldData[$key],
                'new' => $newData[$key]
            ];
        }
    }
    
    return $changes;
}

/**
 * Wrapper sicuro - NON blocca MAI l'esecuzione
 */
function safeLogActivity($pdo, $userId, $username, $actionType, $tableName, $recordId, $changes = null) {
    try {
        logActivity($pdo, $userId, $username, $actionType, $tableName, $recordId, $changes);
    } catch (Exception $e) {
        error_log("safeLogActivity FAILED: " . $e->getMessage());
    }
}

/**
 * Formatta il tipo di azione per visualizzazione
 */
function formatActionType($actionType) {
    $labels = [
        'INSERT' => '➕ Creazione',
        'UPDATE' => '✏️ Modifica',
        'DELETE' => '🗑️ Eliminazione'
    ];
    return $labels[$actionType] ?? $actionType;
}

/**
 * Formatta il nome tabella per visualizzazione
 */
function formatTableName($tableName) {
    $labels = [
        'agencies' => '🏢 Agenzia',
        'agents' => '👤 Agente',
        'tickets' => '🎫 Ticket',
        'ticket_categories' => '🏷️ Categoria Ticket',
        'services' => '⚙️ Servizio',
        'documents' => '📄 Documento'
    ];
    return $labels[$tableName] ?? $tableName;
}


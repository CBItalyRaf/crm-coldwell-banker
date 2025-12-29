<?php
require_once 'config/database.php';

$pdo = getDB();

try {
    // Verifica lunghezza attuale
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'cbcrm' 
        AND TABLE_NAME = 'agency_services' 
        AND COLUMN_NAME = 'service_name'
    ");
    $current = $stmt->fetch();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Fix service_name Field</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            .info { background: #DBEAFE; border-left: 4px solid #3B82F6; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            .success { background: #D1FAE5; border-left: 4px solid #10B981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
            h1 { color: #012169; }
        </style>
    </head>
    <body>
        <h1>🔧 Fix service_name Field Size</h1>";
    
    echo "<div class='info'>
        <strong>📊 Stato attuale:</strong><br>
        Campo: <code>agency_services.service_name</code><br>
        Tipo: <code>{$current['COLUMN_TYPE']}</code><br>
        Lunghezza massima: <code>{$current['CHARACTER_MAXIMUM_LENGTH']} caratteri</code>
    </div>";
    
    // Modifica campo
    if ($current['CHARACTER_MAXIMUM_LENGTH'] < 100) {
        echo "<div class='warning'>
            ⚠️ Il campo è troppo corto per 'casella_mail_agenzia' (21 caratteri).<br>
            Eseguo ALTER TABLE per portarlo a VARCHAR(100)...
        </div>";
        
        $pdo->exec("ALTER TABLE agency_services MODIFY COLUMN service_name VARCHAR(100) NOT NULL");
        
        echo "<div class='success'>
            ✅ <strong>Campo allargato con successo!</strong><br>
            Nuovo tipo: <code>VARCHAR(100)</code><br><br>
            Ora puoi salvare 'casella_mail_agenzia' senza problemi.
        </div>";
    } else {
        echo "<div class='success'>
            ✅ Il campo è già abbastanza grande (100+ caratteri). Nessuna modifica necessaria.
        </div>";
    }
    
    echo "<p><a href='servizi_edit.php?code=CBI162' style='background:#012169;color:white;padding:0.75rem 1.5rem;text-decoration:none;border-radius:8px;display:inline-block'>← Torna a Servizi</a></p>
    </body>
    </html>";
    
} catch(Exception $e) {
    die('ERRORE: ' . $e->getMessage());
}

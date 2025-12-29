<?php
require_once 'config/database.php';

$pdo = getDB();

try {
    // Verifica quali servizi esistono già
    $stmt = $pdo->query("SELECT service_name FROM services_master");
    $existing = array_column($stmt->fetchAll(), 'service_name');
    
    $toInsert = [
        'Casella Mail Agenzia' => 0.00,
        'EuroMq' => 0.00,
        'Gestim' => 0.00
    ];
    
    $inserted = [];
    $skipped = [];
    
    foreach($toInsert as $name => $price) {
        if (in_array($name, $existing)) {
            $skipped[] = $name;
            continue;
        }
        
        $stmt = $pdo->prepare("INSERT INTO services_master (service_name, default_price) VALUES (:name, :price)");
        $stmt->execute(['name' => $name, 'price' => $price]);
        $inserted[] = $name;
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Inserimento Servizi</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            .success { background: #D1FAE5; border-left: 4px solid #10B981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            .info { background: #DBEAFE; border-left: 4px solid #3B82F6; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            .btn { background: #012169; color: white; padding: .75rem 1.5rem; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 1rem; }
            h1 { color: #012169; }
        </style>
    </head>
    <body>
        <h1>✅ Servizi Inseriti in services_master</h1>
        
        <?php if(!empty($inserted)): ?>
        <div class="success">
            <strong>✓ Servizi inseriti con successo:</strong>
            <ul>
                <?php foreach($inserted as $name): ?>
                <li><?= htmlspecialchars($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($skipped)): ?>
        <div class="info">
            <strong>ℹ️ Servizi già esistenti (non inseriti):</strong>
            <ul>
                <?php foreach($skipped as $name): ?>
                <li><?= htmlspecialchars($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <a href="check_services_master.php" class="btn">📋 Vedi tutti i servizi</a>
    </body>
    </html>
    <?php
    
} catch(Exception $e) {
    die('ERRORE: ' . $e->getMessage());
}

<?php
/**
 * GitHub Webhook - Auto Deploy
 * Riceve notifica da GitHub e aggiorna automaticamente
 */

// Log per debug
$logFile = '/tmp/webhook.log';

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Esegui git pull
$output = [];
$returnCode = 0;

chdir('/var/www/admin.mycb.it');
exec('git fetch --all 2>&1', $output, $returnCode);
exec('git reset --hard origin/main 2>&1', $output, $returnCode);
exec('git pull 2>&1', $output, $returnCode);

// Log risultato
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Deploy eseguito\n" . implode("\n", $output) . "\n\n", FILE_APPEND);

// Risposta OK
http_response_code(200);
echo "Deploy OK";
?>

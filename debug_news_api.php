<?php
// Test diretto API con debug completo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test API News CB - Debug Dettagliato</h1>";
echo "<style>body{font-family:monospace;padding:2rem}pre{background:#f5f5f5;padding:1rem;border-radius:8px;overflow-x:auto}.error{color:red}.success{color:green}</style>";

$baseUrl = 'https://coldwellbankeritaly.tech/repository/cb-news/api/v1';
$token = '27|O8cC2pZInPq1n3CgX5pYrNnLngsBbuqWgETqICLT2d5c5131';

echo "<h2>Configurazione</h2>";
echo "<pre>";
echo "Base URL: $baseUrl\n";
echo "Token: " . substr($token, 0, 20) . "...\n";
echo "Endpoint Test: /articles\n";
echo "</pre>";

// Test 1: Connessione base
echo "<h2>Test 1: Ping Server</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://coldwellbankeritaly.tech');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if($httpCode == 200 || $httpCode == 301 || $httpCode == 302) {
    echo "<p class='success'>✓ Server raggiungibile (HTTP $httpCode)</p>";
} else {
    echo "<p class='error'>✗ Server non raggiungibile</p>";
    if($curlError) {
        echo "<pre class='error'>CURL Error: $curlError</pre>";
    }
}

// Test 2: API Articles
echo "<h2>Test 2: GET /articles</h2>";
$url = $baseUrl . '/articles?limit=3';

echo "<pre>";
echo "URL completa: $url\n";
echo "Headers inviati:\n";
echo "  Authorization: Bearer [token]\n";
echo "  Accept: application/json\n";
echo "</pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disabilita verifica SSL per test
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

curl_close($ch);

echo "<h3>Risposta</h3>";
echo "<pre>";
echo "HTTP Code: $httpCode\n";
if($curlError) {
    echo "CURL Error: $curlError\n";
}
echo "\nHeaders Info:\n";
echo "  Total Time: " . $curlInfo['total_time'] . "s\n";
echo "  Content Type: " . ($curlInfo['content_type'] ?? 'N/A') . "\n";
echo "  Size Download: " . $curlInfo['size_download'] . " bytes\n";
echo "</pre>";

if($httpCode == 200) {
    echo "<p class='success'>✓ API risponde correttamente</p>";
    echo "<h3>JSON Response</h3>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
    
    $data = json_decode($response, true);
    if($data) {
        echo "<p class='success'>✓ JSON valido</p>";
        echo "<p>Articoli trovati: " . count($data['data'] ?? []) . "</p>";
    } else {
        echo "<p class='error'>✗ JSON non valido</p>";
    }
} else {
    echo "<p class='error'>✗ API Error (HTTP $httpCode)</p>";
    echo "<h3>Response Body:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

echo "<h3>CURL Verbose Log</h3>";
echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";

// Test 3: Categories
echo "<hr><h2>Test 3: GET /categories</h2>";
$url = $baseUrl . '/categories';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "HTTP Code: $httpCode\n";
if($curlError) {
    echo "CURL Error: $curlError\n";
}
echo "</pre>";

if($httpCode == 200) {
    echo "<p class='success'>✓ Categories OK</p>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
} else {
    echo "<p class='error'>✗ Categories Error</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

echo "<hr><p>Test completato: " . date('d/m/Y H:i:s') . "</p>";
?>

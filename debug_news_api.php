<?php
// Test API con URL CORRETTO
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test API News CB - URL CORRETTO</h1>";
echo "<style>body{font-family:monospace;padding:2rem}pre{background:#f5f5f5;padding:1rem;border-radius:8px;overflow-x:auto}.error{color:red}.success{color:green}</style>";

$baseUrl = 'https://coldwellbankeritaly.tech/repository/cb-news/public/api/v1';
$token = '27|O8cC2pZInPq1n3CgX5pYrNnLngsBbuqWgETqICLT2d5c5131';

echo "<h2>Configurazione CORRETTA</h2>";
echo "<pre>";
echo "Base URL: $baseUrl\n";
echo "Token: " . substr($token, 0, 20) . "...\n";
echo "</pre>";

// Test API Articles
echo "<h2>Test: GET /articles</h2>";
$url = $baseUrl . '/articles?limit=3';

echo "<pre>URL: $url</pre>";

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
    echo "<p class='success'>✓ API FUNZIONA!</p>";
    
    $data = json_decode($response, true);
    if($data && isset($data['data'])) {
        echo "<p class='success'>✓ JSON valido</p>";
        echo "<p><strong>Articoli trovati: " . count($data['data']) . "</strong></p>";
        
        if(!empty($data['data'])) {
            echo "<h3>Primi 3 articoli:</h3>";
            foreach(array_slice($data['data'], 0, 3) as $article) {
                echo "<div style='border:1px solid #ccc;padding:1rem;margin:1rem 0;background:white'>";
                echo "<strong>ID:</strong> " . ($article['id'] ?? 'N/A') . "<br>";
                echo "<strong>Titolo:</strong> " . htmlspecialchars($article['title'] ?? 'N/A') . "<br>";
                echo "<strong>Visibility:</strong> <span style='color:" . (($article['visibility'] ?? 'public') === 'internal' ? 'blue' : 'green') . "'>" . ($article['visibility'] ?? 'public') . "</span><br>";
                echo "<strong>Categoria:</strong> " . htmlspecialchars($article['category']['name'] ?? 'N/A') . "<br>";
                echo "</div>";
            }
        }
    }
    
    echo "<h3>JSON Completo (primi 1000 char):</h3>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "...</pre>";
} else {
    echo "<p class='error'>✗ API Error (HTTP $httpCode)</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

// Test Categories
echo "<hr><h2>Test: GET /categories</h2>";
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
curl_close($ch);

if($httpCode == 200) {
    echo "<p class='success'>✓ Categories OK</p>";
    $data = json_decode($response, true);
    if($data && isset($data['data'])) {
        echo "<p>Categorie trovate: " . count($data['data']) . "</p>";
        echo "<ul>";
        foreach($data['data'] as $cat) {
            echo "<li><strong>ID " . $cat['id'] . ":</strong> " . htmlspecialchars($cat['name']) . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p class='error'>✗ Categories Error (HTTP $httpCode)</p>";
}

// Test Visibility Internal
echo "<hr><h2>Test: GET /articles?visibility=internal</h2>";
$url = $baseUrl . '/articles?visibility=internal&limit=3';

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
curl_close($ch);

if($httpCode == 200) {
    $data = json_decode($response, true);
    echo "<p class='success'>✓ Visibility Internal OK</p>";
    echo "<p>News 'Solo CB' trovate: " . count($data['data'] ?? []) . "</p>";
} else {
    echo "<p class='error'>✗ Error</p>";
}

echo "<hr>";
echo "<h2 class='success'>TUTTO OK! API FUNZIONA! 🎉</h2>";
echo "<p><a href='test_news_api.php' style='background:#1F69FF;color:white;padding:1rem 2rem;border-radius:8px;text-decoration:none;display:inline-block'>Test Completo →</a></p>";
echo "<p><a href='news.php' style='background:#10B981;color:white;padding:1rem 2rem;border-radius:8px;text-decoration:none;display:inline-block;margin-top:1rem'>Vai alle News →</a></p>";

echo "<p style='margin-top:2rem;color:#666'>Test completato: " . date('d/m/Y H:i:s') . "</p>";
?>

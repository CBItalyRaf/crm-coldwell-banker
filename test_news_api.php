<?php
// Test API News CB Italia
// Esegui questo file per verificare che l'API funzioni

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'helpers/news_api.php';

echo "<h1>Test API News CB Italia</h1>";
echo "<style>body{font-family:sans-serif;padding:2rem;max-width:900px;margin:0 auto}pre{background:#f5f5f5;padding:1rem;border-radius:8px;overflow-x:auto}.success{color:#059669;font-weight:bold}.error{color:#EF4444;font-weight:bold}</style>";

// Test 1: GET /articles
echo "<h2>Test 1: Lista Articoli</h2>";
$articles = getNewsArticles(5);

if($articles) {
    echo "<p class='success'>✓ API funzionante</p>";
    echo "<p>Totale articoli: " . ($articles['total'] ?? 'N/A') . "</p>";
    echo "<p>Articoli ricevuti: " . count($articles['data'] ?? []) . "</p>";
    
    if(!empty($articles['data'])) {
        echo "<h3>Primi 3 articoli:</h3>";
        foreach(array_slice($articles['data'], 0, 3) as $article) {
            echo "<div style='border:1px solid #e5e7eb;padding:1rem;margin-bottom:1rem;border-radius:8px'>";
            echo "<strong>ID:</strong> " . ($article['id'] ?? 'N/A') . "<br>";
            echo "<strong>Titolo:</strong> " . htmlspecialchars($article['title'] ?? 'N/A') . "<br>";
            echo "<strong>Categoria:</strong> " . htmlspecialchars($article['category']['name'] ?? 'N/A') . "<br>";
            echo "<strong>Data:</strong> " . ($article['published_at'] ?? $article['created_at'] ?? 'N/A') . "<br>";
            echo "<strong>Immagine:</strong> " . (!empty($article['image_url']) ? 'Sì' : 'No') . "<br>";
            echo "</div>";
        }
    }
    
    echo "<h3>Risposta completa (JSON):</h3>";
    echo "<pre>" . json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p class='error'>✗ API non risponde o errore</p>";
    echo "<p>Verifica:</p>";
    echo "<ul>";
    echo "<li>Token valido</li>";
    echo "<li>URL API corretto</li>";
    echo "<li>Connessione server</li>";
    echo "<li>Controlla error_log per dettagli</li>";
    echo "</ul>";
}

// Test 2: GET /categories
echo "<hr style='margin:2rem 0'>";
echo "<h2>Test 2: Lista Categorie</h2>";
$categories = getNewsCategories();

if($categories) {
    echo "<p class='success'>✓ Categorie caricate</p>";
    echo "<p>Totale categorie: " . count($categories['data'] ?? []) . "</p>";
    
    if(!empty($categories['data'])) {
        echo "<h3>Categorie disponibili:</h3>";
        echo "<ul>";
        foreach($categories['data'] as $cat) {
            echo "<li><strong>ID " . $cat['id'] . ":</strong> " . htmlspecialchars($cat['name']) . "</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Risposta completa (JSON):</h3>";
    echo "<pre>" . json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p class='error'>✗ Categorie non caricate</p>";
}

// Test 3: GET /articles/{id} (solo se abbiamo articoli)
if(!empty($articles['data'][0]['id'])) {
    echo "<hr style='margin:2rem 0'>";
    echo "<h2>Test 3: Dettaglio Articolo</h2>";
    
    $testId = $articles['data'][0]['id'];
    echo "<p>Test con ID: " . $testId . "</p>";
    
    $article = getNewsArticle($testId);
    
    if($article && !empty($article['data'])) {
        echo "<p class='success'>✓ Articolo caricato</p>";
        echo "<h3>Dettagli:</h3>";
        echo "<div style='border:1px solid #e5e7eb;padding:1rem;border-radius:8px'>";
        echo "<strong>Titolo:</strong> " . htmlspecialchars($article['data']['title']) . "<br>";
        echo "<strong>Autore:</strong> " . htmlspecialchars($article['data']['author'] ?? 'N/A') . "<br>";
        echo "<strong>Contenuto:</strong> " . (strlen($article['data']['content'] ?? '') > 0 ? 'Presente' : 'Assente') . "<br>";
        echo "<strong>Excerpt:</strong> " . htmlspecialchars(substr($article['data']['excerpt'] ?? '', 0, 100)) . "...<br>";
        echo "</div>";
        
        echo "<h3>Risposta completa (JSON - primi 1000 caratteri):</h3>";
        $json = json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "<pre>" . substr($json, 0, 1000) . "..." . "</pre>";
    } else {
        echo "<p class='error'>✗ Articolo non caricato</p>";
    }
}

// Test 4: Ricerca
echo "<hr style='margin:2rem 0'>";
echo "<h2>Test 4: Ricerca</h2>";
$searchResults = getNewsArticles(5, 'immobiliare');

if($searchResults) {
    echo "<p class='success'>✓ Ricerca funzionante</p>";
    echo "<p>Risultati per 'immobiliare': " . count($searchResults['data'] ?? []) . "</p>";
} else {
    echo "<p class='error'>✗ Ricerca non funziona</p>";
}

// Riepilogo
echo "<hr style='margin:2rem 0'>";
echo "<h2>Riepilogo</h2>";
$tests = [
    'Lista articoli' => !empty($articles),
    'Categorie' => !empty($categories),
    'Dettaglio articolo' => !empty($article),
    'Ricerca' => !empty($searchResults)
];

$passed = count(array_filter($tests));
$total = count($tests);

echo "<p>Test passati: <strong>$passed/$total</strong></p>";

if($passed === $total) {
    echo "<p class='success' style='font-size:1.2rem'>✓ Tutti i test superati! API funzionante al 100%</p>";
    echo "<p><a href='news.php' style='background:#1F69FF;color:white;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-block;margin-top:1rem'>Vai alle News →</a></p>";
} else {
    echo "<p class='error' style='font-size:1.2rem'>✗ Alcuni test falliti. Controlla configurazione API.</p>";
}

echo "<hr style='margin:2rem 0'>";
echo "<p style='color:#6B7280;font-size:.9rem'>Test completato: " . date('d/m/Y H:i:s') . "</p>";
?>

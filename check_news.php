<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Check auth...<br>";
require_once 'check_auth.php';
echo "OK<br>";

echo "2. Check helpers/news_api.php...<br>";
if(!file_exists('helpers/news_api.php')) {
    die("ERRORE: helpers/news_api.php non esiste!");
}
require_once 'helpers/news_api.php';
echo "OK<br>";

echo "3. Test API call...<br>";
$result = getNewsArticles(3);
echo "Risultato: ";
var_dump($result);
echo "<br>";

if($result === null) {
    echo "<strong style='color:red'>API NON RISPONDE!</strong><br>";
    echo "Possibili cause:<br>";
    echo "- URL API errato<br>";
    echo "- Token scaduto<br>";
    echo "- Server non raggiungibile<br>";
} else {
    echo "<strong style='color:green'>API FUNZIONA!</strong><br>";
    echo "News trovate: " . count($result['data'] ?? []) . "<br>";
}

echo "<hr>";
echo "<a href='news.php'>Vai a news.php</a>";
?>

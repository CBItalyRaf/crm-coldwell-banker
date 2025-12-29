<?php
require_once 'check_auth.php';

echo "<h1>DEBUG UTENTE SESSIONE</h1>";
echo "<pre>";
print_r($_SESSION['crm_user']);
echo "</pre>";

echo "<h2>Campo ID:</h2>";
echo "user['id'] = " . ($user['id'] ?? 'NULL') . "<br>";
echo "user['user_id'] = " . ($user['user_id'] ?? 'NULL') . "<br>";
echo "user['ID'] = " . ($user['ID'] ?? 'NULL') . "<br>";

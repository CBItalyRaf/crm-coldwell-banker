<?php
/**
 * Genera token SSO per accesso portale agenzia
 * Solo per admin e editor
 */

require_once 'check_auth.php';
require_once 'config/database.php';

// Verifica permessi (solo admin e editor)
if (!in_array($_SESSION['crm_user']['crm_role'], ['admin', 'editor'])) {
    http_response_code(403);
    die('Accesso negato. Solo admin e editor possono accedere ai portali.');
}

// Verifica parametro agency_code
$agencyCode = $_GET['agency'] ?? '';
if (empty($agencyCode)) {
    http_response_code(400);
    die('Codice agenzia mancante.');
}

$pdo = getDB();

// Verifica che l'agenzia esista e sia attiva
$stmt = $pdo->prepare("SELECT code, name, status FROM agencies WHERE code = :code");
$stmt->execute([':code' => $agencyCode]);
$agency = $stmt->fetch();

if (!$agency) {
    http_response_code(404);
    die('Agenzia non trovata.');
}

if ($agency['status'] !== 'Active') {
    http_response_code(400);
    die('Impossibile accedere al portale. Agenzia non attiva (status: ' . htmlspecialchars($agency['status']) . ')');
}

// Genera token sicuro
$token = bin2hex(random_bytes(32)); // 64 caratteri
$expiresAt = date('Y-m-d H:i:s', time() + 300); // Valido 5 minuti
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

// Salva token nel database
try {
    $stmt = $pdo->prepare("
        INSERT INTO portal_sso_tokens 
        (token, agency_code, admin_email, admin_role, expires_at, ip_address) 
        VALUES 
        (:token, :agency_code, :admin_email, :admin_role, :expires_at, :ip_address)
    ");
    
    $stmt->execute([
        ':token' => $token,
        ':agency_code' => $agencyCode,
        ':admin_email' => $_SESSION['crm_user']['email'],
        ':admin_role' => $_SESSION['crm_user']['crm_role'],
        ':expires_at' => $expiresAt,
        ':ip_address' => $ipAddress
    ]);
    
    // Pulizia token scaduti (mantieni DB pulito)
    $pdo->exec("DELETE FROM portal_sso_tokens WHERE expires_at < NOW() OR used_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    
} catch (PDOException $e) {
    error_log("Errore creazione token SSO: " . $e->getMessage());
    http_response_code(500);
    die('Errore durante la generazione del token di accesso.');
}

// Redirect al portale con token
$portalUrl = "https://mycb.it/sso_login.php?token=" . urlencode($token);

echo "<!-- DEBUG: Redirect to $portalUrl -->";
echo "<h1>Reindirizzamento al portale...</h1>";
echo "<p>Token: " . htmlspecialchars($token) . "</p>";
echo "<p>URL: <a href='$portalUrl'>$portalUrl</a></p>";
echo "<script>setTimeout(function(){ window.location.href = '$portalUrl'; }, 2000);</script>";

// header("Location: " . $portalUrl);
exit;

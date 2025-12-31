<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';
require_once 'helpers/smtp_helper.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: news_newsletter.php');
    exit;
}

$newsIds = explode(',', $_POST['news_ids'] ?? '');
$recipients = $_POST['recipients'] ?? '';
$subject = $_POST['subject'] ?? '';
$introMessage = $_POST['intro_message'] ?? '';
$sendTest = isset($_POST['send_test']);
$senderAccount = $_POST['sender_account'] ?? '';

// Validazioni base
if(empty($newsIds) || empty($recipients) || empty($subject)) {
    die('Dati mancanti: assicurati di compilare tutti i campi obbligatori');
}

// SICUREZZA: Verifica permessi account mittente (usa EMAIL)
if(!canUseSMTPAccount($senderAccount, $user['email'])) {
    die('❌ ERRORE SICUREZZA: Non sei autorizzato a usare questo account mittente.<br><br>Puoi usare solo l\'account generico o il tuo account personale.');
}

// Ottieni credenziali SMTP dal database (usa EMAIL)
$smtpCreds = getSMTPCredentials($senderAccount, $user['email']);
if(!$smtpCreds) {
    die('❌ ERRORE: Account mittente non valido o non configurato.<br><br><a href="settings_email.php">Vai alle Impostazioni Email →</a>');
}

// Parse recipients
$recipientsList = array_map('trim', explode(',', $recipients));
$recipientsList = array_filter($recipientsList, function($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
});

if(empty($recipientsList)) {
    die('Nessun destinatario valido trovato. Verifica gli indirizzi email inseriti.');
}

// Carica dettagli news
$newsDetails = [];
foreach($newsIds as $id) {
    $id = trim($id);
    if(empty($id)) continue;
    
    $article = getNewsArticle($id);
    if($article && isset($article['id'])) {
        $newsDetails[] = $article;
    }
}

if(empty($newsDetails)) {
    die('Nessuna news valida selezionata. IDs ricevuti: ' . implode(', ', $newsIds));
}

// Crea HTML newsletter
$html = '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlspecialchars($subject) . '</title>';
$html .= '<style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f5f7fa;margin:0;padding:0}.container{max-width:700px;margin:0 auto;background:white}.header{background:#012169;color:white;padding:2rem;text-align:center}.header img{height:40px;margin-bottom:1rem}.header h1{margin:0;font-size:1.5rem}.intro{padding:2rem;background:#EFF6FF;border-left:4px solid #3B82F6}.intro p{margin:0;font-size:1rem;line-height:1.8}.news-item{padding:2rem;border-bottom:2px solid #f3f4f6}.news-item.internal{background:#EFF6FF}.news-badge{display:inline-block;padding:.25rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;text-transform:uppercase;margin-bottom:.75rem}.badge-internal{background:#3B82F6;color:white}.badge-category{background:#E5E7EB;color:#6B7280;margin-left:.5rem}.news-title{font-size:1.5rem;font-weight:700;color:#012169;margin:.5rem 0 1rem;line-height:1.3}.news-meta{color:#6D7180;font-size:.9rem;margin-bottom:1rem}.news-excerpt{color:#4B5563;font-size:1rem;line-height:1.8;margin-bottom:1rem}.news-image{width:100%;max-width:100%;height:auto;border-radius:8px;margin:1rem 0}.read-more{display:inline-block;background:#1F69FF;color:white;padding:.75rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:600;margin-top:1rem}.footer{background:#012169;color:white;padding:2rem;text-align:center;font-size:.85rem}.footer a{color:#60A5FA;text-decoration:none}</style></head><body><div class="container">';
$html .= '<div class="header"><img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker Italy"><h1>' . htmlspecialchars($subject) . '</h1></div>';

if($introMessage) {
    $html .= '<div class="intro"><p>' . nl2br(htmlspecialchars($introMessage)) . '</p></div>';
}

foreach($newsDetails as $news) {
    $isInternal = ($news['visibility'] ?? 'public') === 'internal';
    $html .= '<div class="news-item' . ($isInternal ? ' internal' : '') . '">';
    if($isInternal) $html .= '<span class="news-badge badge-internal">🔒 Solo CB</span>';
    if(!empty($news['category'])) $html .= '<span class="news-badge badge-category">' . htmlspecialchars($news['category']['name']) . '</span>';
    $html .= '<h2 class="news-title">' . htmlspecialchars($news['title']) . '</h2>';
    $html .= '<div class="news-meta">📅 ' . date('d F Y', strtotime($news['published_at'] ?? $news['created_at']));
    if(!empty($news['author'])) $html .= ' • ✍️ ' . htmlspecialchars($news['author']);
    $html .= '</div>';
    
    if(!empty($news['image_url'])) {
        $html .= '<img src="' . htmlspecialchars($news['image_url']) . '" alt="' . htmlspecialchars($news['title']) . '" class="news-image">';
    } else {
        $placeholderBg = $isInternal ? 'linear-gradient(135deg, #DBEAFE 0%, #93C5FD 100%)' : 'linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%)';
        $placeholderText = $isInternal ? '🔒 Solo CB' : '📰 CB News';
        $placeholderColor = $isInternal ? '#3B82F6' : '#9CA3AF';
        $html .= '<div class="news-image" style="background:' . $placeholderBg . ';height:200px;display:flex;align-items:center;justify-content:center;color:' . $placeholderColor . ';font-weight:600;opacity:0.7">' . $placeholderText . '</div>';
    }
    
    if(!empty($news['excerpt'])) $html .= '<p class="news-excerpt">' . nl2br(htmlspecialchars($news['excerpt'])) . '</p>';
    $html .= '<a href="https://admin.mycb.it/news_detail.php?id=' . $news['id'] . '" class="read-more">Leggi articolo completo →</a></div>';
}

$html .= '<div class="footer"><p>Newsletter inviata da <strong>' . htmlspecialchars($smtpCreds['name']) . '</strong></p><p style="margin-top:1rem"><a href="https://admin.mycb.it/news.php">Visualizza tutte le news</a> • <a href="https://coldwellbankeritaly.tech">coldwellbankeritaly.tech</a></p></div></div></body></html>';

// Verifica PHPMailer
$phpmailerPath = __DIR__ . '/vendor/phpmailer';
if(!file_exists($phpmailerPath . '/PHPMailer.php')) {
    die('❌ PHPMailer non installato!<br><br><strong>INSTALLAZIONE RAPIDA:</strong><br><br>1. Scarica: <a href="https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip" target="_blank">PHPMailer ZIP</a><br>2. Estrai la cartella "src" in <code>/vendor/phpmailer/</code><br>3. Ricarica questa pagina<br><br>Oppure via SSH:<br><code>cd ' . __DIR__ . '<br>mkdir -p vendor/phpmailer<br>cd vendor/phpmailer<br>wget https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip<br>unzip master.zip<br>mv PHPMailer-master/src/* .<br>rm -rf PHPMailer-master master.zip</code>');
}

require $phpmailerPath . '/PHPMailer.php';
require $phpmailerPath . '/SMTP.php';
require $phpmailerPath . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Test mode
if($sendTest) {
    $recipientsList = [$user['email']];
    $subject = "[TEST] " . $subject;
}

// Invio email
$successCount = 0;
$failedEmails = [];
$debugLog = [];

foreach($recipientsList as $email) {
    $mail = new PHPMailer(true);
    
    try {
        // Configurazione SMTP
        $mail->isSMTP();
        $mail->Host = $smtpCreds['server']['host'];
        $mail->SMTPAuth = $smtpCreds['server']['auth'];
        $mail->Username = $smtpCreds['email'];
        $mail->Password = $smtpCreds['password'];
        $mail->SMTPSecure = $smtpCreds['server']['encryption'];
        $mail->Port = $smtpCreds['server']['port'];
        $mail->Timeout = $smtpCreds['server']['timeout'];
        $mail->CharSet = 'UTF-8';
        
        // Debug mode (commenta in produzione)
        // $mail->SMTPDebug = 2;
        
        // Mittente
        $mail->setFrom($smtpCreds['email'], $smtpCreds['name']);
        $mail->addReplyTo($smtpCreds['email'], $smtpCreds['name']);
        
        // Destinatario
        $mail->addAddress($email);
        
        // Contenuto
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        
        $mail->send();
        $successCount++;
        $debugLog[] = "✅ $email - SUCCESS";
        
    } catch (Exception $e) {
        $failedEmails[] = $email;
        $debugLog[] = "❌ $email - ERROR: {$mail->ErrorInfo}";
    }
}

// Log per debug
error_log("Newsletter SMTP Log:\n" . implode("\n", $debugLog));

// Feedback
if($successCount > 0) {
    $message = "✅ Newsletter inviata con successo a $successCount destinatari";
    $message .= "\\n\\nMittente: {$smtpCreds['name']} ({$smtpCreds['email']})";
    
    if(!empty($failedEmails)) {
        $message .= "\\n\\n⚠️ Invio fallito per " . count($failedEmails) . " destinatari:\\n" . implode("\\n", array_slice($failedEmails, 0, 10));
        if(count($failedEmails) > 10) {
            $message .= "\\n... e altri " . (count($failedEmails) - 10);
        }
    }
    
    if($sendTest) {
        $message .= "\\n\\n📧 Email di TEST inviata a: {$user['email']}\\n\\nControlla la tua casella prima di procedere con l'invio finale.";
    }
    
    echo "<script>alert('$message'); window.location.href='news_newsletter.php';</script>";
} else {
    $errorMsg = "❌ ERRORE: Nessuna email inviata\\n\\n";
    $errorMsg .= "Configurazione SMTP:\\n";
    $errorMsg .= "Host: {$smtpCreds['server']['host']}\\n";
    $errorMsg .= "Port: {$smtpCreds['server']['port']}\\n";
    $errorMsg .= "From: {$smtpCreds['email']}\\n\\n";
    
    if(!empty($failedEmails)) {
        $errorMsg .= "Destinatari falliti:\\n" . implode("\\n", array_slice($failedEmails, 0, 5));
    }
    
    $errorMsg .= "\\n\\nControlla:\\n";
    $errorMsg .= "1. Password Office365 corretta\\n";
    $errorMsg .= "2. Account non bloccato/autenticazione 2FA\\n";
    $errorMsg .= "3. Connessione internet funzionante";
    
    echo "<script>alert('$errorMsg'); window.history.back();</script>";
}
?>

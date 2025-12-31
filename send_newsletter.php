<?php
require_once 'check_auth.php';
require_once 'helpers/news_api.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: news_newsletter.php');
    exit;
}

$newsIds = explode(',', $_POST['news_ids'] ?? '');
$recipients = $_POST['recipients'] ?? '';
$subject = $_POST['subject'] ?? '';
$introMessage = $_POST['intro_message'] ?? '';
$sendTest = isset($_POST['send_test']);

// Validazioni
if(empty($newsIds) || empty($recipients) || empty($subject)) {
    die('Dati mancanti');
}

// Parse recipients
$recipientsList = array_map('trim', explode(',', $recipients));
$recipientsList = array_filter($recipientsList, function($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
});

if(empty($recipientsList)) {
    die('Nessun destinatario valido');
}

// Carica dettagli news
$newsDetails = [];
foreach($newsIds as $id) {
    $id = trim($id);
    if(empty($id)) continue;
    
    $article = getNewsArticle($id);
    // L'API ritorna l'articolo direttamente, non in ['data']
    if($article && isset($article['id'])) {
        $newsDetails[] = $article;
    }
}

if(empty($newsDetails)) {
    die('Nessuna news valida selezionata. Debug: IDs ricevuti = ' . implode(', ', $newsIds));
}

// Crea HTML newsletter
$html = '
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($subject) . '</title>
<style>
body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f5f7fa;margin:0;padding:0}
.container{max-width:700px;margin:0 auto;background:white}
.header{background:#012169;color:white;padding:2rem;text-align:center}
.header img{height:40px;margin-bottom:1rem}
.header h1{margin:0;font-size:1.5rem}
.intro{padding:2rem;background:#EFF6FF;border-left:4px solid #3B82F6}
.intro p{margin:0;font-size:1rem;line-height:1.8}
.news-item{padding:2rem;border-bottom:2px solid #f3f4f6}
.news-item.internal{background:#EFF6FF}
.news-badge{display:inline-block;padding:.25rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;text-transform:uppercase;margin-bottom:.75rem}
.badge-internal{background:#3B82F6;color:white}
.badge-category{background:#E5E7EB;color:#6B7280;margin-left:.5rem}
.news-title{font-size:1.5rem;font-weight:700;color:#012169;margin:.5rem 0 1rem;line-height:1.3}
.news-meta{color:#6D7180;font-size:.9rem;margin-bottom:1rem}
.news-excerpt{color:#4B5563;font-size:1rem;line-height:1.8;margin-bottom:1rem}
.news-image{width:100%;max-width:100%;height:auto;border-radius:8px;margin:1rem 0}
.read-more{display:inline-block;background:#1F69FF;color:white;padding:.75rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:600;margin-top:1rem}
.footer{background:#012169;color:white;padding:2rem;text-align:center;font-size:.85rem}
.footer a{color:#60A5FA;text-decoration:none}
</style>
</head>
<body>
<div class="container">
<div class="header">
<img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker Italy">
<h1>' . htmlspecialchars($subject) . '</h1>
</div>';

if($introMessage) {
    $html .= '
<div class="intro">
<p>' . nl2br(htmlspecialchars($introMessage)) . '</p>
</div>';
}

foreach($newsDetails as $news) {
    $isInternal = ($news['visibility'] ?? 'public') === 'internal';
    
    $html .= '
<div class="news-item' . ($isInternal ? ' internal' : '') . '">';
    
    if($isInternal) {
        $html .= '<span class="news-badge badge-internal">🔒 Solo CB</span>';
    }
    
    if(!empty($news['category'])) {
        $html .= '<span class="news-badge badge-category">' . htmlspecialchars($news['category']['name']) . '</span>';
    }
    
    $html .= '
    <h2 class="news-title">' . htmlspecialchars($news['title']) . '</h2>
    <div class="news-meta">
        📅 ' . date('d F Y', strtotime($news['published_at'] ?? $news['created_at']));
    
    if(!empty($news['author'])) {
        $html .= ' • ✍️ ' . htmlspecialchars($news['author']);
    }
    
    $html .= '</div>';
    
    if(!empty($news['image_url'])) {
        $html .= '<img src="' . htmlspecialchars($news['image_url']) . '" alt="' . htmlspecialchars($news['title']) . '" class="news-image">';
    } else {
        // Placeholder immagine
        $placeholderBg = $isInternal ? 'linear-gradient(135deg, #DBEAFE 0%, #93C5FD 100%)' : 'linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%)';
        $placeholderText = $isInternal ? '🔒 Solo CB' : '📰 CB News';
        $placeholderColor = $isInternal ? '#3B82F6' : '#9CA3AF';
        
        $html .= '<div class="news-image" style="background:' . $placeholderBg . ';height:200px;display:flex;align-items:center;justify-content:center;color:' . $placeholderColor . ';font-weight:600;opacity:0.7">' . $placeholderText . '</div>';
    }
    
    if(!empty($news['excerpt'])) {
        $html .= '<p class="news-excerpt">' . nl2br(htmlspecialchars($news['excerpt'])) . '</p>';
    }
    
    $newsUrl = 'https://admin.mycb.it/news_detail.php?id=' . $news['id'];
    $html .= '
    <a href="' . $newsUrl . '" class="read-more">Leggi l\'articolo completo →</a>
</div>';
}

$html .= '
<div class="footer">
<p>Newsletter inviata da <strong>Coldwell Banker Italy</strong></p>
<p style="margin-top:1rem">
<a href="https://admin.mycb.it/news.php">Visualizza tutte le news</a> • 
<a href="https://coldwellbankeritaly.tech">coldwellbankeritaly.tech</a>
</p>
</div>
</div>
</body>
</html>';

// Headers email
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Coldwell Banker Italy <noreply@mycb.it>\r\n";

// Invia test se richiesto
if($sendTest) {
    $testRecipient = $user['email'];
    $testSubject = "[TEST] " . $subject;
    
    $sent = mail($testRecipient, $testSubject, $html, $headers);
    
    if($sent) {
        echo "<script>alert('Email di test inviata a: $testRecipient\\n\\nControlla la tua casella email prima di procedere con l\\'invio finale.'); window.location.href='news_newsletter.php';</script>";
    } else {
        echo "<script>alert('Errore invio email di test'); window.history.back();</script>";
    }
    exit;
}

// Invia newsletter a tutti i destinatari
$successCount = 0;
$failedEmails = [];

foreach($recipientsList as $email) {
    $sent = mail($email, $subject, $html, $headers);
    
    if($sent) {
        $successCount++;
    } else {
        $failedEmails[] = $email;
    }
}

// Feedback
if($successCount > 0) {
    $message = "Newsletter inviata con successo a $successCount destinatari!";
    if(!empty($failedEmails)) {
        $message .= "\\n\\nInvio fallito per: " . implode(', ', $failedEmails);
    }
    echo "<script>alert('$message'); window.location.href='news_newsletter.php';</script>";
} else {
    echo "<script>alert('Errore: nessuna email inviata'); window.history.back();</script>";
}
?>

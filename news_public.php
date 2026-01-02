<?php
/**
 * News Pubblica - Visualizzazione news senza autenticazione
 * Link da newsletter per lettura articoli completi
 */

require_once 'helpers/news_api.php';

// Ottieni ID news
$newsId = (int)($_GET['id'] ?? 0);
$isShared = isset($_GET['share']); // Se arriva da condivisione esterna

if(!$newsId) {
    die('News non trovata');
}

// Carica news da API
$news = getNewsArticle($newsId);

if(!$news || !isset($news['id'])) {
    die('News non trovata');
}

// Formatta data (usa published_at o created_at come fallback)
$dataArticolo = $news['published_at'] ?: $news['created_at'];
$dataItaliana = date('d/m/Y', strtotime($dataArticolo));

$pageTitle = htmlspecialchars($news['title']) . " - Coldwell Banker Italy";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <style>
        :root {
            --cb-midnight: #0A1730;
            --cb-blue: #012169;
            --cb-bright-blue: #0051A5;
            --cb-gray: #6B7280;
            --bg: #F9FAFB;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--cb-midnight);
            line-height: 1.6;
        }
        
        .header {
            background: var(--cb-blue);
            color: white;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .header-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .header-link:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .news-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .news-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--bg);
        }
        
        .news-date {
            color: var(--cb-gray);
            font-size: 0.9rem;
        }
        
        .news-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--cb-bright-blue);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-solo-cb {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .news-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--cb-midnight);
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }
        
        .news-image {
            width: 100%;
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .news-content {
            font-size: 1.05rem;
            line-height: 1.8;
            color: #374151;
        }
        
        .news-content h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            color: var(--cb-blue);
        }
        
        .news-content h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 1.5rem 0 0.75rem;
            color: var(--cb-midnight);
        }
        
        .news-content p {
            margin-bottom: 1rem;
        }
        
        .news-content ul,
        .news-content ol {
            margin: 1rem 0 1rem 1.5rem;
        }
        
        .news-content li {
            margin-bottom: 0.5rem;
        }
        
        .news-content blockquote {
            border-left: 4px solid var(--cb-bright-blue);
            padding-left: 1rem;
            margin: 1.5rem 0;
            font-style: italic;
            color: var(--cb-gray);
        }
        
        .news-content a {
            color: var(--cb-bright-blue);
            text-decoration: underline;
        }
        
        .news-content a:hover {
            color: var(--cb-blue);
        }
        
        .news-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        
        .footer {
            margin-top: 3rem;
            padding: 2rem 1.5rem;
            text-align: center;
            color: var(--cb-gray);
            font-size: 0.9rem;
        }
        
        .footer-logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--cb-blue);
            margin-bottom: 0.5rem;
        }
        
        .share-section {
            background: var(--bg);
            padding: 2rem;
            border-radius: 12px;
            margin-top: 3rem;
            text-align: center;
        }
        
        .share-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--cb-midnight);
        }
        
        .share-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .share-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }
        
        .share-email {
            background: #6B7280;
        }
        
        .share-linkedin {
            background: #0A66C2;
        }
        
        .share-whatsapp {
            background: #25D366;
        }
        
        .share-telegram {
            background: #0088cc;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .header-logo {
                font-size: 0.9rem;
            }
            
            .header-link {
                width: 100%;
                text-align: center;
            }
            
            .container {
                padding: 1.5rem 1rem;
            }
            
            .news-card {
                padding: 1.5rem;
            }
            
            .news-title {
                font-size: 1.5rem;
            }
            
            .news-content {
                font-size: 1rem;
            }
            
            .share-section {
                padding: 1.5rem;
            }
            
            .share-buttons {
                flex-direction: column;
            }
            
            .share-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="header-logo">
                <img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker Italy" style="height:40px">
            </div>
            <?php if(!$isShared): ?>
            <a href="news_index.php" class="header-link">← Tutte le News</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <div class="news-card">
            <div class="news-meta">
                <span class="news-date">📅 <?= $dataItaliana ?></span>
                
                <?php if(isset($news['category']['name'])): ?>
                <span class="news-category"><?= htmlspecialchars($news['category']['name']) ?></span>
                <?php endif; ?>
                
                <?php if(isset($news['visibility']) && $news['visibility'] === 'internal'): ?>
                <span class="badge-solo-cb">🔒 Solo CB</span>
                <?php endif; ?>
            </div>
            
            <h1 class="news-title"><?= htmlspecialchars($news['title']) ?></h1>
            
            <?php 
            $imageUrl = getFullImageUrl($news['image_url'] ?? null);
            if($imageUrl): 
            ?>
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($news['title']) ?>" class="news-image">
            <?php endif; ?>
            
            <div class="news-content">
                <?= $news['content'] ?>
            </div>
            
            <div class="share-section">
                <h3 class="share-title">Condividi questa news</h3>
                <?php 
                // URL corrente per condivisione
                $shareUrl = 'https://admin.mycb.it/news_public.php?id=' . $news['id'] . '&share=1';
                ?>
                <div class="share-buttons">
                    <a href="mailto:?subject=<?= urlencode($news['title']) ?>&body=<?= urlencode($news['title'] . ' - ' . $shareUrl) ?>" class="share-btn share-email">
                        📧 Email
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>" target="_blank" class="share-btn share-linkedin">
                        🔗 LinkedIn
                    </a>
                    <a href="https://wa.me/?text=<?= urlencode($news['title'] . ' - ' . $shareUrl) ?>" target="_blank" class="share-btn share-whatsapp">
                        💬 WhatsApp
                    </a>
                    <a href="https://t.me/share/url?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($news['title']) ?>" target="_blank" class="share-btn share-telegram">
                        ✈️ Telegram
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="footer-logo">COLDWELL BANKER ITALY</div>
        <p>© <?= date('Y') ?> Coldwell Banker Italy. Tutti i diritti riservati.</p>
    </div>
</body>
</html>

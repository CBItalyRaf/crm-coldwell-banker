<?php
/**
 * News Index Pubblica - Tutte le news con ricerca e filtri
 */

require_once 'helpers/news_api.php';

// Parametri ricerca
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 1000; // Tutte le news

$newsArticles = getNewsArticles($limit, $search, $category, null, 'published');
$articles = $newsArticles['data'] ?? [];
$total = $newsArticles['total'] ?? 0;

// Carica categorie
$categoriesData = getNewsCategories();
$categories = $categoriesData ?? [];

$pageTitle = "News Coldwell Banker Italy";
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--cb-midnight);
            line-height: 1.6;
        }
        
        .header {
            background: var(--cb-blue);
            color: white;
            padding: 2rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--cb-bright-blue);
        }
        
        .search-btn {
            padding: 0.75rem 1.5rem;
            background: var(--cb-bright-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .search-btn:hover {
            background: var(--cb-blue);
        }
        
        .categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 0.5rem 1rem;
            background: transparent;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--cb-gray);
        }
        
        .category-btn:hover {
            border-color: var(--cb-bright-blue);
            color: var(--cb-bright-blue);
        }
        
        .category-btn.active {
            background: var(--cb-bright-blue);
            color: white;
            border-color: var(--cb-bright-blue);
        }
        
        .news-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .news-item {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid #F3F4F6;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .news-item:hover {
            background: var(--bg);
        }
        
        .news-item.internal {
            background: #EFF6FF;
        }
        
        .news-item:last-child {
            border-bottom: none;
        }
        
        .news-thumbnail {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
            background: var(--bg);
        }
        
        .news-content {
            flex: 1;
            min-width: 0;
        }
        
        .news-item-header {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        
        .news-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-internal {
            background: #3B82F6;
            color: white;
        }
        
        .badge-category {
            background: #E5E7EB;
            color: #6B7280;
        }
        
        .news-item-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--cb-midnight);
            margin-bottom: 0.5rem;
        }
        
        .news-item-excerpt {
            font-size: 0.9rem;
            color: var(--cb-gray);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .news-item-meta {
            font-size: 0.85rem;
            color: var(--cb-gray);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--cb-gray);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .footer {
            margin-top: 3rem;
            padding: 2rem 1.5rem;
            text-align: center;
            color: var(--cb-gray);
            font-size: 0.9rem;
            border-top: 1px solid #E5E7EB;
        }
        
        .footer-logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--cb-blue);
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 1.5rem 1rem;
            }
            
            .news-item {
                padding: 1rem;
            }
            
            .news-thumbnail {
                width: 80px;
                height: 80px;
            }
            
            .search-bar {
                flex-direction: column;
            }
            
            .search-input {
                width: 100%;
            }
            
            .search-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="header-logo">
                <img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker Italy" style="height:50px;margin-bottom:0.5rem">
            </div>
            <h1>News e Aggiornamenti</h1>
        </div>
    </div>
    
    <div class="container">
        <div class="filters">
            <form method="GET" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="🔍 Cerca news..." value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <button type="submit" class="search-btn">Cerca</button>
            </form>
            
            <div class="categories">
                <a href="?" class="category-btn <?= !$category ? 'active' : '' ?>">Tutte</a>
                <?php foreach($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat['slug']) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="category-btn <?= $category === $cat['slug'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if(empty($articles)): ?>
        <div class="empty-state">
            <div class="empty-icon">📰</div>
            <h3>Nessuna news trovata</h3>
            <p>Prova a modificare i filtri di ricerca</p>
        </div>
        <?php else: ?>
        <div class="news-list">
            <?php foreach($articles as $article): ?>
            <?php $isInternal = ($article['visibility'] ?? 'public') === 'internal'; ?>
            <div class="news-item <?= $isInternal ? 'internal' : '' ?>" onclick="window.location.href='news_public.php?id=<?= $article['id'] ?>'">
                <?php 
                $imageUrl = getFullImageUrl($article['image_url'] ?? null);
                if($imageUrl): 
                ?>
                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="news-thumbnail">
                <?php endif; ?>
                <div class="news-content">
                    <div class="news-item-header">
                        <?php if($isInternal): ?>
                        <span class="news-badge badge-internal">🔒 Solo CB</span>
                        <?php endif; ?>
                        <?php if(!empty($article['category'])): ?>
                        <span class="news-badge badge-category"><?= htmlspecialchars($article['category']['name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="news-item-title"><?= htmlspecialchars($article['title']) ?></h3>
                    <?php if(!empty($article['summary']) || !empty($article['excerpt'])): ?>
                    <p class="news-item-excerpt"><?= htmlspecialchars(substr($article['summary'] ?? $article['excerpt'], 0, 200)) ?>...</p>
                    <?php endif; ?>
                    <div class="news-item-meta">
                        <span>📅 <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?></span>
                        <?php if(!empty($article['author'])): ?>
                        <span>✍️ <?= htmlspecialchars($article['author']) ?></span>
                        <?php endif; ?>
                        <span style="color:var(--cb-bright-blue);font-weight:600">Leggi tutto →</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <div class="footer-logo">COLDWELL BANKER ITALY</div>
        <p>© <?= date('Y') ?> Coldwell Banker Italy. Tutti i diritti riservati.</p>
    </div>
</body>
</html>

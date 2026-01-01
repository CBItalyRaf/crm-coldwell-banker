<?php
/**
 * News Index Pubblica - Tutte le news con ricerca e filtri
 */

require_once 'helpers/news_api.php';

// Parametri ricerca
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 100; // Mostra tutte le news

// Carica news da API
$params = ['limit' => $limit];
if($search) $params['search'] = $search;
if($category) $params['category'] = $category;

$newsArticles = getNewsArticles($limit, $search, $category, null);
$articles = $newsArticles ?? [];

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
        
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .news-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        
        .news-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        
        .news-card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg);
        }
        
        .news-card-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .news-card-meta {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .news-card-date {
            color: var(--cb-gray);
            font-size: 0.85rem;
        }
        
        .news-card-category {
            padding: 0.25rem 0.75rem;
            background: var(--cb-bright-blue);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-solo-cb {
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .news-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--cb-midnight);
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }
        
        .news-card-summary {
            color: var(--cb-gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-card-read {
            color: var(--cb-bright-blue);
            font-weight: 600;
            font-size: 0.9rem;
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
            
            .news-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
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
        <div class="news-grid">
            <?php foreach($articles as $article): ?>
            <a href="news_public.php?id=<?= $article['id'] ?>" class="news-card">
                <?php if(!empty($article['image_url'])): ?>
                <img src="<?= htmlspecialchars($article['image_url']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="news-card-image">
                <?php endif; ?>
                
                <div class="news-card-content">
                    <div class="news-card-meta">
                        <span class="news-card-date">
                            📅 <?= date('d/m/Y', strtotime($article['published_at'] ?: $article['created_at'])) ?>
                        </span>
                        
                        <?php if(isset($article['category']['name'])): ?>
                        <span class="news-card-category"><?= htmlspecialchars($article['category']['name']) ?></span>
                        <?php endif; ?>
                        
                        <?php if(isset($article['visibility']) && $article['visibility'] === 'internal'): ?>
                        <span class="badge-solo-cb">🔒 Solo CB</span>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="news-card-title"><?= htmlspecialchars($article['title']) ?></h2>
                    
                    <?php if(!empty($article['summary'])): ?>
                    <p class="news-card-summary"><?= htmlspecialchars($article['summary']) ?></p>
                    <?php endif; ?>
                    
                    <span class="news-card-read">Leggi tutto →</span>
                </div>
            </a>
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

<?php
/**
 * News Index - Tutte le news con ricerca e filtri
 * Accessibile pubblicamente con header stile admin
 */

require_once 'helpers/news_api.php';

// Parametri ricerca
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; // Articoli per pagina

$newsArticles = getNewsArticles($limit, $search, $category, null, 'published', $page);

$articles = $newsArticles['data'] ?? [];
$total = $newsArticles['meta']['total'] ?? 0;
$totalPages = $newsArticles['meta']['last_page'] ?? 1;

// Carica categorie
$categoriesData = getNewsCategories();
$categories = $categoriesData ?? [];

$pageTitle = "News di Rete - Coldwell Banker Italy";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --cb-blue: #003F87;
            --cb-bright-blue: #0051A5;
            --cb-midnight: #001F3F;
            --cb-gray: #6B7280;
            --bg: #F9FAFB;
            --white: #FFFFFF;
            --border: #E5E7EB;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--cb-midnight);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* HEADER STILE ADMIN */
        .public-header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .header-logo {
            height: 30px;
        }
        
        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--cb-midnight);
        }
        
        /* CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            flex: 1;
        }
        /* CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            flex: 1;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        .filters {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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
    
    /* PAGINAZIONE */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }
    
    .pagination-btn {
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--cb-midnight);
        text-decoration: none;
        font-size: 0.9rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .pagination-btn:hover:not(.disabled) {
        border-color: var(--cb-bright-blue);
        color: var(--cb-bright-blue);
    }
    
    .pagination-btn.active {
        background: var(--cb-bright-blue);
        color: white;
        border-color: var(--cb-bright-blue);
    }
    
    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .pagination-info {
        color: var(--cb-gray);
        font-size: 0.85rem;
        padding: 0 1rem;
    }
    
        }
    </style>
</head>
<body>
    <!-- HEADER PUBBLICO STILE ADMIN -->
    <header class="public-header">
        <div class="header-container">
            <div class="header-left">
                <img src="https://mycb.it/assets/img/logo-blue.png" 
                     alt="Coldwell Banker Italy" 
                     class="header-logo">
            </div>
            <h1 class="header-title">📰 News</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
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

<!-- CONTATORE NEWS -->
<div style="background:white;padding:1rem 1.5rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);color:var(--cb-gray);font-size:.95rem">
    <?php if($search || $category): ?>
        Trovate <strong style="color:var(--cb-midnight)"><?= $total ?></strong> news
        <?php if($search): ?>
            per "<strong><?= htmlspecialchars($search) ?></strong>"
        <?php endif; ?>
        <?php if($category): ?>
            <?php 
            $catName = '';
            foreach($categories as $cat) {
                if($cat['slug'] === $category) {
                    $catName = $cat['name'];
                    break;
                }
            }
            ?>
            in <strong><?= htmlspecialchars($catName) ?></strong>
        <?php endif; ?>
    <?php else: ?>
        <strong style="color:var(--cb-midnight)"><?= $total ?></strong> news totali disponibili
    <?php endif; ?>
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

<?php if($totalPages > 1): ?>
<!-- PAGINAZIONE -->
<div class="pagination">
    <?php 
    // URL base con parametri
    $baseUrl = '?';
    if($search) $baseUrl .= 'search=' . urlencode($search) . '&';
    if($category) $baseUrl .= 'category=' . urlencode($category) . '&';
    
    // Bottone Precedente
    if($page > 1): ?>
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-btn">← Precedente</a>
    <?php else: ?>
        <span class="pagination-btn disabled">← Precedente</span>
    <?php endif; ?>
    
    <?php
    // Mostra max 7 numeri di pagina
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    
    // Prima pagina
    if($start > 1): ?>
        <a href="<?= $baseUrl ?>page=1" class="pagination-btn">1</a>
        <?php if($start > 2): ?>
            <span class="pagination-info">...</span>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Pagine centrali -->
    <?php for($i = $start; $i <= $end; $i++): ?>
        <a href="<?= $baseUrl ?>page=<?= $i ?>" 
           class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    
    <!-- Ultima pagina -->
    <?php if($end < $totalPages): ?>
        <?php if($end < $totalPages - 1): ?>
            <span class="pagination-info">...</span>
        <?php endif; ?>
        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="pagination-btn"><?= $totalPages ?></a>
    <?php endif; ?>
    
    <!-- Bottone Successivo -->
    <?php if($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-btn">Successivo →</a>
    <?php else: ?>
        <span class="pagination-btn disabled">Successivo →</span>
    <?php endif; ?>
</div>

<div class="pagination-info" style="text-align: center; margin-top: 1rem;">
    Pagina <?= $page ?> di <?= $totalPages ?> • <?= $total ?> news totali
</div>
<?php endif; ?>

    </div> <!-- chiude container -->
    
    <!-- FOOTER SEMPLICE -->
    <footer style="background: var(--cb-midnight); color: white; padding: 2rem 1.5rem; margin-top: auto; text-align: center;">
        <div style="max-width: 1400px; margin: 0 auto;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 0.5rem;">
                <img src="https://mycb.it/assets/img/logo-white.png" alt="Coldwell Banker Italy" style="height: 24px;">
                <span style="font-size: 0.9rem;">Powered by <strong>Coldwell Banker Italy</strong></span>
            </div>
            <p style="font-size: 0.85rem; opacity: 0.7; margin: 0;">&copy; <?= date('Y') ?> Coldwell Banker Real Estate LLC</p>
        </div>
    </footer>
</body>
</html>

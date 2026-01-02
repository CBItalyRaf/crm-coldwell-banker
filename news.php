<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';

$pageTitle = "News CB Italia - CRM Coldwell Banker";
$pdo = getDB();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$visibility = $_GET['visibility'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; // Articoli per pagina

// Carica news con error handling
try {
    $newsData = getNewsArticles($limit, $search ?: null, $category ?: null, $visibility ?: null, null, $page);
    $categories = getNewsCategories();
} catch (Exception $e) {
    error_log("Errore caricamento news: " . $e->getMessage());
    $newsData = null;
    $categories = null;
}

$articles = $newsData['data'] ?? [];
$total = $newsData['meta']['total'] ?? 0;
$totalPages = $newsData['meta']['last_page'] ?? 1;

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600;margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem}
.page-subtitle{color:var(--cb-gray);font-size:.95rem}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto auto auto;gap:1rem;align-items:center}
.search-box{position:relative}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.category-select,.visibility-select{padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;min-width:180px;cursor:pointer}
.btn-search{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;transition:background .2s}
.btn-search:hover{background:var(--cb-blue)}
.news-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem}
.news-card{background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);transition:all .2s;cursor:pointer;display:flex;flex-direction:column}
.news-card.internal{background:#EFF6FF;border:2px solid #3B82F6}
.news-card:hover{transform:translateY(-4px);box-shadow:0 4px 16px rgba(0,0,0,.15)}
.news-image{width:100%;height:200px;object-fit:cover;background:var(--bg)}
.news-content{padding:1.5rem;flex:1;display:flex;flex-direction:column}
.news-badges{display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap}
.news-badge{display:inline-block;padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.badge-internal{background:#3B82F6;color:white}
.badge-category{background:#E5E7EB;color:#6B7280}
.news-title{font-size:1.1rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.75rem;line-height:1.4}
.news-excerpt{color:var(--cb-gray);font-size:.9rem;line-height:1.6;margin-bottom:1rem;flex:1}
.news-meta{display:flex;justify-content:space-between;align-items:center;font-size:.85rem;color:var(--cb-gray);padding-top:1rem;border-top:1px solid #F3F4F6}
.news-date{display:flex;align-items:center;gap:.5rem}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
.empty-icon{font-size:4rem;margin-bottom:1rem;opacity:.3}
.results-count{background:white;padding:1rem 1.5rem;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:1.5rem;color:var(--cb-gray);font-size:.95rem}
@media(max-width:768px){
.filters-grid{grid-template-columns:1fr;gap:.75rem}
.news-grid{grid-template-columns:1fr}
}
</style>

<div class="page-header">
<div>
<h1 class="page-title">News CB Italia</h1>
<p class="page-subtitle">Ultime notizie e comunicazioni dal network Coldwell Banker</p>
</div>
<a href="news_newsletter.php" class="btn-primary" style="background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem">
📧 Crea Newsletter
</a>
</div>

<div class="filters-bar">
<form method="GET" class="filters-grid">
<div class="search-box">
<input type="text" name="search" placeholder="🔍 Cerca nelle news..." value="<?= htmlspecialchars($search) ?>">
</div>
<select name="visibility" class="visibility-select">
<option value="">Tutte le news</option>
<option value="internal" <?= $visibility === 'internal' ? 'selected' : '' ?>>🔒 Solo CB (interne)</option>
<option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>🌐 Pubbliche</option>
</select>
<select name="category" class="category-select">
<option value="">Tutte le categorie</option>
<?php if($categories && isset($categories['data'])): ?>
<?php foreach($categories['data'] as $cat): ?>
<option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?>
</option>
<?php endforeach; ?>
<?php endif; ?>
</select>
<button type="submit" class="btn-search">Cerca</button>
</form>
</div>

<?php if($search || $category || $visibility): ?>
<div class="results-count">
Trovate <strong style="color:var(--cb-midnight)"><?= count($articles) ?></strong> news
<?php if($search): ?>
per "<strong><?= htmlspecialchars($search) ?></strong>"
<?php endif; ?>
<?php if($visibility === 'internal'): ?>
<strong>(Solo CB - interne)</strong>
<?php elseif($visibility === 'public'): ?>
<strong>(Pubbliche)</strong>
<?php endif; ?>
<?php if($total > count($articles)): ?>
(<?= $total ?> totali)
<?php endif; ?>
</div>
<?php else: ?>
<div class="results-count">
<strong style="color:var(--cb-midnight)"><?= $total ?></strong> news totali disponibili
</div>
<?php endif; ?>

<?php if(empty($articles)): ?>
<div class="empty-state">
<div class="empty-icon">📋</div>
<h3>Nessuna news trovata</h3>
<p>Prova a modificare i filtri di ricerca</p>
</div>
<?php else: ?>
<div class="news-grid">
<?php foreach($articles as $article): ?>
<?php $isInternal = ($article['visibility'] ?? 'public') === 'internal'; ?>
<div class="news-card <?= $isInternal ? 'internal' : '' ?>" onclick="window.location.href='news_detail.php?id=<?= $article['id'] ?>'">
<?php 
$imageUrl = getFullImageUrl($article['image_url'] ?? null);
if($imageUrl): 
?>
<img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="news-image">
<?php else: ?>
<div class="news-image news-image-placeholder" style="background:linear-gradient(135deg,<?= $isInternal ? '#DBEAFE 0%, #93C5FD 100%' : '#F3F4F6 0%, #E5E7EB 100%' ?>);display:flex;align-items:center;justify-content:center;position:relative">
<div style="text-align:center;color:<?= $isInternal ? '#3B82F6' : '#9CA3AF' ?>;font-size:1rem;font-weight:600;opacity:.7">
<?= $isInternal ? '🔒 Solo CB' : '📰 CB News' ?>
</div>
</div>
<?php endif; ?>
<div class="news-content">
<div class="news-badges">
<?php if($isInternal): ?>
<span class="news-badge badge-internal">🔒 Solo CB</span>
<?php endif; ?>
<?php if(!empty($article['category'])): ?>
<span class="news-badge badge-category"><?= htmlspecialchars($article['category']['name']) ?></span>
<?php endif; ?>
</div>
<h3 class="news-title"><?= htmlspecialchars($article['title']) ?></h3>
<?php if(!empty($article['summary']) || !empty($article['excerpt'])): ?>
<p class="news-excerpt"><?= htmlspecialchars(substr($article['summary'] ?? $article['excerpt'], 0, 150)) ?>...</p>
<?php endif; ?>
<div class="news-meta">
<span class="news-date">
📅 <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?>
</span>
<?php if(!empty($article['author'])): ?>
<span>✍️ <?= htmlspecialchars($article['author']) ?></span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($totalPages > 1): ?>
<!-- PAGINAZIONE -->
<div style="display:flex;justify-content:center;align-items:center;gap:.5rem;margin-top:2rem;flex-wrap:wrap">
    <?php 
    // URL base con parametri
    $baseUrl = '?';
    if($search) $baseUrl .= 'search=' . urlencode($search) . '&';
    if($category) $baseUrl .= 'category=' . urlencode($category) . '&';
    if($visibility) $baseUrl .= 'visibility=' . urlencode($visibility) . '&';
    
    // Bottone Precedente
    if($page > 1): ?>
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);text-decoration:none;font-size:.9rem">← Precedente</a>
    <?php else: ?>
        <span style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);font-size:.9rem;opacity:.5">← Precedente</span>
    <?php endif; ?>
    
    <?php
    // Mostra max 7 numeri di pagina
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    
    // Prima pagina
    if($start > 1): ?>
        <a href="<?= $baseUrl ?>page=1" style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);text-decoration:none;font-size:.9rem">1</a>
        <?php if($start > 2): ?>
            <span style="color:var(--cb-gray);font-size:.85rem;padding:0 1rem">...</span>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Pagine centrali -->
    <?php for($i = $start; $i <= $end; $i++): ?>
        <a href="<?= $baseUrl ?>page=<?= $i ?>" 
           style="padding:.5rem 1rem;background:<?= $i === $page ? 'var(--cb-bright-blue)' : 'white' ?>;border:1px solid <?= $i === $page ? 'var(--cb-bright-blue)' : '#E5E7EB' ?>;border-radius:8px;color:<?= $i === $page ? 'white' : 'var(--cb-midnight)' ?>;text-decoration:none;font-size:.9rem">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    
    <!-- Ultima pagina -->
    <?php if($end < $totalPages): ?>
        <?php if($end < $totalPages - 1): ?>
            <span style="color:var(--cb-gray);font-size:.85rem;padding:0 1rem">...</span>
        <?php endif; ?>
        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);text-decoration:none;font-size:.9rem"><?= $totalPages ?></a>
    <?php endif; ?>
    
    <!-- Bottone Successivo -->
    <?php if($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);text-decoration:none;font-size:.9rem">Successivo →</a>
    <?php else: ?>
        <span style="padding:.5rem 1rem;background:white;border:1px solid #E5E7EB;border-radius:8px;color:var(--cb-midnight);font-size:.9rem;opacity:.5">Successivo →</span>
    <?php endif; ?>
</div>

<div style="text-align:center;margin-top:1rem;color:var(--cb-gray);font-size:.85rem">
    Pagina <?= $page ?> di <?= $totalPages ?> • <?= $total ?> news totali
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

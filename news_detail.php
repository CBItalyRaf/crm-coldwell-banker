<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';

$pdo = getDB();
$id = $_GET['id'] ?? '';

if(!$id) {
    die("Errore: ID news mancante. <a href='news.php'>Torna alle news</a>");
}

$articleData = getNewsArticle($id);

if(!$articleData) {
    die("Errore: API non risponde. ID: $id <a href='news.php'>Torna alle news</a>");
}

$article = $articleData['data'] ?? null;

if(!$article) {
    die("Errore: News non trovata. ID: $id. Response: " . print_r($articleData, true) . " <a href='news.php'>Torna alle news</a>");
}

$isInternal = ($article['visibility'] ?? 'public') === 'internal';
$pageTitle = htmlspecialchars($article['title']) . " - News CB Italia";

require_once 'header.php';
?>

<style>
.detail-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center}
.back-btn{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.5rem 1rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:.9rem;transition:all .2s}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.article-container{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);overflow:hidden;max-width:900px;margin:0 auto}
.article-container.internal{background:#EFF6FF;border:3px solid #3B82F6}
.article-header{padding:2rem}
.article-badges{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}
.article-badge{display:inline-block;padding:.25rem .75rem;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.badge-internal{background:#3B82F6;color:white}
.badge-category{background:#E5E7EB;color:#6B7280}
.article-title{font-size:2rem;font-weight:700;color:var(--cb-midnight);line-height:1.3;margin-bottom:1rem}
.article-meta{display:flex;gap:2rem;color:var(--cb-gray);font-size:.9rem;padding-bottom:1.5rem;border-bottom:2px solid #F3F4F6;flex-wrap:wrap}
.meta-item{display:flex;align-items:center;gap:.5rem}
.article-image{width:100%;max-height:500px;object-fit:cover}
.article-content{padding:2rem;font-size:1.05rem;line-height:1.8;color:#374151}
.article-content p{margin-bottom:1.5rem}
.article-content h2{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:2rem 0 1rem}
.article-content h3{font-size:1.25rem;font-weight:600;color:var(--cb-midnight);margin:1.5rem 0 1rem}
.article-content ul,.article-content ol{margin:1rem 0 1.5rem 2rem}
.article-content li{margin-bottom:.5rem}
.article-content blockquote{border-left:4px solid var(--cb-bright-blue);padding-left:1.5rem;margin:1.5rem 0;font-style:italic;color:var(--cb-gray)}
.article-content img{max-width:100%;height:auto;border-radius:8px;margin:1.5rem 0}
.article-footer{padding:2rem;background:var(--bg);border-top:2px solid #E5E7EB}
.article-footer.internal{background:#DBEAFE}
.share-section{text-align:center}
.share-title{font-size:1.1rem;font-weight:600;margin-bottom:1rem}
.share-buttons{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}
.share-btn{padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;color:white;font-weight:600;transition:opacity .2s}
.share-btn:hover{opacity:.8}
.share-email{background:#6B7280}
.share-linkedin{background:#0A66C2}
.share-whatsapp{background:#25D366}
@media(max-width:768px){
.article-title{font-size:1.5rem}
.article-content{padding:1.5rem;font-size:1rem}
.article-meta{gap:1rem}
}
</style>

<div class="detail-header">
<a href="news.php" class="back-btn">← Tutte le news</a>
</div>

<div class="article-container <?= $isInternal ? 'internal' : '' ?>">
<div class="article-header">
<div class="article-badges">
<?php if($isInternal): ?>
<span class="article-badge badge-internal">🔒 Solo CB - Comunicazione Interna</span>
<?php endif; ?>
<?php if(!empty($article['category'])): ?>
<span class="article-badge badge-category"><?= htmlspecialchars($article['category']['name']) ?></span>
<?php endif; ?>
</div>

<h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>

<div class="article-meta">
<span class="meta-item">
📅 <?= date('d F Y', strtotime($article['published_at'] ?? $article['created_at'])) ?>
</span>
<?php if(!empty($article['author'])): ?>
<span class="meta-item">
✍️ <?= htmlspecialchars($article['author']) ?>
</span>
<?php endif; ?>
<?php if(!empty($article['read_time'])): ?>
<span class="meta-item">
⏱️ <?= htmlspecialchars($article['read_time']) ?> min
</span>
<?php endif; ?>
</div>
</div>

<?php if(!empty($article['image_url'])): ?>
<img src="<?= htmlspecialchars($article['image_url']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="article-image">
<?php endif; ?>

<div class="article-content">
<?php if(!empty($article['content'])): ?>
<?= $article['content'] ?>
<?php elseif(!empty($article['body'])): ?>
<?= $article['body'] ?>
<?php else: ?>
<p><?= htmlspecialchars($article['excerpt'] ?? 'Contenuto non disponibile.') ?></p>
<?php endif; ?>
</div>

<div class="article-footer <?= $isInternal ? 'internal' : '' ?>">
<div class="share-section">
<div class="share-title">Condividi questa news</div>
<div class="share-buttons">
<a href="mailto:?subject=<?= urlencode($article['title']) ?>&body=<?= urlencode($article['title'] . ' - ' . $_SERVER['REQUEST_URI']) ?>" class="share-btn share-email">
📧 Email
</a>
<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn share-linkedin">
🔗 LinkedIn
</a>
<a href="https://wa.me/?text=<?= urlencode($article['title'] . ' - ' . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn share-whatsapp">
💬 WhatsApp
</a>
</div>
</div>
</div>
</div>

<?php require_once 'footer.php'; ?>

<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';

$pdo = getDB();
$id = $_GET['id'] ?? '';

if(!$id) {
    header('Location: news.php');
    exit;
}

$article = getNewsArticle($id);

if(!$article || !isset($article['title'])) {
    header('Location: news.php');
    exit;
}

$isInternal = ($article['visibility'] ?? 'public') === 'internal';
$pageTitle = htmlspecialchars($article['title']) . " - News CB Italia";

require_once 'header.php';
?>

<style>
.back-section{margin-bottom:2rem}
.back-btn{background:white;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.back-btn:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue);transform:translateX(-4px)}
.article-container{background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;max-width:900px;margin:0 auto 2rem}
.article-container.internal{background:linear-gradient(to bottom,#EFF6FF 0%,white 200px);border:3px solid #3B82F6}
.article-header{padding:3rem 3rem 2rem}
.article-badges{display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap}
.article-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:20px;font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.badge-internal{background:#3B82F6;color:white;box-shadow:0 2px 8px rgba(59,130,246,.3)}
.badge-category{background:#F3F4F6;color:#6B7280}
.article-title{font-size:2.5rem;font-weight:700;color:var(--cb-midnight);line-height:1.2;margin-bottom:1.5rem;letter-spacing:-.02em}
.article-meta{display:flex;gap:2rem;padding:1.5rem 0;border-top:2px solid #F3F4F6;border-bottom:2px solid #F3F4F6;flex-wrap:wrap;font-size:.95rem;color:var(--cb-gray)}
.meta-item{display:flex;align-items:center;gap:.5rem;font-weight:500}
.article-image{width:100%;max-height:500px;object-fit:cover}
.article-body{padding:3rem;font-size:1.1rem;line-height:1.9;color:#374151}
.article-body p{margin-bottom:1.75rem}
.article-body h2{font-size:1.75rem;font-weight:700;color:var(--cb-midnight);margin:2.5rem 0 1.25rem;letter-spacing:-.01em}
.article-body h3{font-size:1.4rem;font-weight:600;color:var(--cb-midnight);margin:2rem 0 1rem}
.article-body h4{font-size:1.2rem;font-weight:600;color:var(--cb-midnight);margin:1.5rem 0 .75rem}
.article-body ul,.article-body ol{margin:1.5rem 0 1.75rem 2rem;line-height:1.8}
.article-body li{margin-bottom:.75rem}
.article-body blockquote{border-left:5px solid var(--cb-bright-blue);padding:1.5rem 2rem;margin:2rem 0;font-style:italic;background:var(--bg);border-radius:0 8px 8px 0;color:#4B5563}
.article-body img{max-width:100%;height:auto;border-radius:12px;margin:2rem 0;box-shadow:0 4px 12px rgba(0,0,0,.1)}
.article-body a{color:var(--cb-bright-blue);text-decoration:none;border-bottom:2px solid transparent;transition:border-color .2s}
.article-body a:hover{border-bottom-color:var(--cb-bright-blue)}
.article-body strong{font-weight:700;color:var(--cb-midnight)}
.article-body code{background:#F3F4F6;padding:.25rem .5rem;border-radius:4px;font-size:.95em;font-family:monospace}
.article-body pre{background:#1F2937;color:#F9FAFB;padding:1.5rem;border-radius:8px;overflow-x:auto;margin:2rem 0}
.article-body pre code{background:transparent;padding:0;color:inherit}
.article-footer{padding:2.5rem 3rem;background:var(--bg);border-top:2px solid #E5E7EB}
.article-footer.internal{background:#DBEAFE}
.share-section{text-align:center}
.share-title{font-size:1.1rem;font-weight:600;margin-bottom:1.25rem;color:var(--cb-midnight)}
.share-buttons{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}
.share-btn{padding:.875rem 1.75rem;border-radius:10px;text-decoration:none;color:white;font-weight:600;transition:all .2s;display:inline-flex;align-items:center;gap:.75rem;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.share-btn:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.25)}
.share-email{background:#6B7280}
.share-linkedin{background:#0A66C2}
.share-whatsapp{background:#25D366}
@media(max-width:768px){
.article-title{font-size:1.75rem}
.article-header,.article-body,.article-footer{padding:2rem 1.5rem}
.article-body{font-size:1rem}
.article-meta{gap:1rem;font-size:.875rem}
}
</style>

<div class="back-section">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
<a href="news.php" class="back-btn">← Torna alle news</a>
<a href="news_edit.php?id=<?= $article['id'] ?>" class="back-btn" style="background:var(--cb-bright-blue);color:white;border-color:var(--cb-bright-blue)">✏️ Modifica News</a>
</div>
</div>

<article class="article-container <?= $isInternal ? 'internal' : '' ?>">
<header class="article-header">
<?php if($isInternal || !empty($article['category'])): ?>
<div class="article-badges">
<?php if($isInternal): ?>
<span class="article-badge badge-internal">🔒 Solo CB - Comunicazione Interna</span>
<?php endif; ?>
<?php if(!empty($article['category']['name'])): ?>
<span class="article-badge badge-category">🏷️ <?= htmlspecialchars($article['category']['name']) ?></span>
<?php endif; ?>
</div>
<?php endif; ?>

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
⏱️ <?= htmlspecialchars($article['read_time']) ?> min di lettura
</span>
<?php endif; ?>
</div>
</header>

<?php 
$imageUrl = getFullImageUrl($article['image_url'] ?? null);
if($imageUrl): 
?>
<img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="article-image">
<?php endif; ?>

<div class="article-body">
<?php 
$content = $article['content'] ?? $article['body'] ?? '';
if($content) {
    echo $content;
} elseif(!empty($article['excerpt'])) {
    echo '<p>' . nl2br(htmlspecialchars($article['excerpt'])) . '</p>';
} else {
    echo '<p>Contenuto non disponibile.</p>';
}
?>
</div>

<footer class="article-footer <?= $isInternal ? 'internal' : '' ?>">
<div class="share-section">
<h3 class="share-title">Condividi questa news</h3>
<?php 
// URL pubblico per condivisione (senza autenticazione)
$publicUrl = 'https://admin.mycb.it/news_public.php?id=' . $article['id'] . '&share=1';
?>
<div class="share-buttons">
<a href="mailto:?subject=<?= urlencode($article['title']) ?>&body=<?= urlencode($article['title'] . ' - ' . $publicUrl) ?>" class="share-btn share-email">
📧 Email
</a>
<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($publicUrl) ?>" target="_blank" class="share-btn share-linkedin">
🔗 LinkedIn
</a>
<a href="https://wa.me/?text=<?= urlencode($article['title'] . ' - ' . $publicUrl) ?>" target="_blank" class="share-btn share-whatsapp">
💬 WhatsApp
</a>
<a href="https://t.me/share/url?url=<?= urlencode($publicUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" class="share-btn share-telegram" style="background:#0088cc">
✈️ Telegram
</a>
</div>
</div>
</footer>
</article>

<?php require_once 'footer.php'; ?>

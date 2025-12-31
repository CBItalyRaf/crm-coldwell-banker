<?php
require_once 'check_auth.php';
require_once 'config/database.php';
require_once 'helpers/news_api.php';

// Debug ruolo
if($user['crm_role'] !== 'admin') {
    die("Accesso negato. Solo admin può accedere a questa pagina.<br><br>Il tuo ruolo: " . htmlspecialchars($user['crm_role']) . "<br><br><a href='index.php'>Torna alla dashboard</a>");
}

$pageTitle = "Newsletter News CB - CRM Coldwell Banker";
$pdo = getDB();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$visibility = $_GET['visibility'] ?? '';
$limit = 50; // Mostra più news per selezione

$newsData = getNewsArticles($limit, $search ?: null, $category ?: null, $visibility ?: null);
$categories = getNewsCategories();

$articles = $newsData['data'] ?? [];
$total = $newsData['total'] ?? 0;

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:600}
.btn-primary{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600;transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem}
.btn-primary:hover{background:var(--cb-blue)}
.filters-bar{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filters-grid{display:grid;grid-template-columns:1fr auto auto auto;gap:1rem;align-items:center}
.search-box input{width:100%;padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem}
.search-box input:focus{outline:none;border-color:var(--cb-bright-blue)}
.category-select,.visibility-select{padding:.75rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;min-width:180px;cursor:pointer}
.btn-search{background:var(--cb-bright-blue);color:white;border:none;padding:.75rem 1.5rem;border-radius:8px;cursor:pointer;font-weight:600}
.selection-bar{background:#EFF6FF;border:2px solid #3B82F6;padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem;display:none;align-items:center;justify-content:space-between;gap:1rem}
.selection-bar.active{display:flex}
.selection-count{font-weight:600;color:var(--cb-midnight)}
.btn-group{display:flex;gap:.75rem}
.btn-secondary{background:white;border:1px solid #E5E7EB;color:var(--cb-midnight);padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:500;transition:all .2s}
.btn-secondary:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.news-list{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden}
.news-item{display:flex;gap:1rem;padding:1.5rem;border-bottom:1px solid #F3F4F6;transition:background .2s;cursor:pointer}
.news-item:hover{background:var(--bg)}
.news-item.internal{background:#EFF6FF}
.news-item:last-child{border-bottom:none}
.news-checkbox{width:20px;height:20px;cursor:pointer;flex-shrink:0;margin-top:.25rem}
.news-content{flex:1;min-width:0}
.news-item-header{display:flex;gap:.5rem;margin-bottom:.5rem;flex-wrap:wrap}
.news-badge{padding:.25rem .5rem;border-radius:6px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.badge-internal{background:#3B82F6;color:white}
.badge-category{background:#E5E7EB;color:#6B7280}
.news-item-title{font-size:1.05rem;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem}
.news-item-excerpt{font-size:.9rem;color:var(--cb-gray);line-height:1.6;margin-bottom:.5rem}
.news-item-meta{font-size:.85rem;color:var(--cb-gray);display:flex;gap:1rem;flex-wrap:wrap}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal.open{display:flex}
.modal-content{background:white;border-radius:12px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto}
.modal-header{padding:1.5rem;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.25rem;font-weight:600}
.modal-close{background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:var(--cb-gray)}
.modal-body{padding:1.5rem}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--cb-midnight)}
.form-input,.form-textarea{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;font-family:inherit}
.form-input:focus,.form-textarea:focus{outline:none;border-color:var(--cb-bright-blue)}
.form-textarea{min-height:120px;resize:vertical}
.preview-box{background:var(--bg);border:1px solid #E5E7EB;border-radius:8px;padding:1rem;margin-top:.5rem;max-height:400px;overflow-y:auto}
.modal-actions{padding:1.5rem;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:1rem}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--cb-gray)}
@media(max-width:768px){
.filters-grid{grid-template-columns:1fr}
.selection-bar{flex-direction:column;align-items:stretch}
.btn-group{flex-direction:column}
}
</style>

<div class="page-header">
<h1 class="page-title">📧 Newsletter News CB</h1>
<a href="news.php" class="btn-secondary">← Torna alle News</a>
</div>

<div class="filters-bar">
<form method="GET" class="filters-grid">
<div class="search-box">
<input type="text" name="search" placeholder="🔍 Cerca nelle news..." value="<?= htmlspecialchars($search) ?>">
</div>
<select name="visibility" class="visibility-select">
<option value="">Tutte le news</option>
<option value="internal" <?= $visibility === 'internal' ? 'selected' : '' ?>>🔒 Solo CB</option>
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

<div class="selection-bar" id="selectionBar">
<div class="selection-count">
<span id="selectedCount">0</span> news selezionate
</div>
<div class="btn-group">
<button class="btn-secondary" onclick="deselectAll()">Deseleziona tutto</button>
<button class="btn-primary" onclick="openNewsletterModal()">📧 Crea Newsletter</button>
</div>
</div>

<?php if(empty($articles)): ?>
<div class="empty-state">
<div style="font-size:4rem;margin-bottom:1rem;opacity:.3">📋</div>
<h3>Nessuna news trovata</h3>
<p>Prova a modificare i filtri di ricerca</p>
</div>
<?php else: ?>
<div class="news-list">
<?php foreach($articles as $article): ?>
<?php $isInternal = ($article['visibility'] ?? 'public') === 'internal'; ?>
<div class="news-item <?= $isInternal ? 'internal' : '' ?>" onclick="toggleCheckbox(event, <?= $article['id'] ?>)">
<input type="checkbox" class="news-checkbox" id="news_<?= $article['id'] ?>" value="<?= $article['id'] ?>" data-title="<?= htmlspecialchars($article['title']) ?>" data-internal="<?= $isInternal ? '1' : '0' ?>">
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
<?php if(!empty($article['excerpt'])): ?>
<p class="news-item-excerpt"><?= htmlspecialchars(substr($article['excerpt'], 0, 200)) ?>...</p>
<?php endif; ?>
<div class="news-item-meta">
<span>📅 <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?></span>
<?php if(!empty($article['author'])): ?>
<span>✍️ <?= htmlspecialchars($article['author']) ?></span>
<?php endif; ?>
<a href="news_detail.php?id=<?= $article['id'] ?>" onclick="event.stopPropagation()" style="color:var(--cb-bright-blue);text-decoration:none;font-weight:600">Vedi dettaglio →</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Newsletter -->
<div class="modal" id="newsletterModal">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title">📧 Crea Newsletter</h2>
<button class="modal-close" onclick="closeNewsletterModal()">✕</button>
</div>
<div class="modal-body">
<form id="newsletterForm" action="send_newsletter.php" method="POST">
<input type="hidden" name="news_ids" id="newsIds">

<div class="form-group">
<label class="form-label">News Selezionate (<span id="modalCount">0</span>)</label>
<div class="preview-box" id="selectedNewsList"></div>
</div>

<div class="form-group">
<label class="form-label">Destinatari Email *</label>
<input type="text" name="recipients" class="form-input" placeholder="email@esempio.it, altra@esempio.it" required>
<small style="color:var(--cb-gray);font-size:.85rem">Separa più email con virgole</small>
</div>

<div class="form-group">
<label class="form-label">Oggetto Email *</label>
<input type="text" name="subject" class="form-input" placeholder="Newsletter CB Italia - Dicembre 2025" required>
</div>

<div class="form-group">
<label class="form-label">Messaggio Introduttivo</label>
<textarea name="intro_message" class="form-textarea" placeholder="Testo opzionale da inserire prima delle news..."></textarea>
</div>

<div class="form-group">
<label class="form-label">
<input type="checkbox" name="send_test" value="1" style="width:auto;margin-right:.5rem">
Invia email di test a me stesso prima di inviare
</label>
</div>
</div>
<div class="modal-actions">
<button type="button" class="btn-secondary" onclick="closeNewsletterModal()">Annulla</button>
<button type="submit" class="btn-primary">📧 Invia Newsletter</button>
</div>
</form>
</div>
</div>

<script>
function toggleCheckbox(event, newsId) {
    // Non fare nulla se si clicca sul checkbox stesso o sul link
    if (event.target.type === 'checkbox' || event.target.tagName === 'A') return;
    
    const checkbox = document.getElementById('news_' + newsId);
    checkbox.checked = !checkbox.checked;
    updateSelectionBar();
}

function updateSelectionBar() {
    const checkboxes = document.querySelectorAll('.news-checkbox:checked');
    const count = checkboxes.length;
    
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectionBar').classList.toggle('active', count > 0);
}

function deselectAll() {
    document.querySelectorAll('.news-checkbox').forEach(cb => cb.checked = false);
    updateSelectionBar();
}

function openNewsletterModal() {
    const checkboxes = document.querySelectorAll('.news-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Seleziona almeno una news');
        return;
    }
    
    const newsIds = Array.from(checkboxes).map(cb => cb.value);
    const newsList = Array.from(checkboxes).map(cb => {
        const isInternal = cb.dataset.internal === '1';
        return `<div style="padding:.75rem;border-bottom:1px solid #E5E7EB">
            ${isInternal ? '🔒' : '📰'} ${cb.dataset.title}
        </div>`;
    }).join('');
    
    document.getElementById('newsIds').value = newsIds.join(',');
    document.getElementById('modalCount').textContent = checkboxes.length;
    document.getElementById('selectedNewsList').innerHTML = newsList;
    document.getElementById('newsletterModal').classList.add('open');
}

function closeNewsletterModal() {
    document.getElementById('newsletterModal').classList.remove('open');
}

// Aggiungi listener ai checkbox
document.querySelectorAll('.news-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectionBar);
});

// Chiudi modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNewsletterModal();
    }
});
</script>

<?php require_once 'footer.php'; ?>

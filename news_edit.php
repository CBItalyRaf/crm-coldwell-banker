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

// Carica articolo esistente
$article = getNewsArticle($id);

if(!$article || !isset($article['title'])) {
    header('Location: news.php');
    exit;
}

// Processa form submit
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'excerpt' => $_POST['excerpt'] ?? '',
        'content' => $_POST['content'] ?? '',
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'visibility' => $_POST['visibility'] ?? 'public',
        'image_url' => $_POST['image_url'] ?? null,
        'author' => $_POST['author'] ?? null,
        'published_at' => $_POST['published_at'] ?? null,
    ];
    
    $result = updateNewsArticle($id, $updateData);
    
    if($result) {
        header('Location: news_detail.php?id=' . $id . '&updated=1');
        exit;
    } else {
        $error = "Errore durante l'aggiornamento. Verifica che l'API supporti l'editing.";
    }
}

$categories = getNewsCategories();
$pageTitle = "Modifica News - CRM Coldwell Banker";

require_once 'header.php';
?>

<style>
.edit-container{max-width:900px;margin:0 auto}
.edit-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.edit-title{font-size:1.75rem;font-weight:600}
.btn-back{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:500;transition:all .2s}
.btn-back:hover{border-color:var(--cb-bright-blue);color:var(--cb-bright-blue)}
.edit-form{background:white;padding:2.5rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.form-section{margin-bottom:2.5rem}
.form-section:last-child{margin-bottom:0}
.section-title{font-size:1.25rem;font-weight:600;color:var(--cb-midnight);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--bg)}
.form-group{margin-bottom:1.5rem}
.form-label{display:block;font-weight:600;color:var(--cb-midnight);margin-bottom:.5rem;font-size:.95rem}
.form-input,.form-textarea,.form-select{width:100%;padding:.875rem 1rem;border:1px solid #E5E7EB;border-radius:8px;font-size:.95rem;font-family:inherit;transition:border-color .2s}
.form-input:focus,.form-textarea:focus,.form-select:focus{outline:none;border-color:var(--cb-bright-blue);box-shadow:0 0 0 3px rgba(31,105,255,.1)}
.form-textarea{min-height:150px;resize:vertical;font-family:inherit;line-height:1.6}
.form-textarea.large{min-height:400px}
.form-hint{font-size:.85rem;color:var(--cb-gray);margin-top:.5rem}
.radio-group{display:flex;gap:1.5rem;margin-top:.75rem}
.radio-label{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:500}
.radio-label input[type="radio"]{width:18px;height:18px;cursor:pointer}
.form-actions{display:flex;gap:1rem;padding-top:2rem;border-top:2px solid var(--bg);justify-content:flex-end}
.btn-submit{background:var(--cb-bright-blue);color:white;border:none;padding:.875rem 2rem;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn-submit:hover{background:var(--cb-blue)}
.btn-cancel{background:transparent;border:1px solid #E5E7EB;color:var(--cb-gray);padding:.875rem 2rem;border-radius:8px;font-size:1rem;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none}
.btn-cancel:hover{border-color:var(--cb-gray)}
.alert-error{background:#FEE2E2;border:1px solid #EF4444;color:#991B1B;padding:1rem 1.5rem;border-radius:8px;margin-bottom:2rem}
.alert-info{background:#DBEAFE;border:1px solid #3B82F6;color:#1E40AF;padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem}
@media(max-width:768px){
.edit-form{padding:1.5rem}
.form-actions{flex-direction:column}
.btn-submit,.btn-cancel{width:100%;justify-content:center}
}
</style>

<div class="edit-container">
<div class="edit-header">
<h1 class="edit-title">✏️ Modifica News</h1>
<a href="news_detail.php?id=<?= $id ?>" class="btn-back">← Annulla e torna</a>
</div>

<?php if(isset($error)): ?>
<div class="alert-error">
❌ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="alert-info">
ℹ️ <strong>Nota:</strong> Le modifiche verranno salvate nell'API esterna. Assicurati che i dati siano corretti prima di salvare.
</div>

<form method="POST" class="edit-form">
<!-- Informazioni Base -->
<div class="form-section">
<h2 class="section-title">📝 Informazioni Base</h2>

<div class="form-group">
<label class="form-label" for="title">Titolo *</label>
<input type="text" id="title" name="title" class="form-input" value="<?= htmlspecialchars($article['title'] ?? '') ?>" required>
</div>

<div class="form-group">
<label class="form-label" for="slug">Slug URL</label>
<input type="text" id="slug" name="slug" class="form-input" value="<?= htmlspecialchars($article['slug'] ?? '') ?>">
<div class="form-hint">URL-friendly (es: conformita-catastale-2025)</div>
</div>

<div class="form-group">
<label class="form-label" for="excerpt">Estratto / Anteprima</label>
<textarea id="excerpt" name="excerpt" class="form-textarea"><?= htmlspecialchars($article['excerpt'] ?? '') ?></textarea>
<div class="form-hint">Breve descrizione che appare nelle anteprime</div>
</div>
</div>

<!-- Contenuto -->
<div class="form-section">
<h2 class="section-title">📄 Contenuto</h2>

<div class="form-group">
<label class="form-label" for="content">Contenuto HTML *</label>
<textarea id="content" name="content" class="form-textarea large" required><?= htmlspecialchars($article['content'] ?? $article['body'] ?? '') ?></textarea>
<div class="form-hint">Supporta HTML per formattazione (p, h2, h3, ul, ol, strong, em, a)</div>
</div>
</div>

<!-- Categorizzazione -->
<div class="form-section">
<h2 class="section-title">🏷️ Categorizzazione</h2>

<div class="form-group">
<label class="form-label" for="category_id">Categoria</label>
<select id="category_id" name="category_id" class="form-select">
<option value="">Nessuna categoria</option>
<?php 
$categoriesList = $categories['data'] ?? $categories ?? [];
foreach($categoriesList as $cat): 
?>
<option value="<?= $cat['id'] ?>" <?= (!empty($article['category']['id']) && $article['category']['id'] == $cat['id']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label class="form-label">Visibilità *</label>
<div class="radio-group">
<label class="radio-label">
<input type="radio" name="visibility" value="public" <?= ($article['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
📰 Public (Visibile a tutti)
</label>
<label class="radio-label">
<input type="radio" name="visibility" value="internal" <?= ($article['visibility'] ?? 'public') === 'internal' ? 'checked' : '' ?>>
🔒 Internal (Solo CB)
</label>
</div>
</div>
</div>

<!-- Media & Metadata -->
<div class="form-section">
<h2 class="section-title">🖼️ Media & Metadata</h2>

<div class="form-group">
<label class="form-label" for="image_url">URL Immagine</label>
<input type="url" id="image_url" name="image_url" class="form-input" value="<?= htmlspecialchars($article['image_url'] ?? '') ?>" placeholder="https://esempio.com/immagine.jpg">
<div class="form-hint">URL completo dell'immagine hero</div>
</div>

<div class="form-group">
<label class="form-label" for="author">Autore</label>
<input type="text" id="author" name="author" class="form-input" value="<?= htmlspecialchars($article['author'] ?? '') ?>" placeholder="Coldwell Banker Italy">
</div>

<div class="form-group">
<label class="form-label" for="published_at">Data Pubblicazione</label>
<input type="datetime-local" id="published_at" name="published_at" class="form-input" value="<?= !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : '' ?>">
<div class="form-hint">Lascia vuoto per usare data corrente</div>
</div>
</div>

<!-- Azioni -->
<div class="form-actions">
<a href="news_detail.php?id=<?= $id ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-submit">💾 Salva Modifiche</button>
</div>
</form>
</div>

<?php require_once 'footer.php'; ?>

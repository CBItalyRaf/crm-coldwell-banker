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
    $imageUrl = $article['image_url'] ?? null; // Mantieni URL esistente di default
    
    // Gestione rimozione immagine
    if(isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        // Elimina file se locale
        if(!empty($article['image_url']) && strpos($article['image_url'], 'admin.mycb.it/uploads/news/') !== false) {
            $uploadDir = '/var/www/admin.mycb.it/uploads/news/';
            $oldFile = str_replace('https://admin.mycb.it/uploads/news/', $uploadDir, $article['image_url']);
            if(file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
        $imageUrl = null; // Rimuovi URL
    }
    
    // Gestione upload immagine
    if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '/var/www/admin.mycb.it/uploads/news/';
        
        // Crea directory se non esiste
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['image']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Valida estensione
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if(in_array($extension, $allowedExtensions)) {
            // Nome file univoco
            $fileName = 'news_' . $id . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $fileName;
            
            if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                // URL pubblico
                $imageUrl = 'https://admin.mycb.it/uploads/news/' . $fileName;
                
                // Elimina vecchia immagine se esiste
                if(!empty($article['image_url']) && strpos($article['image_url'], 'admin.mycb.it/uploads/news/') !== false) {
                    $oldFile = str_replace('https://admin.mycb.it/uploads/news/', $uploadDir, $article['image_url']);
                    if(file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
            } else {
                $error = "Errore durante l'upload dell'immagine";
            }
        } else {
            $error = "Formato file non supportato. Usa: JPG, PNG, GIF, WEBP";
        }
    }
    
    // Se non ci sono errori di upload, procedi con l'update
    if(!isset($error)) {
        $updateData = [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'excerpt' => $_POST['excerpt'] ?? '',
            'content' => $_POST['content'] ?? '',
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'visibility' => $_POST['visibility'] ?? 'public',
            'image_url' => $imageUrl,
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
.image-preview-container{margin-bottom:1rem}
.image-preview{max-width:100%;height:auto;max-height:400px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.image-actions{display:flex;gap:1rem;margin-top:1rem}
.btn-remove-image{background:#EF4444;color:white;border:none;padding:.5rem 1rem;border-radius:6px;font-size:.85rem;cursor:pointer;transition:background .2s}
.btn-remove-image:hover{background:#DC2626}
.upload-hint{background:var(--bg);padding:1rem;border-radius:8px;margin-top:.5rem;font-size:.85rem;color:var(--cb-gray)}
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

<form method="POST" enctype="multipart/form-data" class="edit-form">
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
<label class="form-label" for="content">Contenuto Articolo *</label>
<textarea id="content" name="content" class="form-textarea large" required><?= htmlspecialchars($article['content'] ?? $article['body'] ?? '') ?></textarea>
<div class="form-hint">
Editor visuale - scrivi come in Word, il sistema genera automaticamente l'HTML
</div>
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
<h2 class="section-title">🖼️ Immagine & Metadata</h2>

<div class="form-group">
<label class="form-label">Immagine Articolo</label>

<?php if(!empty($article['image_url'])): ?>
<div class="image-preview-container">
<img src="<?= htmlspecialchars($article['image_url']) ?>" alt="Immagine attuale" class="image-preview" id="currentImage">
<div class="image-actions">
<button type="button" class="btn-remove-image" onclick="removeImage()">🗑️ Rimuovi Immagine</button>
</div>
</div>
<?php endif; ?>

<input type="file" id="image" name="image" class="form-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImage(event)">
<div class="upload-hint">
📸 <strong>Formati supportati:</strong> JPG, PNG, GIF, WEBP<br>
💡 <strong>Dimensioni consigliate:</strong> 1200x630px (ottimale per social)<br>
<?php if(!empty($article['image_url'])): ?>
⚠️ Caricando una nuova immagine, sostituirai quella attuale
<?php endif; ?>
</div>

<!-- Preview nuova immagine -->
<div id="newImagePreview" style="display:none;margin-top:1rem">
<p style="font-weight:600;margin-bottom:0.5rem;color:var(--cb-bright-blue)">📎 Nuova immagine:</p>
<img id="previewImg" alt="Anteprima" class="image-preview">
</div>
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

<script>
function previewImage(event) {
    const file = event.target.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('newImagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

function removeImage() {
    if(confirm('Sei sicuro di voler rimuovere l\'immagine? Dovrai salvare per confermare la modifica.')) {
        document.getElementById('currentImage').parentElement.style.display = 'none';
        // Aggiungi campo hidden per indicare rimozione
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_image';
        input.value = '1';
        document.querySelector('form').appendChild(input);
    }
}
</script>

<!-- Azioni -->
<div class="form-actions">
<a href="news_detail.php?id=<?= $id ?>" class="btn-cancel">Annulla</a>
<button type="submit" class="btn-submit">💾 Salva Modifiche</button>
</div>
</form>
</div>

<!-- TinyMCE Editor -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content',
    height: 500,
    menubar: false,
    language: 'it',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
             'alignleft aligncenter alignright alignjustify | ' +
             'bullist numlist outdent indent | link image | ' +
             'removeformat code | help',
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
    
    // Configurazione immagini
    images_upload_url: false, // Disabilita upload, usa solo URL
    automatic_uploads: false,
    
    // Pulizia HTML
    paste_as_text: false,
    paste_word_valid_elements: 'p,b,strong,i,em,h1,h2,h3,h4,ul,ol,li,a,img',
    
    // Stili personalizzati
    style_formats: [
        { title: 'Paragrafo', block: 'p' },
        { title: 'Titolo 2', block: 'h2' },
        { title: 'Titolo 3', block: 'h3' },
        { title: 'Citazione', block: 'blockquote' }
    ],
    
    setup: function(editor) {
        editor.on('init', function() {
            console.log('TinyMCE caricato con successo');
        });
    }
});

// Auto-genera slug da titolo
document.getElementById('title').addEventListener('input', function() {
    const slugField = document.getElementById('slug');
    if(!slugField.value || slugField.dataset.autogenerated === 'true') {
        const slug = this.value
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Rimuove accenti
            .replace(/[^a-z0-9\s-]/g, '') // Solo lettere, numeri, spazi, trattini
            .trim()
            .replace(/\s+/g, '-') // Spazi → trattini
            .replace(/-+/g, '-'); // Trattini multipli → singolo
        slugField.value = slug;
        slugField.dataset.autogenerated = 'true';
    }
});

document.getElementById('slug').addEventListener('input', function() {
    this.dataset.autogenerated = 'false';
});
</script>

<?php require_once 'footer.php'; ?>

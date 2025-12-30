<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Gestione API Keys";

// Solo admin
if ($user['crm_role'] !== 'admin') {
    die("Accesso negato - Solo amministratori");
}

$message = '';

// Genera nuova API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $name = trim($_POST['name']);
    
    if (empty($name)) {
        $message = '<div style="background:#FEE2E2;color:#991B1B;padding:1rem;border-radius:6px;margin-bottom:1rem">Nome obbligatorio</div>';
    } else {
        // Genera API key sicura
        $api_key = bin2hex(random_bytes(32)); // 64 caratteri hex
        
        $stmt = $pdo->prepare("INSERT INTO api_keys (api_key, name, is_active) VALUES (:key, :name, 1)");
        $stmt->execute(['key' => $api_key, 'name' => $name]);
        
        $message = '<div style="background:#D1FAE5;color:#065F46;padding:1rem;border-radius:6px;margin-bottom:1rem">
            <strong>✓ API Key generata!</strong><br><br>
            <div style="background:white;padding:1rem;border-radius:4px;font-family:monospace;word-break:break-all;color:#000">' . $api_key . '</div>
            <br><small>⚠️ SALVALA SUBITO - Non verrà più mostrata!</small>
        </div>';
    }
}

// Disattiva API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable') {
    $id = $_POST['key_id'];
    $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = :id")->execute(['id' => $id]);
    $message = '<div style="background:#D1FAE5;color:#065F46;padding:1rem;border-radius:6px;margin-bottom:1rem">✓ API Key disattivata</div>';
}

// Riattiva API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enable') {
    $id = $_POST['key_id'];
    $pdo->prepare("UPDATE api_keys SET is_active = 1 WHERE id = :id")->execute(['id' => $id]);
    $message = '<div style="background:#D1FAE5;color:#065F46;padding:1rem;border-radius:6px;margin-bottom:1rem">✓ API Key riattivata</div>';
}

// Recupera tutte le API keys
$keys = $pdo->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll();

require_once 'header.php';
?>

<style>
.api-container{max-width:900px;margin:2rem auto}
.card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:1.5rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-weight:600;margin-bottom:.5rem}
.form-group input{width:100%;padding:.75rem;border:1px solid #E5E7EB;border-radius:6px}
.btn{padding:.75rem 1.5rem;border-radius:6px;border:none;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block}
.btn-primary{background:var(--cb-bright-blue);color:white}
.btn-danger{background:#EF4444;color:white}
.btn-success{background:#10B981;color:white}
.btn-secondary{background:#6B7280;color:white}
.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:.75rem;border-bottom:2px solid #E5E7EB;font-weight:600;font-size:.875rem;text-transform:uppercase;color:var(--cb-gray)}
.table td{padding:.75rem;border-bottom:1px solid #E5E7EB}
.badge{padding:.25rem .75rem;border-radius:999px;font-size:.75rem;font-weight:600}
.badge-active{background:#D1FAE5;color:#065F46}
.badge-inactive{background:#FEE2E2;color:#991B1B}
.api-key{font-family:monospace;background:#F3F4F6;padding:.5rem;border-radius:4px;font-size:.85rem}
</style>

<div class="api-container">
    <h1>🔑 Gestione API Keys</h1>
    <p style="color:var(--cb-gray);margin-bottom:2rem">Genera API keys per autenticare chiamate server-to-server (es. da Booking System)</p>

    <?= $message ?>

    <div class="card">
        <h2 style="margin-bottom:1rem">Genera Nuova API Key</h2>
        <form method="POST">
            <input type="hidden" name="action" value="generate">
            <div class="form-group">
                <label>Nome Applicazione</label>
                <input type="text" name="name" placeholder="es. Booking System" required>
            </div>
            <button type="submit" class="btn btn-primary">🔑 Genera API Key</button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-bottom:1rem">API Keys Esistenti</h2>
        
        <?php if (empty($keys)): ?>
            <p style="text-align:center;color:var(--cb-gray);padding:2rem">Nessuna API key generata</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>API Key</th>
                        <th>Creata</th>
                        <th>Ultimo Uso</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($key['name']) ?></strong></td>
                        <td>
                            <span class="api-key"><?= substr($key['api_key'], 0, 16) ?>...<?= substr($key['api_key'], -8) ?></span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($key['created_at'])) ?></td>
                        <td><?= $key['last_used_at'] ? date('d/m/Y H:i', strtotime($key['last_used_at'])) : 'Mai usata' ?></td>
                        <td>
                            <?php if ($key['is_active']): ?>
                                <span class="badge badge-active">Attiva</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Disattivata</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($key['is_active']): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:.5rem 1rem;font-size:.85rem" onclick="return confirm('Disattivare questa API key?')">Disattiva</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="enable">
                                    <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                    <button type="submit" class="btn btn-success" style="padding:.5rem 1rem;font-size:.85rem">Riattiva</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" style="background:#FEF3C7;border-left:4px solid #F59E0B">
        <h3 style="margin-bottom:.5rem">📖 Come Usare</h3>
        <p style="margin-bottom:1rem">Da Booking System, invia richieste con header:</p>
        <pre style="background:white;padding:1rem;border-radius:6px;overflow-x:auto">X-API-Key: [LA_TUA_API_KEY]</pre>
        <p style="margin-top:1rem;font-size:.9rem">Esempio curl:</p>
        <pre style="background:white;padding:1rem;border-radius:6px;overflow-x:auto;font-size:.85rem">curl -X POST https://admin.mycb.it/api/calendar_events.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{"title":"Test","start_datetime":"2025-01-20 10:00:00","end_datetime":"2025-01-20 12:00:00"}'</pre>
    </div>
</div>

<?php require_once 'footer.php'; ?>

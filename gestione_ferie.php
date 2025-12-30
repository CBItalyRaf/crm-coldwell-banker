<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();
$user = $_SESSION['crm_user'];
$pageTitle = "Gestione Ferie - CRM Coldwell Banker";

// Solo Raf e Sara possono approvare ferie
$canApproveLeaves = in_array($user['email'], [
    'raffaella.pace@cbitaly.it',
    'sara.mazoni@cbitaly.it'
]);

// Gestione approvazione/rifiuto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canApproveLeaves) {
    $leave_id = $_POST['leave_id'] ?? null;
    $action = $_POST['action'] ?? null; // 'approve' o 'reject'
    
    if ($leave_id && in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $stmt = $pdo->prepare("UPDATE team_leaves SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $leave_id]);
        
        header('Location: ferie.php?success=1');
        exit;
    }
}

// Carica richieste
if ($canApproveLeaves) {
    // Admin vede tutte le richieste pending
    $stmt = $pdo->prepare("
        SELECT * FROM team_leaves 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchAll();
    
    // Storia recente (ultimi 20 approved/rejected)
    $stmt = $pdo->prepare("
        SELECT * FROM team_leaves 
        WHERE status IN ('approved', 'rejected')
        ORDER BY updated_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $historyLeaves = $stmt->fetchAll();
} else {
    // Utenti normali vedono solo le proprie richieste
    $stmt = $pdo->prepare("
        SELECT * FROM team_leaves 
        WHERE user_email = :email
        ORDER BY created_at DESC
    ");
    $stmt->execute(['email' => $user['email']]);
    $myLeaves = $stmt->fetchAll();
}

require_once 'header.php';
?>

<style>
.page-header{background:white;padding:1.5rem;margin-bottom:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.page-title{font-size:1.5rem;font-weight:600;color:var(--cb-midnight);margin:0}
.section{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:2rem}
.section-title{font-size:1.1rem;font-weight:600;color:var(--cb-blue);margin-bottom:1.5rem;text-transform:uppercase;letter-spacing:.05em}
.leave-card{background:var(--bg);padding:1.5rem;border-radius:8px;margin-bottom:1rem;border-left:4px solid}
.leave-card.pending{border-left-color:#F59E0B}
.leave-card.approved{border-left-color:#10B981}
.leave-card.rejected{border-left-color:#EF4444}
.leave-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem}
.leave-user{font-weight:600;font-size:1.1rem;color:var(--cb-midnight)}
.leave-type{display:inline-block;padding:.25rem .75rem;border-radius:6px;font-size:.85rem;font-weight:600}
.leave-type.ferie{background:#D1FAE5;color:#065F46}
.leave-type.malattia{background:#FEE2E2;color:#991B1B}
.leave-type.permesso{background:#FEF3C7;color:#92400E}
.leave-type.smartworking{background:#DBEAFE;color:#1E40AF}
.leave-type.altro{background:#E5E7EB;color:#374151}
.leave-dates{font-size:.95rem;color:var(--cb-gray);margin-bottom:.75rem}
.leave-notes{font-size:.9rem;color:var(--cb-gray);font-style:italic;margin-bottom:1rem}
.leave-actions{display:flex;gap:.75rem}
.btn-approve{background:#10B981;color:white;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:600;font-size:.85rem}
.btn-approve:hover{background:#059669}
.btn-reject{background:#EF4444;color:white;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:600;font-size:.85rem}
.btn-reject:hover{background:#DC2626}
.status-badge{display:inline-block;padding:.25rem .75rem;border-radius:6px;font-size:.8rem;font-weight:600}
.status-badge.approved{background:#D1FAE5;color:#065F46}
.status-badge.rejected{background:#FEE2E2;color:#991B1B}
.status-badge.pending{background:#FEF3C7;color:#92400E}
.empty-state{text-align:center;padding:3rem;color:var(--cb-gray)}
.empty-icon{font-size:3rem;margin-bottom:1rem;opacity:.3}
</style>

<div class="page-header">
    <h1 class="page-title">🌴 Gestione Ferie & Assenze</h1>
</div>

<?php if ($canApproveLeaves): ?>
    
    <!-- Richieste in Attesa -->
    <div class="section">
        <h2 class="section-title">⏳ Richieste in Attesa (<?= count($pendingLeaves) ?>)</h2>
        
        <?php if (empty($pendingLeaves)): ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <p>Nessuna richiesta in attesa</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingLeaves as $leave): ?>
                <div class="leave-card pending">
                    <div class="leave-header">
                        <div>
                            <div class="leave-user"><?= htmlspecialchars($leave['user_name']) ?></div>
                            <div style="font-size:.85rem;color:var(--cb-gray);margin-top:.25rem"><?= htmlspecialchars($leave['user_email']) ?></div>
                        </div>
                        <span class="leave-type <?= $leave['leave_type'] ?>">
                            <?php
                            $icons = ['ferie' => '🏖️', 'malattia' => '🤒', 'permesso' => '📋', 'smartworking' => '🏠', 'altro' => '📅'];
                            echo $icons[$leave['leave_type']] ?? '📅';
                            ?>
                            <?= ucfirst($leave['leave_type']) ?>
                        </span>
                    </div>
                    
                    <div class="leave-dates">
                        📅 <strong>Dal <?= date('d/m/Y', strtotime($leave['start_date'])) ?></strong> al <strong><?= date('d/m/Y', strtotime($leave['end_date'])) ?></strong>
                        <?php
                        $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
                        echo " ({$days} " . ($days == 1 ? 'giorno' : 'giorni') . ")";
                        ?>
                    </div>
                    
                    <?php if ($leave['notes']): ?>
                        <div class="leave-notes">💬 <?= htmlspecialchars($leave['notes']) ?></div>
                    <?php endif; ?>
                    
                    <div style="font-size:.8rem;color:var(--cb-gray);margin-bottom:1rem">
                        Richiesta il <?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?>
                    </div>
                    
                    <form method="POST" style="display:inline" onsubmit="return confirm('Approvare questa richiesta?')">
                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn-approve">✓ Approva</button>
                    </form>
                    
                    <form method="POST" style="display:inline" onsubmit="return confirm('Rifiutare questa richiesta?')">
                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn-reject">✕ Rifiuta</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Storico -->
    <div class="section">
        <h2 class="section-title">📜 Storico Recente</h2>
        
        <?php if (empty($historyLeaves)): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Nessuno storico</p>
            </div>
        <?php else: ?>
            <?php foreach ($historyLeaves as $leave): ?>
                <div class="leave-card <?= $leave['status'] ?>">
                    <div class="leave-header">
                        <div>
                            <div class="leave-user"><?= htmlspecialchars($leave['user_name']) ?></div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <span class="leave-type <?= $leave['leave_type'] ?>">
                                <?php
                                $icons = ['ferie' => '🏖️', 'malattia' => '🤒', 'permesso' => '📋', 'smartworking' => '🏠', 'altro' => '📅'];
                                echo $icons[$leave['leave_type']] ?? '📅';
                                ?>
                                <?= ucfirst($leave['leave_type']) ?>
                            </span>
                            <span class="status-badge <?= $leave['status'] ?>">
                                <?= $leave['status'] === 'approved' ? '✓ Approvata' : '✕ Rifiutata' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="leave-dates">
                        📅 Dal <?= date('d/m/Y', strtotime($leave['start_date'])) ?> al <?= date('d/m/Y', strtotime($leave['end_date'])) ?>
                    </div>
                    
                    <div style="font-size:.8rem;color:var(--cb-gray)">
                        Gestita il <?= date('d/m/Y H:i', strtotime($leave['updated_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    
    <!-- Vista Utente Normale - Solo le proprie richieste -->
    <div class="section">
        <h2 class="section-title">📋 Le Mie Richieste</h2>
        
        <?php if (empty($myLeaves)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌴</div>
                <p>Nessuna richiesta ancora inviata</p>
                <p style="font-size:.9rem;margin-top:.5rem">
                    <a href="team_calendar.php" style="color:var(--cb-bright-blue);font-weight:600">Vai al calendario per richiedere ferie</a>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($myLeaves as $leave): ?>
                <div class="leave-card <?= $leave['status'] ?>">
                    <div class="leave-header">
                        <span class="leave-type <?= $leave['leave_type'] ?>">
                            <?php
                            $icons = ['ferie' => '🏖️', 'malattia' => '🤒', 'permesso' => '📋', 'smartworking' => '🏠', 'altro' => '📅'];
                            echo $icons[$leave['leave_type']] ?? '📅';
                            ?>
                            <?= ucfirst($leave['leave_type']) ?>
                        </span>
                        <span class="status-badge <?= $leave['status'] ?>">
                            <?php
                            if ($leave['status'] === 'pending') echo '⏳ In Attesa';
                            elseif ($leave['status'] === 'approved') echo '✓ Approvata';
                            else echo '✕ Rifiutata';
                            ?>
                        </span>
                    </div>
                    
                    <div class="leave-dates">
                        📅 Dal <?= date('d/m/Y', strtotime($leave['start_date'])) ?> al <?= date('d/m/Y', strtotime($leave['end_date'])) ?>
                    </div>
                    
                    <?php if ($leave['notes']): ?>
                        <div class="leave-notes">💬 <?= htmlspecialchars($leave['notes']) ?></div>
                    <?php endif; ?>
                    
                    <div style="font-size:.8rem;color:var(--cb-gray)">
                        Richiesta il <?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once 'footer.php'; ?>

<?php
/**
 * Dashboard CRM Coldwell Banker Italy
 * Home page con statistiche e overview
 */

require_once 'check_auth.php';
require_once 'config/database.php';

$pdo = getDB();

// Statistiche
$agenciesStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active
    FROM agencies
")->fetch();

$agentsStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active
    FROM agents
")->fetch();

$ticketsOpen = 0;

// Ultime agenzie
$recentAgencies = $pdo->query("
    SELECT name, city, created_at 
    FROM agencies 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$user = $_SESSION['crm_user'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRM Coldwell Banker</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cb-blue: #012169;
            --cb-bright-blue: #1F69FF;
            --cb-midnight: #0A1730;
            --cb-gray: #6D7180;
            --bg: #F5F7FA;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--cb-midnight);
            line-height: 1.6;
        }

        .header {
            background: var(--cb-blue);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .logo {
            height: 32px;
        }

        .main-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-item {
            position: relative;
        }

        .nav-button {
            background: transparent;
            border: none;
            color: white;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .nav-button:hover {
            background: rgba(255,255,255,0.1);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1000;
        }

        .nav-item:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1.25rem;
            color: var(--cb-midnight);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .dropdown-item:first-child {
            border-radius: 8px 8px 0 0;
        }

        .dropdown-item:last-child {
            border-radius: 0 0 8px 8px;
        }

        .dropdown-item:hover {
            background: var(--bg);
        }

        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: transparent;
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: background 0.2s;
        }

        .user-button:hover {
            background: rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--cb-bright-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .welcome {
            margin-bottom: 2rem;
        }

        .welcome h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome p {
            color: var(--cb-gray);
            font-size: 0.95rem;
        }

        .search-bar {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .search-bar input {
            width: 100%;
            border: none;
            font-size: 1rem;
            outline: none;
            background: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-left: 4px solid var(--cb-bright-blue);
        }

        .stat-card h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--cb-gray);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--cb-blue);
            margin-bottom: 0.5rem;
        }

        .stat-subtitle {
            font-size: 0.875rem;
            color: var(--cb-gray);
        }

        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .widget {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .widget-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .widget-icon {
            font-size: 1.5rem;
        }

        .widget-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .widget-placeholder {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--cb-gray);
        }

        .widget-placeholder-icon {
            font-size: 3rem;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }

        .recent-activity {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .recent-activity h2 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #F3F4F6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.875rem;
            color: var(--cb-gray);
        }

        .footer {
            background: white;
            border-top: 1px solid #E5E7EB;
            margin-top: 3rem;
            padding: 1.5rem 0;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            text-align: center;
            color: var(--cb-gray);
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                height: auto;
                padding: 1rem;
                gap: 1rem;
            }

            .header-left {
                width: 100%;
                justify-content: space-between;
            }

            .main-nav {
                overflow-x: auto;
                width: 100%;
                padding: 0.5rem 0;
            }

            .container {
                padding: 1rem;
            }

            .welcome h1 {
                font-size: 1.5rem;
            }

            .stats-grid,
            .widgets-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker" class="logo">
                
                <nav class="main-nav">
                    <div class="nav-item">
                        <button class="nav-button">Gestione ▼</button>
                        <div class="dropdown-menu">
                            <a href="agenzie.php" class="dropdown-item">🏢 Agenzie</a>
                            <a href="agenti.php" class="dropdown-item">👥 Agenti</a>
                            <a href="servizi.php" class="dropdown-item">⚙️ Servizi</a>
                        </div>
                    </div>

                    <div class="nav-item">
                        <button class="nav-button">Operations ▼</button>
                        <div class="dropdown-menu">
                            <a href="onboarding.php" class="dropdown-item">📥 Onboarding</a>
                            <a href="offboarding.php" class="dropdown-item">📤 Offboarding</a>
                            <a href="ticket.php" class="dropdown-item">🎫 Ticket</a>
                        </div>
                    </div>

                    <div class="nav-item">
                        <button class="nav-button">Amministrazione ▼</button>
                        <div class="dropdown-menu">
                            <a href="fatture.php" class="dropdown-item">💰 Fatture</a>
                            <a href="fornitori.php" class="dropdown-item">🏪 Fornitori</a>
                        </div>
                    </div>

                    <a href="sviluppo.php" class="nav-button">🚀 Sviluppo</a>

                    <div class="nav-item">
                        <button class="nav-button">Team ▼</button>
                        <div class="dropdown-menu">
                            <a href="ferie.php" class="dropdown-item">🌴 Ferie</a>
                            <a href="calendario.php" class="dropdown-item">📅 Calendario</a>
                        </div>
                    </div>
                </nav>
            </div>

            <div class="nav-item user-menu">
                <button class="user-button">
                    <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                    <span><?= htmlspecialchars($user['name']) ?></span>
                    <span>▼</span>
                </button>
                <div class="dropdown-menu" style="right: 0; left: auto;">
                    <a href="https://coldwellbankeritaly.tech/repository/dashboard/" class="dropdown-item">🏠 Dashboard CB Italia</a>
                    <a href="logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>👋 Benvenuto, <?= htmlspecialchars($user['name']) ?></h1>
            <p>Overview del network Coldwell Banker Italy</p>
        </div>

        <div class="search-bar">
            <input type="text" placeholder="🔍 Cerca agenzie, agenti, ticket...">
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Agenzie</h3>
                <div class="stat-value"><?= $agenciesStats['total'] ?></div>
                <div class="stat-subtitle"><?= $agenciesStats['active'] ?> attive</div>
            </div>

            <div class="stat-card">
                <h3>Agenti</h3>
                <div class="stat-value"><?= $agentsStats['total'] ?></div>
                <div class="stat-subtitle"><?= $agentsStats['active'] ?> attivi</div>
            </div>

            <div class="stat-card">
                <h3>Ticket</h3>
                <div class="stat-value"><?= $ticketsOpen ?></div>
                <div class="stat-subtitle">Aperti</div>
            </div>
        </div>

        <div class="widgets-grid">
            <div class="widget">
                <div class="widget-header">
                    <span class="widget-icon">📅</span>
                    <h3 class="widget-title">Prossimi 7 Giorni</h3>
                </div>
                <div class="widget-placeholder">
                    <div class="widget-placeholder-icon">🚧</div>
                    <p>Calendario eventi<br><small>Disponibile in Fase 2</small></p>
                </div>
            </div>

            <div class="widget">
                <div class="widget-header">
                    <span class="widget-icon">🎫</span>
                    <h3 class="widget-title">Ticket Urgenti</h3>
                </div>
                <div class="widget-placeholder">
                    <div class="widget-placeholder-icon">🚧</div>
                    <p>Sistema ticketing<br><small>Disponibile in Fase 2</small></p>
                </div>
            </div>

            <div class="widget">
                <div class="widget-header">
                    <span class="widget-icon">📰</span>
                    <h3 class="widget-title">News Recenti</h3>
                </div>
                <div class="widget-placeholder">
                    <div class="widget-placeholder-icon">🚧</div>
                    <p>Integrazione News API<br><small>Disponibile in Fase 2</small></p>
                </div>
            </div>
        </div>

        <div class="recent-activity">
            <h2>📈 Ultime Agenzie Aggiunte</h2>
            <?php foreach ($recentAgencies as $agency): ?>
            <div class="activity-item">
                <div class="activity-title"><?= htmlspecialchars($agency['name']) ?></div>
                <div class="activity-meta">
                    <?= htmlspecialchars($agency['city']) ?> · 
                    <?= date('d/m/Y H:i', strtotime($agency['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer">
        <div class="footer-content">
            © <?= date('Y') ?> Coldwell Banker Italy - CRM Network · v1.0
        </div>
    </div>
</body>
</html>

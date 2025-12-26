<?php
/**
 * Dashboard Home CRM Coldwell Banker
 * Mostra statistiche e overview del sistema
 */

// ============================================================================
// AUTENTICAZIONE SSO
// ============================================================================
require_once 'check_auth.php';

// Connessione Database
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'crm_coldwell_banker',
    'username' => 'crm_user',
    'password' => 'CRM_cb2025!Secure',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Errore connessione database: " . $e->getMessage());
}

// ============================================================================
// STATISTICHE AGENZIE
// ============================================================================

$agenciesStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN status = 'Prospect' THEN 1 ELSE 0 END) as prospect,
        SUM(CASE WHEN status = 'Opening' THEN 1 ELSE 0 END) as opening,
        SUM(CASE WHEN data_incomplete = 1 THEN 1 ELSE 0 END) as incomplete
    FROM agencies
")->fetch();

// ============================================================================
// STATISTICHE AGENTI
// ============================================================================

$agentsStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN data_incomplete = 1 THEN 1 ELSE 0 END) as incomplete,
        SUM(CASE WHEN role = 'Broker' THEN 1 ELSE 0 END) as brokers
    FROM agents
")->fetch();

// ============================================================================
// ULTIME AGENZIE AGGIUNTE
// ============================================================================

$recentAgencies = $pdo->query("
    SELECT code, name, city, status, created_at 
    FROM agencies 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

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
            --cb-gray: #f5f5f5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cb-gray);
            color: var(--cb-midnight);
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: var(--cb-blue);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.875rem;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        @media (min-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Card */
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--cb-blue);
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .card-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-top: 0.5rem;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        @media (min-width: 768px) {
            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-card h3 {
            font-size: 1.125rem;
            color: var(--cb-midnight);
            margin-bottom: 1rem;
        }

        /* Recent Activity */
        .recent-list {
            list-style: none;
        }

        .recent-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item-title {
            font-weight: 600;
            color: var(--cb-midnight);
            margin-bottom: 0.25rem;
        }

        .recent-item-meta {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        @media (min-width: 640px) {
            .quick-actions {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .action-btn {
            background: white;
            border: 2px solid var(--cb-blue);
            color: var(--cb-blue);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: var(--cb-blue);
            color: white;
        }

        .action-btn-primary {
            background: var(--cb-blue);
            color: white;
            border-color: var(--cb-blue);
        }

        .action-btn-primary:hover {
            background: var(--cb-light-blue);
            border-color: var(--cb-light-blue);
        }

        /* Status Badge */
        .status-active { color: var(--success); }
        .status-closed { color: var(--danger); }
        .status-prospect { color: var(--warning); }
        .status-inactive { color: #6b7280; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <img src="https://coldwellbankeritaly.tech/repository/dashboard/logo-white.png" alt="Coldwell Banker" style="height: 32px;">
                <div>
                    <h1 style="font-size: 1.25rem; margin-bottom: 0.25rem;">Dashboard CRM</h1>
                    <p style="font-size: 0.875rem; opacity: 0.9; margin: 0;">Network Overview</p>
                </div>
            </div>
            <div style="text-align: right;">
                <p style="margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    👤 <?= htmlspecialchars($_SESSION['crm_user']['name']) ?>
                </p>
                <a href="logout.php" style="color: white; text-decoration: underline; font-size: 0.875rem; opacity: 0.9;">
                    Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- Agenzie Totali -->
            <div class="card">
                <div class="card-title">Agenzie</div>
                <div class="card-value"><?= $agenciesStats['total'] ?></div>
                <div class="card-subtitle">
                    <span class="card-badge badge-success"><?= $agenciesStats['active'] ?> Active</span>
                    <span class="card-badge badge-danger"><?= $agenciesStats['closed'] ?> Closed</span>
                    <span class="card-badge badge-warning"><?= $agenciesStats['prospect'] ?> Prospect</span>
                </div>
            </div>

            <!-- Agenti Totali -->
            <div class="card">
                <div class="card-title">Agenti</div>
                <div class="card-value"><?= $agentsStats['total'] ?></div>
                <div class="card-subtitle">
                    <span class="card-badge badge-success"><?= $agentsStats['active'] ?> Attivi</span>
                    <span class="card-badge badge-info"><?= $agentsStats['inactive'] ?> Inattivi</span>
                </div>
            </div>

            <!-- Broker -->
            <div class="card">
                <div class="card-title">Broker</div>
                <div class="card-value"><?= $agentsStats['brokers'] ?></div>
                <div class="card-subtitle">Titolari di Agenzia</div>
            </div>

            <!-- Dati Incompleti -->
            <div class="card">
                <div class="card-title">⚠️ Da Completare</div>
                <div class="card-value"><?= $agenciesStats['incomplete'] + $agentsStats['incomplete'] ?></div>
                <div class="card-subtitle">
                    <?= $agenciesStats['incomplete'] ?> agenzie, <?= $agentsStats['incomplete'] ?> agenti
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Chart Agenzie -->
            <div class="chart-card">
                <h3>Distribuzione Agenzie</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="agenciesChart"></canvas>
                </div>
            </div>

            <!-- Chart Agenti -->
            <div class="chart-card">
                <h3>Distribuzione Agenti</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="agentsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="agenzie.php" class="action-btn action-btn-primary">📋 Gestione Agenzie</a>
            <a href="agenti.php" class="action-btn">👥 Gestione Agenti</a>
            <a href="servizi.php" class="action-btn">⚙️ Servizi</a>
        </div>

        <!-- Recent Activity -->
        <div class="chart-card" style="margin-top: 1.5rem;">
            <h3>Ultime Agenzie Aggiunte</h3>
            <ul class="recent-list">
                <?php foreach ($recentAgencies as $agency): ?>
                <li class="recent-item">
                    <div class="recent-item-title">
                        <?= htmlspecialchars($agency['name']) ?>
                        <span class="status-<?= strtolower($agency['status']) ?>">
                            ● <?= $agency['status'] ?>
                        </span>
                    </div>
                    <div class="recent-item-meta">
                        <?= $agency['code'] ?> - <?= htmlspecialchars($agency['city']) ?> 
                        · <?= date('d/m/Y', strtotime($agency['created_at'])) ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Chart Agenzie
        new Chart(document.getElementById('agenciesChart'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Closed', 'Prospect', 'Opening'],
                datasets: [{
                    data: [
                        <?= $agenciesStats['active'] ?>,
                        <?= $agenciesStats['closed'] ?>,
                        <?= $agenciesStats['prospect'] ?>,
                        <?= $agenciesStats['opening'] ?>
                    ],
                    backgroundColor: [
                        '#10b981',  // verde success per Active
                        '#ef4444',  // rosso danger per Closed
                        '#f59e0b',  // arancio warning per Prospect
                        '#1F69FF'   // CB bright blue per Opening
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Chart Agenti
        new Chart(document.getElementById('agentsChart'), {
            type: 'bar',
            data: {
                labels: ['Attivi', 'Inattivi', 'Broker'],
                datasets: [{
                    label: 'Numero Agenti',
                    data: [
                        <?= $agentsStats['active'] ?>,
                        <?= $agentsStats['inactive'] ?>,
                        <?= $agentsStats['brokers'] ?>
                    ],
                    backgroundColor: [
                        '#10b981',    // verde success per Attivi
                        '#6b7280',    // grigio per Inattivi
                        '#012169'     // CB blue per Broker
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>

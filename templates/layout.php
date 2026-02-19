<?php if (!defined('WOOMAP')) exit; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <?= CSRF::meta() ?>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- App CSS -->
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div>
                    <h1><?= APP_NAME ?></h1>
                    <small>v<?= APP_VERSION ?></small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Navigation</div>
                    <a href="/dashboard" class="sidebar-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-map"></i>
                        <span>Carte</span>
                    </a>
                    <a href="/analytics" class="sidebar-link <?= ($currentPage ?? '') === 'analytics' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytiques</span>
                    </a>
                    <a href="/stores" class="sidebar-link <?= ($currentPage ?? '') === 'stores' ? 'active' : '' ?>">
                        <i class="fas fa-store"></i>
                        <span>Boutiques</span>
                        <?php $storeCount = Store::count(); if ($storeCount > 0): ?>
                            <span class="badge"><?= $storeCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Exports</div>
                    <a href="/export/csv" class="sidebar-link">
                        <i class="fas fa-file-csv"></i>
                        <span>Exporter CSV</span>
                    </a>
                    <a href="/export/print" target="_blank" class="sidebar-link">
                        <i class="fas fa-print"></i>
                        <span>Imprimer / PDF</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <?php
                $stores = Store::getAll();
                $activeStore = Store::getActive();
                if (count($stores) > 1):
                ?>
                <select class="store-selector" onchange="if(this.value) window.location='/stores/switch?id='+this.value">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= htmlspecialchars($store['id']) ?>"
                            <?= ($activeStore && $activeStore['id'] === $store['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php elseif ($activeStore): ?>
                <div style="font-size:12px;color:var(--text-muted);">
                    <i class="fas fa-store" style="margin-right:6px;"></i>
                    <?= htmlspecialchars($activeStore['name']) ?>
                </div>
                <?php endif; ?>

                <a href="/logout" class="sidebar-link" style="margin-top:12px;color:#ef4444;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobile-menu-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="global-search" placeholder="Rechercher commande, client, ville...">
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Changer le thème">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="notif-btn" id="notif-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notif-badge" data-count="0"></span>
                    </button>
                </div>
            </header>

            <!-- Flash Messages -->
            <?php $flashes = Session::getFlash(); if (!empty($flashes)): ?>
                <div id="alerts" style="padding:16px 24px 0;">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> fade-in">
                            <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <div class="page-content">
                <?= $content ?? '' ?>
            </div>

            <!-- Notification Panel -->
            <div class="notif-panel" id="notif-panel">
                <div class="notif-panel-header">
                    <strong>Notifications</strong>
                    <button id="notif-close" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:16px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="notif-list">
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-bell-slash"></i>
                        <p>Chargement...</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Heat Plugin (for heatmap) -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- App JS -->
    <script src="/public/js/map.js"></script>
    <script src="/public/js/charts.js"></script>
    <script src="/public/js/app.js"></script>
</body>
</html>

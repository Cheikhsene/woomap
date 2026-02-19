<?php if (!defined('WOOMAP')) exit; ?>

<h1 class="page-title">Carte des commandes</h1>
<p class="page-subtitle">Visualisez vos commandes WooCommerce en temps réel sur la carte</p>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon blue"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-card-value" id="stat-total">-</div>
        <div class="stat-card-label">Total commandes</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-card-value" id="stat-completed">-</div>
        <div class="stat-card-label">Complétées</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-card-value" id="stat-processing">-</div>
        <div class="stat-card-label">En cours</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon blue"><i class="fas fa-coins"></i></div>
        <div class="stat-card-value" id="stat-sales">-</div>
        <div class="stat-card-label">Ventes totales</div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
        <div class="filters-bar">
            <div class="status-filters">
                <button class="status-btn active" data-status="all">
                    Tous <span class="count">0</span>
                </button>
                <button class="status-btn" data-status="completed">
                    <i class="fas fa-circle" style="font-size:8px;color:#16a34a;"></i>
                    Complétées <span class="count">0</span>
                </button>
                <button class="status-btn" data-status="processing">
                    <i class="fas fa-circle" style="font-size:8px;color:#d97706;"></i>
                    En cours <span class="count">0</span>
                </button>
                <button class="status-btn" data-status="cancelled">
                    <i class="fas fa-circle" style="font-size:8px;color:#dc2626;"></i>
                    Annulées <span class="count">0</span>
                </button>
            </div>

            <div style="flex:1;"></div>

            <div class="filter-group">
                <label class="filter-label">Du</label>
                <input type="date" class="filter-input" id="filter-date-from">
            </div>
            <div class="filter-group">
                <label class="filter-label">Au</label>
                <input type="date" class="filter-input" id="filter-date-to">
            </div>

            <button class="btn btn-primary btn-sm" id="filter-apply">
                <i class="fas fa-filter"></i> Appliquer
            </button>
            <button class="btn btn-secondary btn-sm" id="refresh-btn" title="Rafraîchir les données">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-secondary btn-sm" id="heatmap-toggle" title="Carte de chaleur">
                <i class="fas fa-fire"></i> Heatmap
            </button>
        </div>
    </div>
</div>

<!-- Map + Side Panel -->
<div class="grid-2" style="grid-template-columns:1fr 340px;">
    <!-- Map -->
    <div>
        <div class="map-container">
            <div id="map"></div>
        </div>
        <div class="heatmap-legend" id="heatmap-legend" style="display:none;margin-top:10px;">
            <span>Faible</span>
            <div class="gradient"></div>
            <span>Élevé</span>
        </div>
    </div>

    <!-- Side Panel -->
    <div>
        <!-- Status Chart -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">Répartition des statuts</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:220px;">
                    <canvas id="chart-status"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Cities -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Top villes</span>
            </div>
            <div class="card-body" id="top-cities">
                <div class="empty-state" style="padding:20px;">
                    <p>Chargement...</p>
                </div>
            </div>
        </div>
    </div>
</div>

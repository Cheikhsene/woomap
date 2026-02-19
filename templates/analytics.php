<?php if (!defined('WOOMAP')) exit; ?>

<div id="analytics-page">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 class="page-title">Analytiques</h1>
            <p class="page-subtitle" style="margin-bottom:0;">Analyse détaillée de vos ventes et commandes</p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/export/csv" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-csv"></i> Exporter CSV
            </a>
            <a href="/export/print" target="_blank" class="btn btn-secondary btn-sm">
                <i class="fas fa-print"></i> Imprimer
            </a>
        </div>
    </div>

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

    <!-- Charts Row 1 -->
    <div class="grid-2" style="margin-bottom:20px;">
        <!-- Monthly Trend -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-chart-line" style="margin-right:8px;color:var(--accent);"></i>Tendance mensuelle</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chart-monthly"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue by Status -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--accent);"></i>Revenus par statut</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chart-revenue"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid-2" style="margin-bottom:20px;">
        <!-- Status Pie -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--accent);"></i>Répartition des statuts</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chart-status"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Cities -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-city" style="margin-right:8px;color:var(--accent);"></i>Top 10 villes</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chart-cities"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-list" style="margin-right:8px;color:var(--accent);"></i>Dernières commandes</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Commande</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Ville</th>
                            <th>Statut</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody id="orders-table-body">
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">
                                <i class="fas fa-spinner fa-spin" style="font-size:20px;"></i>
                                <p style="margin-top:8px;">Chargement des commandes...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

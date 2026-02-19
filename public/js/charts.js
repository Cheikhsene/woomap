/**
 * WooMap - Charts Module
 * Analytics and data visualization using Chart.js.
 */

const WooMapCharts = {
    charts: {},

    /**
     * Render dashboard charts (status pie + monthly trend).
     */
    renderDashboard(stats) {
        this.renderStatusPie(stats);
    },

    /**
     * Render analytics page charts.
     */
    renderAnalytics(stats, orders) {
        this.renderStatusPie(stats);
        this.renderMonthlyTrend(stats.by_month || {});
        this.renderCityBar(stats.by_city || {});
        this.renderRevenueByStatus(stats.by_status || {});
        this.renderOrdersTable(orders);
    },

    /**
     * Order status pie chart.
     */
    renderStatusPie(stats) {
        const canvas = document.getElementById('chart-status');
        if (!canvas) return;

        this.destroy('status');

        const data = {
            labels: ['Complétées', 'En cours', 'Annulées', 'Autres'],
            datasets: [{
                data: [
                    stats.completed || 0,
                    stats.processing || 0,
                    stats.cancelled || 0,
                    stats.other || 0,
                ],
                backgroundColor: ['#16a34a', '#d97706', '#dc2626', '#6b7280'],
                borderWidth: 0,
                borderRadius: 3,
            }],
        };

        this.charts.status = new Chart(canvas, {
            type: 'doughnut',
            data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { size: 12 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                                return `${ctx.label}: ${ctx.raw} (${pct}%)`;
                            }
                        }
                    }
                },
            },
        });
    },

    /**
     * Monthly orders trend line chart.
     */
    renderMonthlyTrend(byMonth) {
        const canvas = document.getElementById('chart-monthly');
        if (!canvas) return;

        this.destroy('monthly');

        const labels = Object.keys(byMonth).map(m => {
            const [year, month] = m.split('-');
            const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            return months[parseInt(month) - 1] + ' ' + year;
        });

        const counts = Object.values(byMonth).map(v => v.count);
        const totals = Object.values(byMonth).map(v => v.total);

        this.charts.monthly = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Commandes',
                        data: counts,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Revenus (FCFA)',
                        data: totals,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, font: { size: 12 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.datasetIndex === 1) {
                                    return ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA';
                                }
                                return ctx.dataset.label + ': ' + ctx.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Commandes' },
                        beginAtZero: true,
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenus (FCFA)' },
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(value);
                            }
                        },
                    },
                },
            },
        });
    },

    /**
     * City bar chart (top 10).
     */
    renderCityBar(byCity) {
        const canvas = document.getElementById('chart-cities');
        if (!canvas) return;

        this.destroy('cities');

        const sorted = Object.entries(byCity)
            .sort((a, b) => b[1].count - a[1].count)
            .slice(0, 10);

        this.charts.cities = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: sorted.map(([city]) => city),
                datasets: [{
                    label: 'Commandes',
                    data: sorted.map(([, data]) => data.count),
                    backgroundColor: '#2563eb',
                    borderRadius: 4,
                    barPercentage: 0.7,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                    },
                },
            },
        });
    },

    /**
     * Revenue by status bar chart.
     */
    renderRevenueByStatus(byStatus) {
        const canvas = document.getElementById('chart-revenue');
        if (!canvas) return;

        this.destroy('revenue');

        const statusLabels = {
            completed: 'Complétées',
            processing: 'En cours',
            cancelled: 'Annulées',
            pending: 'En attente',
            'on-hold': 'En pause',
        };

        const colors = {
            completed: '#16a34a',
            processing: '#d97706',
            cancelled: '#dc2626',
            pending: '#0284c7',
            'on-hold': '#6b7280',
        };

        const entries = Object.entries(byStatus);

        this.charts.revenue = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: entries.map(([status]) => statusLabels[status] || status),
                datasets: [{
                    label: 'Revenus (FCFA)',
                    data: entries.map(([, data]) => data.total),
                    backgroundColor: entries.map(([status]) => colors[status] || '#6b7280'),
                    borderRadius: 4,
                    barPercentage: 0.6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(value);
                            }
                        },
                    },
                },
            },
        });
    },

    /**
     * Render orders table for analytics.
     */
    renderOrdersTable(orders) {
        const tbody = document.getElementById('orders-table-body');
        if (!tbody) return;

        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Aucune commande</td></tr>';
            return;
        }

        tbody.innerHTML = orders.slice(0, 50).map(order => `
            <tr>
                <td><strong>#${order.id}</strong></td>
                <td>${order.date ? new Date(order.date).toLocaleDateString('fr-FR') : '-'}</td>
                <td>${WooMap.escapeHtml(order.customer)}</td>
                <td>${WooMap.escapeHtml(order.city)}</td>
                <td><span class="status-badge ${order.status}">${WooMapMap.translateStatus(order.status)}</span></td>
                <td><strong>${WooMap.formatMoney(order.total)}</strong></td>
            </tr>
        `).join('');
    },

    /**
     * Destroy a chart instance.
     */
    destroy(name) {
        if (this.charts[name]) {
            this.charts[name].destroy();
            delete this.charts[name];
        }
    },
};

/**
 * WooMap - Main Application JavaScript
 * Handles global state, theme, sidebar, notifications, and search.
 */

const WooMap = {
    // Global state
    state: {
        orders: [],
        stats: {},
        theme: localStorage.getItem('woomap-theme') || 'light',
        notificationsOpen: false,
        sidebarOpen: false,
        lastNotifCheck: null,
        newOrderCount: 0,
    },

    /**
     * Initialize the application.
     */
    init() {
        this.initTheme();
        this.initSidebar();
        this.initNotifications();
        this.initSearch();
        this.initAlertDismiss();

        // Load orders data if on dashboard or analytics page
        const mapEl = document.getElementById('map');
        if (mapEl) {
            this.loadOrders();
        }

        // Analytics page
        const analyticsEl = document.getElementById('analytics-page');
        if (analyticsEl) {
            this.loadOrders('analytics');
        }
    },

    // ==================
    // Theme Management
    // ==================

    initTheme() {
        document.documentElement.setAttribute('data-theme', this.state.theme);
        this.updateThemeIcon();

        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.toggleTheme());
        }
    },

    toggleTheme() {
        this.state.theme = this.state.theme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', this.state.theme);
        localStorage.setItem('woomap-theme', this.state.theme);
        this.updateThemeIcon();
    },

    updateThemeIcon() {
        const icon = document.querySelector('#theme-toggle i');
        if (icon) {
            icon.className = this.state.theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    },

    // ==================
    // Sidebar
    // ==================

    initSidebar() {
        const menuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
                this.state.sidebarOpen = sidebar.classList.contains('open');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                this.state.sidebarOpen = false;
            });
        }
    },

    // ==================
    // Notifications
    // ==================

    initNotifications() {
        const btn = document.getElementById('notif-btn');
        const panel = document.getElementById('notif-panel');
        const closeBtn = document.getElementById('notif-close');

        if (btn && panel) {
            btn.addEventListener('click', () => {
                panel.classList.toggle('open');
                this.state.notificationsOpen = panel.classList.contains('open');
                if (this.state.notificationsOpen) {
                    this.state.newOrderCount = 0;
                    this.updateNotifBadge();
                }
            });
        }

        if (closeBtn && panel) {
            closeBtn.addEventListener('click', () => {
                panel.classList.remove('open');
                this.state.notificationsOpen = false;
            });
        }

        // Start polling for new orders
        this.pollNotifications();
        setInterval(() => this.pollNotifications(), 60000); // Every 60 seconds
    },

    async pollNotifications() {
        try {
            const response = await this.api('/api/notifications');
            if (response.success && response.orders) {
                const panel = document.getElementById('notif-list');
                if (panel) {
                    if (response.orders.length === 0) {
                        panel.innerHTML = '<div class="empty-state" style="padding:30px"><i class="fas fa-bell-slash"></i><p>Aucune nouvelle commande</p></div>';
                    } else {
                        panel.innerHTML = response.orders.map(order => `
                            <div class="notif-item fade-in">
                                <div class="notif-item-title">Commande #${order.id}</div>
                                <div class="notif-item-text">${order.customer || 'Client inconnu'} - ${this.formatMoney(order.total)}</div>
                                <div class="notif-item-time">${this.timeAgo(order.date)}</div>
                            </div>
                        `).join('');
                    }

                    if (!this.state.notificationsOpen && this.state.lastNotifCheck) {
                        const newOrders = response.orders.filter(o =>
                            new Date(o.date) > new Date(this.state.lastNotifCheck)
                        );
                        this.state.newOrderCount += newOrders.length;
                        this.updateNotifBadge();
                    }
                }
                this.state.lastNotifCheck = new Date().toISOString();
            }
        } catch (e) {
            // Silent fail for notifications
        }
    },

    updateNotifBadge() {
        const badge = document.getElementById('notif-badge');
        if (badge) {
            badge.textContent = this.state.newOrderCount > 0 ? this.state.newOrderCount : '';
            badge.setAttribute('data-count', this.state.newOrderCount);
        }
    },

    // ==================
    // Search
    // ==================

    initSearch() {
        const searchInput = document.getElementById('global-search');
        if (!searchInput) return;

        let debounce;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                this.filterOrders(e.target.value);
            }, 300);
        });
    },

    filterOrders(query) {
        if (typeof WooMapMap !== 'undefined' && WooMapMap.filterBySearch) {
            WooMapMap.filterBySearch(query);
        }
    },

    // ==================
    // Data Loading
    // ==================

    async loadOrders(target = 'dashboard') {
        const dateFrom = document.getElementById('filter-date-from');
        const dateTo = document.getElementById('filter-date-to');

        const params = new URLSearchParams();
        if (dateFrom && dateFrom.value) params.set('after', dateFrom.value);
        if (dateTo && dateTo.value) params.set('before', dateTo.value);

        try {
            this.showLoading();
            const response = await this.api('/api/orders?' + params.toString());

            if (response.success) {
                this.state.orders = response.data.orders;
                this.state.stats = response.data.stats;

                if (target === 'dashboard') {
                    if (typeof WooMapMap !== 'undefined') {
                        WooMapMap.render(this.state.orders);
                    }
                    if (typeof WooMapCharts !== 'undefined') {
                        WooMapCharts.renderDashboard(this.state.stats);
                    }
                    this.renderStats(this.state.stats);
                    this.renderTopCities(this.state.stats.by_city || {});
                } else if (target === 'analytics') {
                    if (typeof WooMapCharts !== 'undefined') {
                        WooMapCharts.renderAnalytics(this.state.stats, this.state.orders);
                    }
                    this.renderStats(this.state.stats);
                }
            } else {
                this.showError(response.error || 'Erreur lors du chargement des commandes.');
            }
        } catch (e) {
            this.showError('Impossible de se connecter au serveur.');
        } finally {
            this.hideLoading();
        }
    },

    renderStats(stats) {
        const els = {
            'stat-total': stats.total,
            'stat-completed': stats.completed,
            'stat-processing': stats.processing,
            'stat-sales': this.formatMoney(stats.total_sales),
        };

        for (const [id, value] of Object.entries(els)) {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        }

        // Update filter counts
        document.querySelectorAll('.status-btn .count').forEach(el => {
            const status = el.closest('.status-btn').dataset.status;
            if (status === 'all') el.textContent = stats.total || 0;
            else if (stats[status] !== undefined) el.textContent = stats[status];
        });
    },

    renderTopCities(byCityData) {
        const container = document.getElementById('top-cities');
        if (!container) return;

        const cities = Object.entries(byCityData)
            .sort((a, b) => b[1].count - a[1].count)
            .slice(0, 8);

        if (cities.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>Aucune donnée de ville</p></div>';
            return;
        }

        const maxCount = cities[0][1].count;

        container.innerHTML = '<ul class="city-list">' + cities.map(([city, data]) => `
            <li class="city-item">
                <span class="city-name">${this.escapeHtml(city)}</span>
                <div class="city-bar">
                    <div class="city-bar-fill" style="width: ${(data.count / maxCount * 100)}%"></div>
                </div>
                <span class="city-count">${data.count} cmd</span>
            </li>
        `).join('') + '</ul>';
    },

    // ==================
    // Alert Dismiss
    // ==================

    initAlertDismiss() {
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    },

    // ==================
    // API Helper
    // ==================

    async api(url, options = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const defaults = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        };

        if (csrfToken) {
            defaults.headers['X-CSRF-Token'] = csrfToken;
        }

        const response = await fetch(url, { ...defaults, ...options });
        return response.json();
    },

    // ==================
    // Utilities
    // ==================

    formatMoney(amount, currency = 'FCFA') {
        return new Intl.NumberFormat('fr-FR').format(Math.round(amount)) + ' ' + currency;
    },

    timeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return "A l'instant";
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + ' h';
        return Math.floor(diff / 86400) + ' j';
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    showLoading() {
        let overlay = document.getElementById('loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    },

    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },

    showError(message) {
        const container = document.getElementById('alerts') || document.querySelector('.page-content');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = 'alert alert-error fade-in';
        alert.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${this.escapeHtml(message)}`;
        container.prepend(alert);

        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    },
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => WooMap.init());

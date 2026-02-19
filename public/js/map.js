/**
 * WooMap - Map Module
 * Interactive map using Leaflet + OpenStreetMap (no API key needed).
 * Supports markers, clustering, heatmap, and filtering.
 */

const WooMapMap = {
    map: null,
    markers: [],
    markerLayer: null,
    heatLayer: null,
    geocodeQueue: [],
    isProcessing: false,
    currentFilter: 'all',
    searchQuery: '',

    // Status colors for markers
    statusColors: {
        completed: '#16a34a',
        processing: '#d97706',
        cancelled: '#dc2626',
        pending: '#0284c7',
        default: '#6b7280',
    },

    /**
     * Initialize the Leaflet map.
     */
    init() {
        const mapEl = document.getElementById('map');
        if (!mapEl) return;

        // Default center: Senegal
        this.map = L.map('map', {
            center: [14.6937, -17.4441],
            zoom: 7,
            zoomControl: true,
            scrollWheelZoom: true,
        });

        // OpenStreetMap tile layer (free, no API key)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(this.map);

        // Marker layer group
        this.markerLayer = L.layerGroup().addTo(this.map);

        // Initialize filters
        this.initFilters();

        // Initialize heatmap toggle
        this.initHeatmapToggle();
    },

    /**
     * Render orders on the map.
     */
    async render(orders) {
        if (!this.map) this.init();

        this.clearMarkers();
        this.markers = [];

        // Build geocode requests
        const geocodeRequests = orders.map(order => ({
            order,
            address: order.address,
            city: order.city,
        }));

        // Batch geocode via API
        try {
            const addresses = [...new Set(geocodeRequests.map(r => r.address).filter(Boolean))];
            const cities = {};
            geocodeRequests.forEach(r => { if (r.address) cities[r.address] = r.city; });

            const response = await WooMap.api('/api/geocode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ addresses, cities }),
            });

            if (response.success) {
                const coords = response.coordinates;

                orders.forEach(order => {
                    const coord = coords[order.address];
                    if (coord && coord.lat && coord.lng) {
                        this.addMarker(order, coord.lat, coord.lng);
                    }
                });

                // Fit map bounds to markers
                if (this.markers.length > 0) {
                    const group = L.featureGroup(this.markers.map(m => m.leafletMarker));
                    this.map.fitBounds(group.getBounds().pad(0.1));
                }
            }
        } catch (e) {
            console.error('Geocoding error:', e);
        }
    },

    /**
     * Add a marker to the map.
     */
    addMarker(order, lat, lng) {
        const color = this.statusColors[order.status] || this.statusColors.default;

        // Custom circle marker
        const marker = L.circleMarker([lat, lng], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.85,
        });

        // Popup content
        const statusLabel = this.translateStatus(order.status);
        const popupContent = `
            <div style="min-width:200px;font-family:-apple-system,sans-serif;">
                <div style="font-weight:700;font-size:14px;margin-bottom:8px;color:#1e293b;">
                    Commande #${order.id}
                </div>
                <div style="font-size:12px;color:#475569;line-height:1.8;">
                    <div><strong>Client:</strong> ${this.escapeHtml(order.customer || 'N/A')}</div>
                    <div><strong>Adresse:</strong> ${this.escapeHtml(order.address || 'N/A')}</div>
                    <div><strong>Statut:</strong> <span style="color:${color};font-weight:600;">${statusLabel}</span></div>
                    <div><strong>Montant:</strong> ${WooMap.formatMoney(order.total)}</div>
                    <div><strong>Date:</strong> ${order.date ? new Date(order.date).toLocaleDateString('fr-FR') : 'N/A'}</div>
                </div>
                ${order.items && order.items.length > 0 ? `
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;">
                        <strong>Produits:</strong> ${order.items.map(i => i.name + ' x' + i.quantity).join(', ')}
                    </div>
                ` : ''}
            </div>
        `;

        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'woomap-popup',
        });

        marker.addTo(this.markerLayer);

        this.markers.push({
            leafletMarker: marker,
            order: order,
            lat: lat,
            lng: lng,
        });
    },

    /**
     * Clear all markers from the map.
     */
    clearMarkers() {
        if (this.markerLayer) {
            this.markerLayer.clearLayers();
        }
        this.markers = [];
    },

    // ==================
    // Filtering
    // ==================

    initFilters() {
        // Status filter buttons
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentFilter = btn.dataset.status;
                this.applyFilters();
            });
        });

        // Date filters
        const dateFrom = document.getElementById('filter-date-from');
        const dateTo = document.getElementById('filter-date-to');
        const applyBtn = document.getElementById('filter-apply');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                WooMap.loadOrders();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                const woo = WooMapMap.map ? WooMap : null;
                if (woo) {
                    fetch('/api/clear-cache', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        }
                    }).then(() => WooMap.loadOrders());
                }
            });
        }
    },

    applyFilters() {
        this.markers.forEach(m => {
            const matchStatus = this.currentFilter === 'all' || m.order.status === this.currentFilter;
            const matchSearch = !this.searchQuery || this.matchesSearch(m.order, this.searchQuery);

            if (matchStatus && matchSearch) {
                if (!this.markerLayer.hasLayer(m.leafletMarker)) {
                    m.leafletMarker.addTo(this.markerLayer);
                }
            } else {
                this.markerLayer.removeLayer(m.leafletMarker);
            }
        });
    },

    filterBySearch(query) {
        this.searchQuery = query.toLowerCase();
        this.applyFilters();
    },

    matchesSearch(order, query) {
        return (
            (order.customer && order.customer.toLowerCase().includes(query)) ||
            (order.city && order.city.toLowerCase().includes(query)) ||
            (order.address && order.address.toLowerCase().includes(query)) ||
            (order.email && order.email.toLowerCase().includes(query)) ||
            (order.phone && order.phone.includes(query)) ||
            (String(order.id).includes(query))
        );
    },

    // ==================
    // Heatmap
    // ==================

    initHeatmapToggle() {
        const toggle = document.getElementById('heatmap-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            if (toggle.classList.contains('active')) {
                this.showHeatmap();
            } else {
                this.hideHeatmap();
            }
        });
    },

    showHeatmap() {
        if (!window.L || !L.heatLayer) {
            console.warn('Leaflet.heat plugin not loaded');
            return;
        }

        const points = this.markers.map(m => [m.lat, m.lng, 0.5]);

        if (this.heatLayer) {
            this.map.removeLayer(this.heatLayer);
        }

        this.heatLayer = L.heatLayer(points, {
            radius: 25,
            blur: 15,
            maxZoom: 12,
            max: 1.0,
            gradient: {
                0.0: '#3b82f6',
                0.3: '#22c55e',
                0.6: '#eab308',
                1.0: '#ef4444',
            },
        }).addTo(this.map);

        // Hide markers when heatmap is on
        this.markerLayer.eachLayer(layer => layer.setStyle({ opacity: 0.2, fillOpacity: 0.2 }));

        const legend = document.getElementById('heatmap-legend');
        if (legend) legend.style.display = 'flex';
    },

    hideHeatmap() {
        if (this.heatLayer) {
            this.map.removeLayer(this.heatLayer);
            this.heatLayer = null;
        }

        // Restore markers
        this.markerLayer.eachLayer(layer => layer.setStyle({ opacity: 1, fillOpacity: 0.85 }));

        const legend = document.getElementById('heatmap-legend');
        if (legend) legend.style.display = 'none';
    },

    // ==================
    // Utilities
    // ==================

    translateStatus(status) {
        const map = {
            completed: 'Complétée',
            processing: 'En cours',
            cancelled: 'Annulée',
            pending: 'En attente',
            'on-hold': 'En pause',
            refunded: 'Remboursée',
            failed: 'Échouée',
        };
        return map[status] || status;
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    },
};

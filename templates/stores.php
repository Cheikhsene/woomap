<?php if (!defined('WOOMAP')) exit; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 class="page-title">Gestion des boutiques</h1>
        <p class="page-subtitle" style="margin-bottom:0;">Connectez et gérez plusieurs boutiques WooCommerce</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('add-store-modal').style.display='flex'">
        <i class="fas fa-plus"></i> Ajouter une boutique
    </button>
</div>

<!-- Stores List -->
<?php
$stores = Store::getAll();
$activeStore = Store::getActive();

if (empty($stores)):
?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-store" style="color:var(--text-muted);"></i>
            <h3>Aucune boutique connectée</h3>
            <p>Ajoutez votre première boutique WooCommerce pour commencer à visualiser vos commandes sur la carte.</p>
            <button class="btn btn-primary" style="margin-top:16px;" onclick="document.getElementById('add-store-modal').style.display='flex'">
                <i class="fas fa-plus"></i> Connecter une boutique
            </button>
        </div>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($stores as $store): ?>
    <div class="store-card <?= ($activeStore && $activeStore['id'] === $store['id']) ? 'active' : '' ?>">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;background:var(--accent-bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-store" style="font-size:20px;color:var(--accent);"></i>
            </div>
            <div class="store-info">
                <div class="store-name"><?= htmlspecialchars($store['name']) ?></div>
                <div class="store-url"><?= htmlspecialchars($store['url']) ?></div>
                <div class="store-meta">
                    <span><i class="fas fa-key"></i> <?= htmlspecialchars($store['auth_method'] === 'oauth' ? 'OAuth' : 'Clés API') ?></span>
                    <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($store['connected_at'] ?? 'N/A') ?></span>
                    <?php if ($activeStore && $activeStore['id'] === $store['id']): ?>
                        <span style="color:var(--success);font-weight:600;"><i class="fas fa-check-circle"></i> Active</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="store-actions">
            <?php if (!$activeStore || $activeStore['id'] !== $store['id']): ?>
            <form method="POST" action="/stores/switch" style="display:inline;">
                <?= CSRF::field() ?>
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($store['id']) ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-exchange-alt"></i> Activer
                </button>
            </form>
            <?php endif; ?>
            <form method="POST" action="/stores/delete" style="display:inline;"
                  onsubmit="return confirm('Supprimer cette boutique ?')">
                <?= CSRF::field() ?>
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($store['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Store Modal -->
<div id="add-store-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;padding:20px;">
    <div class="card" style="width:100%;max-width:500px;">
        <div class="card-header">
            <span class="card-title">Ajouter une boutique</span>
            <button onclick="document.getElementById('add-store-modal').style.display='none'"
                    style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form method="POST" action="/stores/add">
                <?= CSRF::field() ?>

                <div class="form-group">
                    <label>URL de la boutique</label>
                    <input type="url" name="site_url" class="form-control"
                           placeholder="https://votre-boutique.com" required>
                </div>

                <div class="form-group">
                    <label>Consumer Key</label>
                    <input type="text" name="consumer_key" class="form-control"
                           placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                </div>

                <div class="form-group">
                    <label>Consumer Secret</label>
                    <input type="password" name="consumer_secret" class="form-control"
                           placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('add-store-modal').style.display='none'">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-link"></i> Connecter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

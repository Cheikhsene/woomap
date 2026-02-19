<?php if (!defined('WOOMAP')) exit; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- App CSS -->
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h2><?= APP_NAME ?></h2>
                <p>Connectez votre boutique WooCommerce</p>
            </div>

            <!-- Flash Messages -->
            <?php $flashes = Session::getFlash(); if (!empty($flashes)): ?>
                <div style="padding:0 32px;">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                            <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Auth Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="app-password">
                    <i class="fas fa-key"></i> Clés API
                </button>
                <button class="auth-tab" data-tab="oauth">
                    <i class="fas fa-plug"></i> OAuth
                </button>
            </div>

            <!-- Application Password / API Keys Panel -->
            <div class="auth-panel active" id="tab-app-password">
                <form method="POST" action="/login">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="auth_method" value="app_password">

                    <div class="form-group">
                        <label for="site_url">URL de votre boutique</label>
                        <input type="url" id="site_url" name="site_url" class="form-control"
                               placeholder="https://votre-boutique.com"
                               value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>"
                               required>
                        <div class="form-help">L'URL complète de votre site WordPress/WooCommerce</div>
                    </div>

                    <div class="form-group">
                        <label for="consumer_key">Consumer Key</label>
                        <input type="text" id="consumer_key" name="consumer_key" class="form-control"
                               placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                               value="<?= htmlspecialchars($_POST['consumer_key'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="consumer_secret">Consumer Secret</label>
                        <input type="password" id="consumer_secret" name="consumer_secret" class="form-control"
                               placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                               required>
                        <div class="form-help">
                            Générez vos clés dans WooCommerce &rarr; Réglages &rarr; Avancé &rarr; API REST
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                        <i class="fas fa-link"></i> Connecter la boutique
                    </button>
                </form>
            </div>

            <!-- OAuth Panel -->
            <div class="auth-panel" id="tab-oauth">
                <form method="POST" action="/oauth/start">
                    <?= CSRF::field() ?>

                    <div style="background:var(--info-bg);color:var(--info);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:20px;font-size:13px;">
                        <i class="fas fa-info-circle"></i>
                        OAuth vous redirigera vers votre boutique pour autoriser l'accès. Les clés API seront générées automatiquement.
                    </div>

                    <div class="form-group">
                        <label for="oauth_site_url">URL de votre boutique</label>
                        <input type="url" id="oauth_site_url" name="site_url" class="form-control"
                               placeholder="https://votre-boutique.com"
                               required>
                        <div class="form-help">Votre boutique doit avoir WooCommerce activé avec l'API REST</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                        <i class="fas fa-external-link-alt"></i> Autoriser via OAuth
                    </button>
                </form>
            </div>

            <div style="text-align:center;padding:0 32px 24px;font-size:12px;color:var(--text-muted);">
                <i class="fas fa-lock" style="margin-right:4px;"></i>
                Connexion sécurisée - Vos identifiants sont chiffrés
            </div>
        </div>
    </div>

    <script>
    // Tab switching
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });
    </script>
</body>
</html>

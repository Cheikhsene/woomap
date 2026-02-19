# WooMap v2.0

Dashboard cartographique pour WooCommerce. Visualisez vos commandes sur une carte interactive, analysez vos ventes par ville et exportez vos rapports.

## Fonctionnalites

- **Carte interactive** (Leaflet + OpenStreetMap) - aucune cle API requise
- **Authentification securisee** - Cles API WooCommerce ou OAuth
- **Multi-boutique** - connectez et gerez plusieurs boutiques WooCommerce
- **Dashboard analytique** - ventes par region, top villes, tendances mensuelles
- **Heatmap** - carte de chaleur des zones de vente
- **Export CSV / PDF** - exportez vos donnees et rapports
- **Recherche avancee** - par client, produit, ville, montant
- **Notifications** - alertes en temps reel sur les nouvelles commandes
- **Mode sombre** - interface moderne avec dark mode
- **Responsive** - fonctionne sur mobile, tablette et desktop
- **Cache intelligent** - cache des appels API et du geocoding
- **Securite** - CSRF, sessions chiffrees, headers de securite

## Prerequis

- PHP 7.4+ avec extensions `curl`, `json`, `openssl`
- Serveur web Apache (avec `mod_rewrite`) ou Nginx
- Boutique WooCommerce avec l'API REST activee

## Installation

1. Clonez le depot :

```bash
git clone https://github.com/Cheikhsene/woomap.git
cd woomap
```

2. Copiez le fichier de configuration :

```bash
cp .env.example .env
```

3. Editez `.env` avec vos parametres :

```ini
APP_URL=https://votre-domaine.com
APP_SECRET=VOTRE_CLE_SECRETE_ALEATOIRE_DE_32_CARACTERES
```

Pour generer une cle secrete :

```bash
php -r "echo bin2hex(random_bytes(16));"
```

4. Assurez-vous que le dossier `data/` est accessible en ecriture :

```bash
chmod -R 755 data/
```

5. Configurez votre serveur web pour pointer vers le dossier du projet.

6. Ouvrez votre navigateur et connectez-vous avec vos cles API WooCommerce.

### Configuration Nginx

```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /chemin/vers/woomap;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?route=$uri&$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ ^/(data|src|templates)/ {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

## Methodes d'authentification

### Cles API WooCommerce (recommande)

1. Dans votre admin WordPress, allez dans **WooCommerce > Reglages > Avance > API REST**
2. Cliquez sur **Ajouter une cle**
3. Donnez un nom, selectionnez un utilisateur et les permissions **Lecture**
4. Copiez la Consumer Key et le Consumer Secret
5. Entrez ces cles dans WooMap

### OAuth WooCommerce

1. Entrez simplement l'URL de votre boutique
2. Vous serez redirige vers votre boutique pour autoriser l'acces
3. Les cles API sont generees automatiquement

## Architecture

```
woomap/
├── index.php              # Point d'entree + routeur
├── config.php             # Configuration + chargement .env
├── .env                   # Variables d'environnement (gitignore)
├── .htaccess              # Rewrite + securite Apache
│
├── src/                   # Classes PHP
│   ├── Auth.php           # Authentification (API keys + OAuth)
│   ├── Cache.php          # Systeme de cache fichier
│   ├── CSRF.php           # Protection CSRF
│   ├── Export.php         # Export CSV et PDF
│   ├── Geocoder.php       # Geocoding Nominatim/OSM
│   ├── Session.php        # Sessions securisees
│   ├── Store.php          # Gestion multi-boutique
│   └── WooCommerce.php    # Client API WooCommerce
│
├── templates/             # Templates PHP
│   ├── layout.php         # Layout principal (sidebar + header)
│   ├── login.php          # Page de connexion
│   ├── dashboard.php      # Dashboard avec carte
│   ├── analytics.php      # Page analytique
│   └── stores.php         # Gestion des boutiques
│
├── public/                # Assets statiques
│   ├── css/app.css        # Styles (variables CSS, dark mode)
│   └── js/
│       ├── app.js         # App principale (theme, notifs, state)
│       ├── map.js         # Carte Leaflet + marqueurs + heatmap
│       └── charts.js      # Graphiques Chart.js
│
└── data/                  # Donnees runtime (gitignore)
    ├── cache/             # Cache API
    ├── geocode/           # Cache geocoding
    └── stores/            # Configs boutiques chiffrees
```

## Securite

- Credentials stockes chiffres (AES-256-CBC) en session
- Protection CSRF sur tous les formulaires et API
- Headers de securite (CSP, X-Frame-Options, X-XSS-Protection)
- Dossiers sensibles bloques par .htaccess
- Sessions securisees avec regeneration d'ID
- Validation et sanitization des entrees

## Licence

MIT - Voir le fichier [LICENSE](LICENSE) pour plus de details.

---

Cree par [CheikhSene](https://github.com/Cheikhsene)

# 🗺️ Carte des Commandes WooCommerce Sénégal

## 📋 Description
Ce projet est une application web qui affiche une carte interactive des commandes WooCommerce au Sénégal. Elle permet de visualiser et de filtrer les commandes par statut et par date, offrant ainsi une vue d'ensemble claire de l'activité commerciale.

## ✨ Fonctionnalités

- 🌍 Carte interactive du Sénégal avec géolocalisation des commandes
- 🔍 Filtrage des commandes par statut (terminé, en cours, annulé)
- 📅 Filtrage par période
- 📊 Graphique des statuts de commande
- 💰 Affichage du total des ventes pour les commandes terminées

## 🛠️ Prérequis

- PHP 7.4 ou supérieur
- Serveur web (Apache, Nginx, etc.)
- Compte WooCommerce avec clés API
- Clé API Google Maps

## 🚀 Installation

1. **Cloner le dépôt**
   ```
   git clone https://github.com/votre-nom-utilisateur/woocommerce-senegal-map.git
   ```

2. **Configurer les clés API**
   Ouvrez le fichier `index.php` et remplacez les valeurs suivantes :
   ```php
   define('WOOCOMMERCE_CONSUMER_KEY', 'votre_cle_consommateur');
   define('WOOCOMMERCE_CONSUMER_SECRET', 'votre_cle_secrete');
   define('GOOGLE_MAPS_API_KEY', 'votre_cle_api_google_maps');
   ```

3. **Configurer l'URL de votre boutique WooCommerce**
   Dans le même fichier, mettez à jour l'URL de votre boutique :
   ```php
   define('WOOCOMMERCE_STORE_URL', 'https://votre-boutique.com/');
   ```

4. **Déployer sur votre serveur web**
   Uploadez tous les fichiers sur votre serveur web.

5. **Accéder à l'application**
   Ouvrez votre navigateur et accédez à l'URL où vous avez déployé l'application.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou à soumettre une pull request.

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

---

Créé avec ❤️ par CheikhSene

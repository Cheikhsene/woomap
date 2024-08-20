<?php
// Configuration
define('WOOCOMMERCE_STORE_URL', 'https://votre-site-woocommerce.com/');
define('WOOCOMMERCE_CONSUMER_KEY', 'votre_cle_consommateur_woocommerce');
define('WOOCOMMERCE_CONSUMER_SECRET', 'votre_cle_secrete_woocommerce');
define('GOOGLE_MAPS_API_KEY', 'votre_cle_api_google_maps');

// Fonction pour récupérer les commandes 
function obtenirToutesLesCommandesWooCommerce() {
    $page = 1;
    $per_page = 100;
    $toutes_les_commandes = [];

    while (true) {
        $url = WOOCOMMERCE_STORE_URL . "wp-json/wc/v3/orders?page=$page&per_page=$per_page";
        $auth = base64_encode(WOOCOMMERCE_CONSUMER_KEY . ':' . WOOCOMMERCE_CONSUMER_SECRET);

        $reponse = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Basic " . $auth . "\r\n"
            ]
        ]));

        if ($reponse === FALSE) {
            break;
        }

        $commandes = json_decode($reponse);

        if (empty($commandes)) {
            break;
        }

        $toutes_les_commandes = array_merge($toutes_les_commandes, $commandes);
        $page++;
    }

    return $toutes_les_commandes;
}

// Pour traiter les données des commandes
function traiterDonneesCommandes() {
    $commandes = obtenirToutesLesCommandesWooCommerce();
    $emplacements = [];
    $statistiques = [
        'termine' => 0,
        'en_cours' => 0,
        'annule' => 0,
        'autre' => 0,
        'total_ventes' => 0
    ];

    foreach ($commandes as $commande) {
        $adresse = $commande->billing->address_1 . ', ' . $commande->billing->city . ', Sénégal';
        $statut = $commande->status;
        $montant = $commande->total;

        // Mise à jour des statistiques
        switch ($statut) {
            case 'completed':
                $statistiques['termine']++;
                $statistiques['total_ventes'] += floatval($montant);
                break;
            case 'processing':
                $statistiques['en_cours']++;
                break;
            case 'cancelled':
                $statistiques['annule']++;
                break;
            default:
                $statistiques['autre']++;
        }

        $emplacements[] = [
            'adresse' => $adresse,
            'statut' => $statut,
            'date' => $commande->date_created,
            'id_commande' => $commande->id,
            'montant' => $montant
        ];
    }

    return ['emplacements' => $emplacements, 'statistiques' => $statistiques];
}

$donnees_commandes = traiterDonneesCommandes();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Clients Sénégal</title>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        #map { height: 600px; }
        .stats-box {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Carte Clients Sénégal</h1>
        
        <div class="mb-4 flex space-x-4 items-center">
            <button id="filtre-tous" class="p-2 bg-blue-500 text-white rounded">
                <i class="fas fa-globe"></i> Tous
            </button>
            <button id="filtre-termine" class="p-2 bg-green-500 text-white rounded">
                <i class="fas fa-check-circle"></i> Terminé
            </button>
            <button id="filtre-en-cours" class="p-2 bg-yellow-500 text-white rounded">
                <i class="fas fa-clock"></i> En cours
            </button>
            <button id="filtre-annule" class="p-2 bg-red-500 text-white rounded">
                <i class="fas fa-times-circle"></i> Annulé
            </button>
            <div>
                <label for="date-debut" class="mr-2">Du:</label>
                <input type="date" id="date-debut" class="p-2 border rounded">
            </div>
            <div>
                <label for="date-fin" class="mr-2">Au:</label>
                <input type="date" id="date-fin" class="p-2 border rounded">
            </div>
            <button id="appliquer-filtres" class="p-2 bg-purple-500 text-white rounded">
                <i class="fas fa-filter"></i> Appliquer les filtres
            </button>
        </div>

        <div id="map" class="w-full bg-white shadow-lg rounded-lg"></div>

        <div class="stats-box">
            <h2 class="text-lg font-bold mb-2">Statistiques</h2>
            <canvas id="statut-chart" width="200" height="200"></canvas>
            <p class="mt-2">Total des ventes terminées: <span id="total-ventes"></span> FCFA</p>
        </div>
    </div>

    <script>
    const donneesCommandes = <?php echo json_encode($donnees_commandes['emplacements']); ?>;
    const statistiques = <?php echo json_encode($donnees_commandes['statistiques']); ?>;
    let carte;
    let marqueurs = [];

    function initialiserCarte() {
        carte = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 14.6937, lng: -17.4441}, // Centre du Sénégal
            zoom: 7
        });

        const geocoder = new google.maps.Geocoder();
        
        donneesCommandes.forEach(commande => {
            geocoder.geocode({ address: commande.adresse }, (resultats, statut) => {
                if (statut === 'OK' && resultats[0]) {
                    const marqueur = new google.maps.Marker({
                        map: carte,
                        position: resultats[0].geometry.location,
                        title: commande.adresse
                    });

                    const infoWindow = new google.maps.InfoWindow({
                        content: `<h3>Commande: ${commande.id_commande}</h3><p>Adresse: ${commande.adresse}</p><p>Statut: ${commande.statut}</p><p>Date: ${commande.date}</p><p>Montant: ${commande.montant} FCFA</p>`
                    });

                    marqueur.addListener('click', () => {
                        infoWindow.open(carte, marqueur);
                    });

                    marqueurs.push({ marqueur, commande });
                }
            });
        });
    }

    function filtrerMarqueurs(statut = 'tous') {
        const dateDebut = new Date(document.getElementById('date-debut').value);
        const dateFin = new Date(document.getElementById('date-fin').value);

        marqueurs.forEach(({ marqueur, commande }) => {
            const dateCommande = new Date(commande.date);
            const statutCorrespond = statut === 'tous' || commande.statut === statut;
            const dateCorrespond = (isNaN(dateDebut) || dateCommande >= dateDebut) && 
                                   (isNaN(dateFin) || dateCommande <= dateFin);

            if (statutCorrespond && dateCorrespond) {
                marqueur.setMap(carte);
            } else {
                marqueur.setMap(null);
            }
        });
    }

    function initialiserGraphique() {
        const ctx = document.getElementById('statut-chart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Terminé', 'En cours', 'Annulé', 'Autre'],
                datasets: [{
                    data: [
                        statistiques.termine,
                        statistiques.en_cours,
                        statistiques.annule,
                        statistiques.autre
                    ],
                    backgroundColor: ['#4CAF50', '#FFA500', '#F44336', '#9E9E9E']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        document.getElementById('total-ventes').textContent = statistiques.total_ventes.toLocaleString('fr-FR');
    }

    document.getElementById('filtre-tous').addEventListener('click', () => filtrerMarqueurs('tous'));
    document.getElementById('filtre-termine').addEventListener('click', () => filtrerMarqueurs('completed'));
    document.getElementById('filtre-en-cours').addEventListener('click', () => filtrerMarqueurs('processing'));
    document.getElementById('filtre-annule').addEventListener('click', () => filtrerMarqueurs('cancelled'));
    document.getElementById('appliquer-filtres').addEventListener('click', () => filtrerMarqueurs('tous'));

    document.addEventListener('DOMContentLoaded', () => {
        initialiserCarte();
        initialiserGraphique();
    });
    </script>
</body>
</html>
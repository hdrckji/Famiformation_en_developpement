<?php
// Affiche toutes les erreurs PHP à l'écran pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
if (!isset($_SESSION['username'])) {
    // Redirige vers la page de connexion si l'utilisateur n'est pas connecté
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Enregistrer présence</title>
    <script>
    // Coordonnées du point central (en décimal)
    const CENTRE_LAT = 50.719083;
    const CENTRE_LON = 3.283350;
    const RAYON_METRES = 50;

    // Formule de Haversine pour calculer la distance en mètres
    function distanceMetres(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Rayon de la Terre en mètres
        const toRad = deg => deg * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function enregistrerPresence() {
        if (!navigator.geolocation) {
            alert('La géolocalisation n\'est pas supportée par ce navigateur.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            const dist = distanceMetres(lat, lon, CENTRE_LAT, CENTRE_LON);
            if (dist > RAYON_METRES) {
                alert("Vous êtes trop loin du point autorisé (" + dist.toFixed(1) + " m > 50 m). Présence non enregistrée.");
                return;
            }
            const data = {
                latitude: lat,
                longitude: lon
            };
            fetch('save_presence.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(async response => {
                let text = await response.text();
                try {
                    let json = JSON.parse(text);
                    alert('Réponse serveur : ' + JSON.stringify(json));
                } catch (e) {
                    alert('Réponse brute : ' + text);
                }
            })
            .catch(error => {
                alert('Erreur JS : ' + error);
            });
        }, function(error) {
            alert('Impossible d\'obtenir la position.');
        });
    }
    </script>
</head>
<body>
    <h1>Enregistrer ma présence</h1>
    <button id="btnPresence" onclick="enregistrerPresence()">...</button>
    <script>
    let nbPointages = 0;
    let texteBoutons = ["arrivé", "départ"];
    // Récupère le nombre de pointages du jour pour l'utilisateur
    function majBoutonPresence() {
        fetch('get_presence_status.php')
            .then(r => r.json())
            .then(data => {
                nbPointages = data.count || 0;
                let idx = nbPointages % 2;
                document.getElementById('btnPresence').textContent = texteBoutons[idx];
            });
    }
    // Après chaque enregistrement, on met à jour le texte du bouton
    const oldEnregistrerPresence = enregistrerPresence;
    enregistrerPresence = function() {
        oldEnregistrerPresence();
        setTimeout(majBoutonPresence, 500); // Décalage pour laisser le temps à l'enregistrement
    }
    // Initialisation au chargement
    majBoutonPresence();
    </script>
</body>
</html>

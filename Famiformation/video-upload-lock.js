/*
 * video-upload-lock.js — Blocage de l'avance sur les vidéos uploadées (HTML5 <video>).
 *
 * Le retour en arrière reste possible, mais tout saut vers l'avant au-delà
 * de la partie déjà regardée est annulé (la vidéo revient au point atteint).
 */
(function () {
    "use strict";

    function lock(v) {
        var maxTime = 0;

        // Suit le point le plus loin réellement regardé (hors saut)
        v.addEventListener('timeupdate', function () {
            if (!v.seeking && v.currentTime > maxTime) {
                maxTime = v.currentTime;
            }
        });

        // Annule tout saut vers l'avant
        v.addEventListener('seeking', function () {
            if (v.currentTime > maxTime + 0.5) {
                v.currentTime = maxTime;
            }
        });
    }

    function init() {
        var vids = document.querySelectorAll('video');
        for (var i = 0; i < vids.length; i++) {
            lock(vids[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

<?php
// ============================================================
// FAMICARD — DÉCONNEXION.
//
// Deux raisons d'avoir cette page ici plutôt que de renvoyer vers celle du site :
//   • sur famicard.famiformation.com, « /logout.php » est réécrit vers
//     famicard/ — sans ce fichier, le lien tombe sur une 404 ;
//   • le ruban injecté par config.php pointe vers « logout.php » en relatif,
//     donc vers /famicard/logout.php dès qu'on est dans ce dossier, y compris
//     sur le domaine principal.
//
// La session étant partagée avec le site (mêmes clés, même cookie sur www), se
// déconnecter ici déconnecte aussi de FamiFormation. C'est voulu : une seule
// session, une seule déconnexion — l'inverse serait un piège.
// ============================================================
require_once __DIR__ . '/config.php';

session_unset();
session_destroy();

header('Location: login.php');
exit();

<?php
// ============================================================
// mes_horaires_attribues.php — REDIRECTION, plus une page.
//
// ⚠️ POURQUOI CE FICHIER NE FAIT PLUS RIEN.
// « Mes horaires attribués » existait EN DOUBLE : cette page (trois listes
// empilées — passés / aujourd'hui / à venir) et mon_horaire.php sur le site
// (la semaine en grille, 7 jours en colonnes). Deux pages, un seul sujet.
//
// La grille est celle qui a été demandée et corrigée. Mais l'accueil FamiJob
// pointait ici, si bien que l'ancienne vue revenait a chaque fois qu'un
// etudiant passait par FamiJob — et ressemblait a un retour en arriere alors
// que rien n'avait ete annule.
//
// Une seule page desormais. Ce fichier reste comme porte d'entree pour les
// liens et les favoris deja en circulation : il redirige, il n'affiche pas.
// La supprimer casserait ces liens sans rien resoudre de plus.
// ============================================================

require_once 'config.php';
verifierConnexion($db);

header('Location: ' . famijobSiteUrl('mon_horaire.php'));
exit();

<?php
// ============================================================
// La Panne — ANCIENNE ADRESSE DE LA PAGE RH.
//
// La page vit désormais à /emails/lapanne/ : les inscriptions se font par le
// quiz (borne et téléphone), l'ancien formulaire de saisie n'avait plus d'objet
// et la liste RH a pris sa place.
//
// Ce fichier ne fait plus que rediriger. Il reste là parce que l'adresse
// /emails/lapanne/rh a pu être communiquée au personnel, mise en favori ou
// imprimée : une page « introuvable » le jour de l'événement serait pénible à
// expliquer. Redirection 301 — permanente, c'est bien un déménagement.
// ============================================================

header('Location: /emails/lapanne/', true, 301);
exit();

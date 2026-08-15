<?php
// ============================================================
// validation_demandes_horaires.php — REDIRECTION, plus une page.
//
// ⚠️ CE FICHIER ETAIT UN JUMEAU PERIME. La meme page existait ici et dans
// FamiJob. Celle de FamiJob a continue d'evoluer — filtres par secteur,
// classeur qui tient dans la page, export, confirmation avant d'affecter,
// masquage des noms entre agences — celle-ci est restee au 28 juillet.
//
// Personne ne s'en apercevait, parce que les deux menaient au meme travail :
// selon la porte empruntee, on tombait sur la version d'aujourd'hui ou sur
// celle d'il y a trois semaines. C'est ce qui a fait dire « les agences ont
// l'export Excel, les admins ne l'ont pas » : les agences arrivaient par
// FamiJob, les admins par la tuile du site.
//
// Ce fichier redirige desormais vers l'unique version. Il reste en place
// parce que la liste des modules vit en BASE : le lien enregistre pointe
// toujours ici, et le supprimer casserait la tuile sans rien resoudre.
//
// La semaine et les filtres suivent : arriver sur la bonne page mais sur la
// mauvaise semaine, c'est corriger a moitie.
// ============================================================

require_once 'config.php';

$cible = 'famijob/validation_demandes_horaires.php';
$requete = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($requete !== '') {
    $cible .= '?' . $requete;
}

header('Location: ' . $cible);
exit();

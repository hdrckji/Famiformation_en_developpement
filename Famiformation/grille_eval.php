<?php $examinateurIndex = isset($examinateurIndex) ? (int) $examinateurIndex : 1; ?>
<h2 style="background:#8fd34f;padding:8px 0;border-radius:8px;text-align:center;">Attitude</h2>
<table style="width:100%;margin-bottom:24px;border-collapse:collapse;">
    <tr style="background:#8fd34f;">
        <th></th>
        <th>😡</th>
        <th>😐</th>
        <th>🙂</th>
        <th>😀</th>
    </tr>
    <?php
    $attitude = [
        "Présentation soignée",
        "Agréable et souriant avec les clients",
        "Politesse",
        "Acceptation et prise en compte des remarques",
        "Intérêt pour le poste",
        "Niveau de confort",
        "Motivation"
    ];
    foreach ($attitude as $crit) {
        echo "<tr><td>".htmlspecialchars($crit)."</td>";
        foreach (['red','yellow','lightgreen','green'] as $val) {
            echo "<td><input type='radio' name='attitude".$examinateurIndex."[".htmlspecialchars($crit, ENT_QUOTES, 'UTF-8')."]' value='$val'></td>";
        }
        echo "</tr>";
    }
    ?>
</table>

<h2 style="background:#8fd34f;padding:8px 0;border-radius:8px;text-align:center;">Travail</h2>
<table style="width:100%;margin-bottom:24px;border-collapse:collapse;">
    <tr style="background:#8fd34f;">
        <th></th>
        <th>😡</th>
        <th>😐</th>
        <th>🙂</th>
        <th>😀</th>
    </tr>
    <?php
    $travail = [
        "Regarde l'écran de caisse",
        "Demande de la carte de fidélité, code postal",
        "Demande de déposer les articles sur le tapis",
        "Vérifier les codes rentrer manuellement",
        "Attention au caddies et bas des poussettes",
        "Comptage des articles",
        "Travail en cash"
    ];
    foreach ($travail as $crit) {
        echo "<tr><td>".htmlspecialchars($crit)."</td>";
        foreach (['red','yellow','lightgreen','green'] as $val) {
            echo "<td><input type='radio' name='travail".$examinateurIndex."[".htmlspecialchars($crit, ENT_QUOTES, 'UTF-8')."]' value='$val'></td>";
        }
        echo "</tr>";
    }
    ?>
</table>

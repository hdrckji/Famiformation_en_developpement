# Famicard

La **carte d'identité du collaborateur Famiflora**, et le **point d'entrée** des
plateformes de la maison — FamiFormation, FamiJob, et les suivantes.

Le sens de lecture compte : Famicard n'est pas une page de FamiFormation. C'est
l'inverse. `index.php` est un accueil de portail à quatre tuiles — Ma fiche, Mes
collaborateurs, FamiFormation, FamiJob — et aucune page n'affiche de « ← retour à
FamiFormation », qui dirait au collaborateur qu'il est dans une annexe.

⚠️ Les tuiles d'accès **reflètent** les règles déjà en place sur l'accueil du site
(FamiJob : admin et teamcoach). On n'ouvre pas un accès depuis ici : une tuile qui
mène à un refus est pire que pas de tuile.

## LE TRI — qui possède quoi

Décision de Jimmy. **Famicard possède la personne. FamiFormation possède la formation.**
Tout se déduit de cette phrase, et c'est le seul critère à appliquer devant un écran
nouveau : est-ce que ça parle du collaborateur, ou de ce qu'il vient y faire ?

### Ce qui appartient à Famicard

- **L'identité** — fiche, champs, photo, badge, export, libellés.
- **Le compte** — création d'un utilisateur, identifiant, mot de passe, activation,
  relance, désactivation, suppression.
- **Le rattachement** — secteur, département, lieu de travail, agence.
- **Les accès** — à quels services ce collaborateur a droit (voir plus bas).
- **La traçabilité** — qui a changé quoi, quand ; validations ; RGPD.

**C'est depuis Famicard qu'on crée un utilisateur.** Pas ailleurs, et à terme nulle part
ailleurs.

### Ce qui appartient à FamiFormation

- Les **modules de formation**, le guide, les vidéos, les quiz et leurs résultats.
- L'**onboarding**, le planning des formations, les évaluations.
- Le **jardin**, le classement, les jeux.

Autrement dit : ce qu'un collaborateur *fait* sur la plateforme, pas ce qu'il *est*.

### Ce qui appartient à FamiJob

Les horaires, les disponibilités, le matching intérim — et la table `departments`, que
les secteurs ÉTENDENT (`sector_id`) au lieu d'en créer une seconde.

**FamiJob est un outil de PLANIFICATION**, et c'est ce qui décide du sens de sa table
`student_department_links` : elle répond à « **où cet étudiant peut-il être placé** »,
avec plusieurs rayons classés par préférence. C'est un vivier de candidats.

⚠️ **Famicard a besoin des mêmes secteurs pour une autre question** — « de quoi cette
personne relève-t-elle » — et c'est un fait différent sur la même personne. Voir
« Deux rattachements » ci-dessous : les confondre fabrique des données fausses dans les
deux sens.

### Les cas frontière, tranchés

| Sujet | Où | Pourquoi |
|---|---|---|
| Rôle / profil | **Famicard** | C'est une propriété de la personne, pas d'un service |
| Statut actif/inactif | **Famicard** | Idem — et il conditionne tous les accès |
| Tri des profils (beta → employé) | **Famicard** | Ça change le profil d'une personne |
| Relance de mot de passe | **Famicard** | Ça touche l'accès d'une personne |
| Progression aux quiz | FamiFormation | C'est un résultat, pas une identité |
| Disponibilités, horaires | FamiJob | Idem |

### État de la bascule

| Sujet | Aujourd'hui | Cible |
|---|---|---|
| Fiche, champs, photo, badge, export | **Famicard** | Famicard |
| Secteur / département | **Famicard** | Famicard |
| Accès aux services | **Famicard** | Famicard |
| Tri des profils, relance mot de passe | **Famicard** | Famicard |
| Création de compte | **Famicard** (`creer.php`) — le site en a encore une | Famicard |
| Mot de passe, activation | les deux | **Famicard** |
| Rôle, statut, agence, lieu de travail | les deux | **Famicard** |
| Employeur, type de contrat | **Famicard** | Famicard |

⚠️ Tant que la bascule n'est pas faite, **ne pas faire écrire la même colonne aux deux
endroits**. Deux écrans qui écrivent l'email finissent toujours par diverger, et c'est
celui qu'on regarde le moins qui gagne.

⚠️ **Les secteurs viennent du dépôt LIVE** (`Famiformation/includes/secteurs.php`, tables
`sectors` et `departments.sector_id`). Famicard avait développé sa propre implantation en
parallèle, sans savoir que le live en avait déjà une : elle a été abandonnée. Deux
systèmes pour la même chose, c'est celui qui ne tourne pas qui gagne les données perdues.

---

## DEUX RATTACHEMENTS — et c'est la confusion la plus coûteuse du dépôt

Les deux plateformes ont besoin des secteurs et des départements, **pour deux raisons
différentes**. FamiJob est un outil de **planification** : il lui faut savoir où placer
quelqu'un. Famicard est un outil **RH** : il lui faut savoir de quoi une personne relève.

| | `student_department_links` | `famicard_rattachement` |
|---|---|---|
| Question | **Où peut-il être placé ?** | **De quoi relève-t-elle ?** |
| Propriétaire | FamiJob (planification) | Famicard (RH) |
| Combien | plusieurs, ordonnés par préférence | **un seul** |
| `priority_rank` | ordre de placement | n'existe pas, n'aurait aucun sens |
| Pour qui | les gens qu'on planifie | **tout le monde**, teamcoachs compris |

Un étudiant qui peut travailler dans trois rayons **ne relève pas de trois départements**
— il peut y être placé. Écrire l'un dans la table de l'autre donne, dans un sens, un
teamcoach qui ressemble à un candidat à planifier, et dans l'autre, un étudiant qui paraît
appartenir à trois rayons.

**Le référentiel, lui, reste unique** : `sectors` et `departments`, ceux du dépôt live.
C'est CE point que le README protège depuis le début (Famicard avait créé ses propres
tables de secteurs, abandonnées). Deux **liens** de sens différent vers **un seul
référentiel**, ce n'est pas la même erreur : c'est la façon d'éviter la première.

### La forme : secteur, et département facultatif

Décision de Jimmy, et elle vient d'un cas réel : **« Décoration » est un secteur** (15
départements), **« Caisse » est un département** d'un autre secteur. Un teamcoach
Décoration couvre donc un secteur entier ; lui faire cocher ses 15 rayons serait faux dès
le rayon suivant. Un employé de caisse, lui, relève d'un département précis.

```
département renseigné  →  son périmètre est CE département
département vide       →  son périmètre est TOUT le secteur
```

`famicardPerimetreRh()` rend cette liste de départements. ⚠️ Il rend **`null`** quand rien
n'est renseigné, jamais un tableau vide : vide voudrait dire « ne voit rien », et un écran
qui confondrait les deux se viderait pour tout le monde le jour de la mise en service.

### À quoi ça servira

À restreindre ce qu'une personne **voit**, sans toucher à ce qu'elle a le **droit de
faire** : « un teamcoach Décoration ne voit pas les horaires de la caisse ». Le couple est
`role` + périmètre — le rôle dit ce qu'on peut faire, le rattachement dit sur quoi. Un
teamcoach reste un teamcoach, il en voit simplement moins.

⚠️ **Rien n'est filtré aujourd'hui.** Famicard enregistre le périmètre et sait le lire.
Brancher un filtrage est une décision de l'écran concerné — et un filtrage posé avant que
les fiches soient renseignées viderait les écrans de tout le monde.

---

## EMPLOYEUR, CONTRAT, PROFIL — trois questions, jamais une seule colonne

Le point de départ, décidé par Jimmy : **Famiflora n'est pas une agence, c'est
l'entreprise qui recrute.** Qui travaille pour elle est *interne*, qui vient d'une
agence est *intérimaire*, et il y a aussi des *indépendants*. Chacun d'eux, en plus,
est étudiant, flexi ou fixe. Et par-dessus, il y a les profils.

Une seule colonne, `interim`, portait tout ça — d'où le bazar. Le code s'était même
fabriqué sa propre définition, recopiée à deux endroits (`admin_user.php`,
`interim_fixes.php`) :

```
interim non vide  ET  interim != 'famiflora'  ET  role != 'etudiant'
```

Une règle métier écrite dans un `WHERE`, c'est le symptôme : la question n'avait pas
de colonne pour la porter. Elle en a trois maintenant, et elles ne se recouvrent jamais.

| Question | Colonne | Valeurs | Ouvre un accès ? |
|---|---|---|---|
| **Chez qui travaille-t-elle ?** | `employeur` | interne · intérim · indépendant | non |
| **Comment est-elle engagée ?** | `contrat` | étudiant · flexi · fixe | non |
| **Qui suit son dossier ?** | `interim` | Konvert, Ago… ou Famiflora | non |
| **Qu'a-t-elle le droit d'ouvrir ?** | `role` | admin, teamcoach, mentor… | **oui, et lui seul** |

⚠️ **Le RBAC n'est pas touché.** Aucune ligne qui lit `role` n'a changé. `employeur` et
`contrat` sont **descriptifs** : ils répondent à des questions que `role` n'a jamais su
répondre. Le jour où FamiJob voudra travailler sur `contrat = 'etudiant'` plutôt que sur
`role = 'etudiant'`, la donnée sera là — et ce sera sa décision, pas la nôtre.

La preuve que les deux axes sont indépendants est dans la base : **1 admin chez Ago**,
**1 teamcoach chez Da Jobs**, **1 employé magasin chez Da Jobs**. Un profil interne avec
un employeur externe — une seule colonne ne pourra jamais dire les deux.

**« Externe » ne se stocke pas.** C'est `employeur != interne`, donc une déduction
(`famicardEstExterne()`). Une colonne de plus, et on aurait un jour une fiche marquée
interne **et** externe.

### ⚠️ `interim` ne dit pas « est-elle intérimaire »

Elle dit **qui suit son dossier**. Pour un externe, c'est son agence. Pour un
recrutement direct, c'est `Famiflora` — c'est-à-dire **Honorine**, qui a une ligne dans
`interim_agences`, un compte `agence_interim` à ce nom, et qui reçoit à ce titre les
mails des étudiants recrutés en direct.

**Cette colonne n'est donc jamais vidée.** Y retirer « Famiflora » aurait paru propre et
aurait coûté deux choses invisibles : la vue d'Honorine sur ses étudiants, et leurs
mails d'agence. 443 références y touchent dans FamiFormation et FamiJob : on ajoute à
côté, on ne déplace rien.

Conséquence : **un étudiant sans dossier n'apparaît chez personne**. Pas de message
d'erreur, il est simplement absent des listes. `creer.php` le refuse, et
`famicardIncoherencesEmploi()` signale les fiches existantes dans ce cas (il y en avait
10 au moment de l'écriture).

### La reprise

`famicardAssureEmploi()` crée les deux colonnes et déduit **une seule fois**, à leur
création. Elle ne devine rien d'autre :

| Ce qu'on lit | Ce qu'on en déduit | Combien |
|---|---|---|
| agence vide | employeur = interne | 231 |
| agence « Famiflora » | employeur = interne | 47 |
| une vraie agence | employeur = intérim | 72 |
| profil « étudiant » | contrat = étudiant | 109 |

Le reste des contrats reste **vide**, et c'est volontaire. Deviner « fixe » pour les 168
employés magasin remplirait l'écran d'une donnée RH que personne n'a vérifiée, et qui
aurait ensuite l'air d'avoir été saisie. « À préciser » se voit ; une valeur fausse, non.
C'est à ça que sert `contrats.php` : un tableau croisé pour voir où on en est, et une
saisie **par paquets** (« tous les employés magasin sont fixes ») pour que la reprise
se termine un jour.

---

## LE RÉCAP — « voici ce qu'on sait de toi, c'est juste ? »

À sa **première connexion**, sur n'importe quelle plateforme, le collaborateur voit sa
fiche et la confirme. Puis **une fois par an**. C'est le seul moment où quelqu'un relit
vraiment ces données : ni l'admin qui a créé le compte, ni personne d'autre ne peut savoir
que la ville a changé ou que le prénom est mal orthographié. Et sans rendez-vous annuel,
une base juste ne le reste pas — côté RGPD, c'est ce qui prouve qu'elle est tenue à jour.

Trois réponses possibles, et aucune n'est un cul-de-sac : **« tout est juste »**,
**« il y a une erreur »** (→ sa fiche, où il corrige lui-même, et l'admin voit passer la
correction par la mécanique existante), et **un mot pour l'administration**, remonté tel
quel et marqué non lu dans `validations.php`.

### Photo et email : deux régimes, et c'est du droit

| | Base légale | Ce qu'on fait |
|---|---|---|
| **Email** | nécessaire au compte (lien d'activation, mot de passe) | on insiste |
| **Photo** | **consentement** | on demande, on accepte le refus |

⚠️ **La photo ne se force pas.** Un bouton « je ne souhaite pas mettre de photo » enregistre
le refus **avec sa date** — un consentement se prouve, un refus aussi — et les rappels
cessent. Insister après un refus, c'est transformer un consentement libre en consentement
arraché. Il reste révocable : déposer une photo annule le refus.

⚠️ **Rien n'est bloquant, jamais.** « Plus tard » est toujours là. Ce qui manque revient
ensuite dans un **bandeau fermable**, identique sur les trois plateformes (une seule
fonction le dessine), qui réapparaît à la connexion suivante.

### Comment les autres plateformes l'appellent

`includes/validation.php` **ne dépend de rien de Famicard** : il ne demande qu'un PDO,
parce qu'il est inclus par FamiFormation et FamiJob, qui n'ont pas la configuration de
Famicard. Leur accueil pose une question et redirige, rien de plus — la logique n'est
écrite qu'une fois.

⚠️ `famicardDoitValiderFiche()` renvoie **false** si sa table n'existe pas ou si la base ne
répond pas. Une plateforme ne doit pas devenir inaccessible parce qu'une table manque
ailleurs : le récap est un service rendu, pas un péage.

⚠️ **Pas de redirection depuis `student.famiformation.com`.** Le cookie de session est
host-only : quelqu'un connecté sur le sous-domaine n'est PAS connecté sur `www`, et
l'envoyer vers `www/famicard/recap.php` le ferait atterrir sur un écran de connexion sans
comprendre pourquoi. Sur ce sous-domaine, le récap attend sa visite sur `www` ou sur
Famicard. C'est une limite de la coexistence des trois hôtes, pas quelque chose qui se
règle dans cet écran.

---

## LES AGENCES — pas des collaborateurs

Décision de Jimmy : **« Mes collaborateurs » et « Agences » sont deux modules distincts.**
Une agence n'est pas quelqu'un de la maison, c'est une société extérieure à qui l'on ouvre
une porte pour qu'elle voie **ses** intérimaires. Son compte n'a ni fiche, ni photo, ni
contrat, ni secteur : mélangé aux gens, il remplissait la base de lignes vides qu'on
prenait pour des fiches incomplètes.

`admin.php` et `export.php` excluent donc `role = 'agence_interim'` — et le filtre
« profil » ne le propose plus, puisqu'il serait garanti vide. Un **filtre agence** répond
en revanche à « qui ai-je chez Konvert », à l'écran comme dans le fichier exporté.

### ⚠️ C'est le NOM qui relie tout, et c'est fragile

FamiJob compare `utilisateurs.interim` au nom de l'agence pour décider qui voit quoi. Il
n'y a pas d'identifiant entre les deux, juste une chaîne de caractères.

**Conséquence que la page du site ne traite pas : renommer une agence coupait tout le
monde.** Le nom changeait dans `interim_agences`, les fiches gardaient l'ancien, et
l'agence se retrouvait avec un écran vide — sans message, sans erreur. `agences.php`
réécrit donc aussi les fiches concernées, dans une transaction, et dit combien ont suivi.

Les accès sont lus **par le nom**, pas par `interim_agence_users` : c'est le nom qui donne
réellement la vue, et un compte créé ailleurs peut n'avoir aucune ligne de liaison.
La liaison est tenue à jour quand même, pour que la page du site dise la même chose.
Les accès qui pointent sur une agence disparue sont **signalés en tête** — c'est
exactement ce que produisait un renommage.

Une agence ne se supprime pas tant que quelqu'un y est rattaché, et **« Famiflora » ne se
supprime pas du tout** : ce n'est pas une agence, c'est le suivi interne.

---

## UNE AGENCE DANS FAMICARD — et ce qu'elle y voit

Décision de Jimmy : **les agences entrent dans Famicard.** Elles y trouvent deux choses,
et deux seulement — leur propre fiche, et la liste des personnes qu'elles nous envoient.

### ⚠️ On ne mélange pas les deux

Décision de Jimmy : **« dans mes collaborateurs, que mes collaborateurs ; dans agences, que
mes agences »**. La séparation tient sur trois verrous, et il faut les trois :

- **`creer.php` ne propose pas le profil « agence intérim »** — un compte agence n'a ni
  employeur, ni contrat, ni rayon, ni photo ; le proposer là obligeait à débrancher la
  moitié des exigences de l'écran pour un cas qui n'y a pas sa place. Ces comptes se créent
  dans `agences.php`, où l'on sait quoi leur demander : de quelle agence il s'agit ;
- **`modifier.php` ne transforme pas une agence en personne** : sur une fiche d'agence, le
  seul profil proposé est « agence intérim ». La basculer en « étudiant » ferait apparaître
  une ligne vide dans la base des collaborateurs, avec un accès de société derrière ;
- **les écrans de collaborateurs les excluent** (`admin.php`, `export.php`, `contrats.php`,
  et les compteurs de contrats et de rattachements).

### Ce qu'elles voient de ces personnes

| | |
|---|---|
| ✅ | nom, prénom, et « **Étudiant** » ou « **Intérimaire** » |
| ❌ | email, téléphone, photo, ville, secteur, lieu de travail, horaires |

⚠️ **La restriction est dans la REQUÊTE, pas dans le gabarit.** `famicardPersonnesDeLAgence()`
ne lit que trois colonnes : ce qui n'est pas lu ne peut pas fuiter par une page qui
afficherait une ligne entière un jour de distraction. C'est de la minimisation, et c'est ce
que le RGPD attend d'un partage avec un tiers.

⚠️ **Le périmètre ne vient JAMAIS d'un paramètre.** Il est lu sur le compte connecté
(`utilisateurs.interim`). Un nom d'agence accepté depuis l'URL laisserait n'importe quelle
agence lire la liste d'une autre en changeant un mot dans la barre d'adresse.

Une agence ne se voit pas elle-même dans la liste, ni les accès d'une consœur. Les comptes
inactifs en sont exclus aussi.

### Leur fiche n'est pas celle d'un collaborateur

Décision de Jimmy : la carte d'une agence porte **ce qui identifie une agence**, pas les
cases vides d'une fiche de personne.

| Groupe | Contenu |
|---|---|
| **L'agence** | nom de l'agence, personne de contact, email principal, second email |
| **Contact** | la ville, et rien d'autre |
| **Compte** | identifiant, profil, statut, dernière visite |

**Retiré** : tout le rattachement (secteur, département, lieu de travail, employeur,
contrat, placement), la photo, le prénom, la date d'anniversaire. Ces lignes n'étaient pas
seulement vides — elles laissaient croire qu'il manquait quelque chose.

Les quatre champs d'agence viennent de **`interim_agences`** et non de `utilisateurs` : ce
sont des pseudo-colonnes, posées par `famicardAjouteAgence()`. ⚠️ **En lecture seule** :
ils décident où partent les horaires, ils se règlent dans `agences.php`, par un
administrateur, **à un seul endroit**. Deux écrans qui écrivent la même adresse finissent
toujours par diverger.

### Ce qui a dû être levé pour ça

`Famiformation/config.php` **éjectait tout compte agence vers FamiJob**, sur n'importe
quelle page incluant sa configuration — donc sur tout Famicard. La règle laisse désormais
passer ce qui est sous `/famicard/`.

⚠️ Le test porte sur le **dossier**, pas sur le nom du fichier : la liste blanche existante
compare un `basename`, et y ajouter « index.php » aurait ouvert l'accueil du **site** en
même temps que celui de Famicard. L'hôte est vérifié aussi, parce que sur
`famicard.famiformation.com` le dossier `famicard/` est la racine et n'apparaît pas
forcément dans l'URI.

`Famicard/login.php` refusait également ces comptes : ce refus est levé. **Ce qu'ils voient
se décide écran par écran, pas à la porte** — tous les écrans d'administration restent
fermés par `famicardEstAdmin()`.

### Trois écrans qui les traitaient comme des personnes

- **Le récap** ne leur est plus proposé : demander à une société de confirmer son prénom et
  de déposer une photo n'a aucun sens ;
- **le bandeau de rappel** non plus, pour la même raison ;
- **l'accueil** ne leur annonce plus « ta carte est incomplète » : elle n'a ni photo, ni
  date de naissance, ni secteur, et le lui reprocher serait lui reprocher de ne pas être
  quelqu'un.

Enfin, une agence ne se voit plus proposer **FamiFormation** : elle ne suit aucune
formation, et cette tuile ne menait qu'à un refus. Son outil est FamiJob.

---

## AVIS ET SUGGESTIONS

« Dans la même idée que FamiJob » (Jimmy) — et littéralement : **c'est la même table**.
`interim_feedback` existe déjà côté FamiJob ; en ouvrir une seconde aurait donné deux
boîtes à idées, dont une que personne ne relève. Une colonne `source` dit d'où vient le
message, pour qu'un administrateur sache de quel écran on lui parle.

**Ouvert à tout le monde, agences comprises.** Un module qui recueille la parole des
équipes et n'est visible que par ceux qui décident ne recueille rien.

⚠️ **Un non-admin ne voit que ses propres messages**, et la condition est dans le SQL. Une
boîte à idées où chacun lit les remarques des autres n'en recueille plus : les gens s'y
censurent.

⚠️ **On n'efface jamais un message.** « Traité » le range, ne le supprime pas — une boîte
qui se vide est une boîte dont on ne peut plus dire ce qui a été proposé, ni ce qu'on en a
fait. Répondre rouvre le sujet.

---

## LES ACCÈS AUX SERVICES

Aujourd'hui deux services, FamiFormation et FamiJob. **Il y en aura d'autres**, et c'est
la contrainte qui a dicté la structure : ajouter un service ne doit demander ni migration
ni modification d'écran.

D'où une **table** (`famicard_services`) et non une liste écrite dans le code : un
service = une ligne (code, nom, description, adresse, ordre, actif). Les accès d'un
collaborateur sont dans `famicard_acces` (user_id, service_id).

> **Pourquoi pas le rôle ?** Parce que `utilisateurs.role` répond à « qui est cette
> personne », pas à « où a-t-elle le droit d'aller ». Un mentor et un teamcoach peuvent
> avoir les mêmes accès ; deux admins peuvent en avoir de différents. Confondre les deux,
> c'est se condamner à créer un rôle par combinaison le jour où il y aura cinq services.

**Migration douce**, indispensable pour ne casser personne : tant qu'aucun accès explicite
n'est enregistré pour quelqu'un, on applique les règles historiques — FamiFormation pour
tous, FamiJob pour les admins et teamcoachs. Dès qu'un accès explicite existe, il fait
foi. Personne ne perd un accès du jour au lendemain parce qu'une table vient d'être créée.

### La règle de navigation

**La seule porte de Famicard vers FamiFormation est la tuile « FamiFormation » de
l'accueil.** Aucune autre page ne doit renvoyer vers le site — ni lien de retour, ni
« modifier ceci sur ton profil ». Famicard est le **centre de données utilisateur** :
toute information qui concerne le collaborateur se consulte et se modifie ici.

C'est pour ça que la photo de profil a quitté `profil.php` du site : elle se dépose
maintenant en haut de `modifier.php`, avec le reste de la fiche.
Les **données**, elles, n'ont pas bougé : même dossier sur le volume, même colonne
`utilisateurs.photo_profil`, même compression. On déplace l'écran, jamais le stockage —
sinon la photo affichée par FamiFormation ne serait plus celle déposée ici.

Exception connue, à trancher : la page de connexion renvoie encore vers
`account_help.php` (mot de passe / identifiant oubliés), qui vit côté site.

Deux lectures d'un même objet :

| Qui | Ce que Famicard est pour lui |
|---|---|
| **Collaborateur** | une plateforme informative : sa carte, ses infos, son badge |
| **Administrateur** | la base de données des collaborateurs de l'entreprise |

---

## Le principe de départ

**Famicard n'ouvre pas une nouvelle base.** Elle lit la table `utilisateurs`, celle que
FamiFormation et FamiJob utilisent déjà, et **reprend leurs libellés** (« Ville de
résidence », « Lieu de travail », « Date d'anniversaire »...). Un même champ ne porte pas
deux noms selon l'écran.

Conséquence pratique : sur `www.famiformation.com/famicard/`, la session est déjà celle du
site (même cookie, même hôte). Un collaborateur connecté à FamiFormation est connecté à
Famicard.

**Sauf sur le sous-domaine.** `famicard.famiformation.com` est un autre hôte pour le
navigateur, et le cookie de session est posé host-only (`'domain' => ''`) : la session de
`www` n'y existe pas. D'où `login.php` — voir « Les deux adresses » plus bas.

---

## Comment c'est construit

```
Famicard/
  config.php           amorçage — réutilise la config du site (session, base, CSRF)
  includes/carte.php   ⭐ LE MODÈLE : les champs et leurs règles
  includes/emploi.php  ⭐ EMPLOYEUR, CONTRAT, DOSSIER — et pourquoi pas le profil
  includes/rattachement.php  ⭐ DE QUOI ELLE RELÈVE — à ne pas confondre avec le
                       PLACEMENT de FamiJob (student_department_links)
  login.php            connexion (mêmes identifiants, même session que le site)
  logout.php           déconnexion
  index.php            ⭐ L'ACCUEIL DU PORTAIL : 4 tuiles, rien d'autre
  creer.php            ⭐ CRÉATION D'UN COLLABORATEUR : compte, rattachement, accès
  includes/photo.php   le dépôt d'une photo — partagé par la création et la fiche
  includes/validation.php  ⭐ LE RÉCAP : « c'est juste ? » — appelé par les 3 plateformes
  recap.php            l'écran de vérification, 1re connexion puis 1×/an
  fiche.php            la carte du collaborateur (c'était index.php)
  modifier.php         ⭐ ÉDITION : sa propre fiche, ou celle d'un autre (admin)
                       — la photo se dépose en haut de cet écran (plus de page à part)
  validations.php      les corrections que l'admin doit confirmer
  includes/modifications.php   écriture des champs + registre des changements
  includes/services.php        ⭐ LES ACCÈS : quels services pour qui
  contrats.php         interne/intérim/indépendant + type de contrat, et ce qui se contredit
  tri_profils.php      passer les comptes beta en profil employé (venu du site)
  relance_mdp.php      renvoyer le lien de création de mot de passe (venu du site)
  admin.php            la base des collaborateurs (liste, filtres, badge, export)
  agences.php          ⭐ LES AGENCES D'INTÉRIM — et les accès qu'on leur ouvre
  avis.php             avis et suggestions — la MÊME boîte que celle de FamiJob
  includes/agence.php  ⭐ CE QU'UNE AGENCE A LE DROIT DE VOIR
  mes_interimaires.php l'écran d'une agence : ses gens, nom + prénom + type
  badge.php            le badge imprimable 75 × 36 mm
  export.php           l'export Excel, colonnes au choix
  admin_champs.php     création des libellés par l'administrateur
```

### Deux sortes de champs

**Le socle** — adossé aux colonnes de `utilisateurs`. Existe déjà, sert déjà aux deux
autres plateformes. Non modifiable depuis Famicard, exprès.

**Les champs libres** — créés par un administrateur dans `admin_champs.php`. Ils vivent
dans deux tables à part (`famicard_champs`, `famicard_valeurs`).

> **Pourquoi pas des colonnes ?** Ajouter une colonne à `utilisateurs` à chaque libellé
> créé, c'est modifier la table dont dépendent FamiFormation, FamiJob et le quiz — pour un
> besoin d'affichage. Une table de valeurs ne casse personne, et un libellé supprimé ne
> laisse pas une colonne morte derrière lui.

### Ce qu'on exige à la CRÉATION

Tout se remplit à la création — décision de Jimmy, et elle a une raison : un champ
laissé vide là est un champ que **personne ne complétera jamais**. La personne existe, le
compte marche, plus rien ne rappelle qu'il manque quelque chose. C'est le seul moment où
l'on a quelqu'un pour répondre à ces questions.

**Obligatoires** : identifiant, nom, prénom, profil, employeur (interne/intérim/
indépendant), type de contrat, lieu de travail, secteur — et l'agence dès que l'employeur
est l'intérim.

**Facultatifs, et c'est voulu** : le **département** (vide = *tout le secteur*, ce qu'il
faut pour un teamcoach), l'**email** et la **photo**.

⚠️ Chaque exigence est conditionnée à l'existence du champ : sur une base qui n'a pas
encore la colonne, on ne réclame pas l'impossible.

**Email et mot de passe : l'un des deux suffit, mais il en faut un.** Avec un email, la
personne reçoit son lien d'activation et choisit son mot de passe. Sans email, le mot de
passe devient **obligatoire** — c'est le seul moyen d'ouvrir le compte, et l'écran le dit
en changeant le libellé dès que le champ email se vide.

**Sans email ou sans photo, l'écran demande confirmation** — une fois, au moment où c'est
encore facile à corriger. Il ne bloque pas : il y a de vraies raisons de créer un compte
sans l'un ni l'autre. Mais l'absence coûte quelque chose plus tard (pas de lien
d'activation, carte signalée incomplète), et c'est ça qui est dit.

### Qui modifie quoi

**Le collaborateur corrige presque tout sur sa fiche** — décision de Jimmy. Prénom, nom,
email, ville, date d'anniversaire, photo, lieu de travail, agence, employeur, type de
contrat, secteur et département. Il sait mieux que quiconque où il travaille et comment
son prénom s'écrit ; le contrôle n'a pas disparu, il vient **après** : la correction
s'applique, puis l'admin confirme ou rétablit (`validations.php`).

⚠️ **Deux champs restent fermés, et c'est de la sécurité, pas du confort** : le **profil**
et le **statut**. Ici la validation vient *après* la modification — quelqu'un qui pourrait
choisir son propre profil se donnerait « admin » et **l'aurait**, le temps que quelqu'un
s'en aperçoive.

**Un mot peut accompagner l'enregistrement.** Facultatif, sur sa propre fiche, il remonte
dans `validations.php` à côté des corrections qu'il explique souvent. ⚠️ Il **ne vaut pas
validation de la fiche** : « je crois que mon contrat est faux » ne dit pas « tout est
juste », et confondre les deux repousserait la prochaine relecture d'un an sur la foi d'un
message qui dit l'inverse.

⚠️ **Rétablir un rattachement demande une traduction.** Le registre ne garde que du texte
(« Décoration > Bougies ») — c'est ce qui le rend lisible des années plus tard. Pour
rétablir, `famicardRetrouveRattachementParNom()` le retraduit en identifiants, et **refuse
de trancher** si le libellé ne correspond plus à rien : marquer une ligne réglée sans rien
remettre en place serait le pire des deux mondes.

**L'identifiant se modifie, mais le champ est VERROUILLÉ par défaut.** Un cadenas est posé
à côté. Il ouvre une **fenêtre** qui demande un **mot de passe dédié**, rangé dans les
variables Railway sous `FAMICARD_MDP_IDENTIFIANT`. Ce n'est pas le mot de passe de
l'administrateur : **être admin ne suffit pas**, ce qui met le changement d'identifiant
hors de portée d'une session laissée ouverte sur un poste. C'est la seule modification de
la fiche qui puisse mettre quelqu'un dehors, puisque c'est avec ça qu'on se connecte.

**Le verrou est un état de SESSION, pas un champ du formulaire.** Le mot de passe est
vérifié une fois, à l'ouverture de la fenêtre ; le serveur retient ensuite que le cadenas
est ouvert — **pour cette fiche-là**, et pour dix minutes. Il se referme après un
changement réussi : le déverrouillage vaut pour une modification, pas pour la demi-heure
qui suit. Un mot de passe gardé dans un champ caché aurait traîné dans le DOM et serait
reparti à chaque enregistrement.

⚠️ **Aucun champ `password` dans le formulaire de la fiche**, et ce n'est pas cosmétique :
Chrome et Edge y voyaient un couple « login + mot de passe », ignoraient
`autocomplete="off"` et **remplissaient l'identifiant avec celui de la personne connectée**.
On corrigeait un email, l'identifiant changeait tout seul, et l'enregistrement était
refusé. La fenêtre du cadenas vit donc **hors** du formulaire, et le champ identifiant n'a
pas d'attribut `name` tant que le verrou est fermé — ce que le navigateur y écrit ne peut
plus rien casser.

⚠️ **Variable absente = champ verrouillé pour tout le monde**, et c'est le bon défaut : une
variable qui manque ne doit jamais ouvrir une porte. L'écran le dit, plutôt que de laisser
chercher pourquoi ça ne marche pas.

⚠️ **L'enregistrement revérifie l'état de session** : la fenêtre ne donne aucun droit, elle
ouvre une porte à l'écran. Un champ envoyé à la main sans être passé par elle est refusé.
Cinq mots de passe faux ferment la porte cinq minutes — sinon le secret s'essaie en boucle
depuis la console du navigateur. Trois refus s'y ajoutent, et ils ne sont pas négociables :

- **`admin` et `Accueil` ne se renomment pas.** Ce ne sont pas des noms, ce sont des clés :
  `checklist_gerbeur.php` ouvre un accès à qui a `$_SESSION['username'] === 'Accueil'`, et
  `admin` est protégé contre la suppression. Les renommer retirerait ces droits sans le
  moindre message (`famicardIdentifiantsVerrouilles()`).
- **Ni vide, ni avec un espace, ni au-delà de 50 caractères** — la colonne tronquerait en
  silence et l'identifiant enregistré ne serait pas celui saisi.
- **Jamais deux fois le même**, la colonne portant une clé unique.

Deux conséquences sont annoncées à l'écran parce qu'on ne peut pas les rattraper :
l'ancien identifiant cesse de fonctionner (il faut prévenir la personne), et les
**présences déjà enregistrées restent sous l'ancien nom** — `presences.nom` stocke du
texte, pas un numéro de compte.

**La correction s'applique tout de suite**, et l'administrateur la confirme ensuite
(`validations.php`, qui montre l'ancienne et la nouvelle valeur). « Rétablir » **réécrit**
l'ancienne valeur — marquer une modification refusée en laissant la valeur refusée en
place donnerait une fiche fausse et un registre qui prétend le contraire.

> Un circuit en quatre temps avait été envisagé (demande avec motif → autorisation →
> modification → validation). Écarté : deux allers-retours administratifs pour corriger
> une adresse, c'est une fiche que personne ne corrige. Le contrôle existe toujours, il
> vient après au lieu d'avant.

Comme pour la lecture, c'est **le champ qui porte la règle** (`modifiable`), et
`famicardPeutModifier()` qui tranche — testée à l'affichage **et** à l'enregistrement,
parce qu'un formulaire n'est pas une autorisation.

### La règle voyage avec le champ

Chaque champ déclare sa **nature** (service / personnel), **qui a le droit de le voir**, et
**s'il peut figurer sur le badge**. Une page n'affiche pas une date d'anniversaire parce
qu'elle est dans la table : elle demande à `famicardPeutVoir()`. Un écran écrit vite, plus
tard, ne peut pas exposer ce qu'il ne doit pas.

---

## Ce qui est fait

- [x] Dossier branché sur `/famicard/` (Dockerfile + Caddyfile)
- [x] **Sous-domaine `famicard.famiformation.com`** + connexion propre à Famicard
- [x] Amorçage sans duplication de la config
- [x] Modèle de champs avec règles de visibilité, libellés alignés sur FamiFormation
- [x] **Photo obligatoire** — la carte signale les champs requis encore vides
- [x] Carte du collaborateur
- [x] Base des collaborateurs : liste, recherche, filtres profil + lieu
- [x] **Badge 75 × 36 mm** imprimable
- [x] **Export Excel** avec choix des colonnes
- [x] **Libellés créés par l'admin**, obligatoires ou non
- [x] **Création d'un collaborateur** (`creer.php`, tuile de l'accueil) — compte,
      rattachement et accès aux services d'un seul geste
- [x] **Rattachement RH modifiable** (secteur + département facultatif) — sa propre
      table, distincte de la planification de FamiJob
- [x] **Identifiant modifiable** par un admin, derrière un cadenas ouvert par un mot de
      passe dédié (`FAMICARD_MDP_IDENTIFIANT`, à poser dans Railway)
- [x] **Employeur et type de contrat** séparés du profil (`includes/emploi.php`,
      `contrats.php`) — interne / intérim / indépendant, étudiant / flexi / fixe,
      **sans toucher au RBAC**
- [x] **Avatar 3D** (`avatar.php`, `assets/avatar3d.js`) — personnage entier construit en
      code, 20 réglages, vignette PNG pour les petits affichages, et une porte JSON
      (`avatar_data.php`) pour que FamiFormation l'affiche sans rien reconstruire

## Ce qui reste

- [ ] **Retirer la création côté site** — `creer.php` existe, mais
      `Famiformation/admin_collaborateurs.php` crée toujours des comptes lui aussi. Ce
      n'est pas une divergence de données (une création est un INSERT, pas deux écritures
      de la même colonne), mais tant que les deux écrans existent, un compte peut naître
      sans passer par Famicard. Le retrait se fait côté live, pas d'ici.
- [ ] **Renseigner le rattachement de tout le monde** — il se pose fiche par fiche
      (`modifier.php`) et à la création. Le compte de ce qui manque est affiché dans
      `contrats.php`. C'est le préalable à tout filtrage : tant que les fiches sont
      vides, restreindre l'affichage viderait les écrans.
- [ ] **Brancher le filtrage par périmètre** — `famicardPerimetreRh()` rend la liste des
      départements qu'une personne couvre. Ce que chaque écran en fait est SA décision
      (« un teamcoach Décoration ne voit pas les horaires de la caisse »). Ne jamais
      confondre `null` (aucun périmètre enregistré) et `[]` (ne voit rien).
- [ ] **Faire lire `contrat` par FamiJob** — le matching travaille encore sur
      `role = 'etudiant'`, qui répond donc à deux questions à la fois. La donnée propre
      existe maintenant à côté ; la bascule est une décision de FamiJob, pas d'ici, et
      elle ne peut se faire qu'une fois les contrats renseignés (voir `contrats.php`).
- [ ] **Écran d'attribution des accès sur un compte EXISTANT** — les cases existent à la
      création (`creer.php`), mais rien ne permet encore de les modifier ensuite. En
      attendant, les règles historiques s'appliquent à qui n'a aucun accès enregistré.
- [ ] **Nettoyage à la suppression d'un compte** — la suppression est encore côté site et
      n'efface pas `famicard_acces` (`famicardOublieAcces()` existe et n'est appelée nulle
      part). À brancher au moment où la suppression rejoindra Famicard, sinon un futur
      compte réutilisant le même id hérite des accès du précédent.

- [ ] **Historique consultable** — les modifications sont toutes enregistrées
      (`famicard_modifications`), mais seules celles en attente sont affichées. « Qui a
      changé ce champ, et quand » demande encore une requête SQL à la main.
- [ ] **Famicard comme autorité d'accès** : ouvrir/fermer FamiFormation et FamiJob depuis ici
- [ ] **Volet RGPD** — voir plus bas
- [ ] **Brancher l'avatar dans FamiFormation** — le personnage existe et sa porte JSON
      aussi (voir « L'AVATAR 3D » plus bas). Ce qui reste est une décision de FamiFormation :
      où il apparaît (profil, classement, jeux three.js) et sous quelle forme (vignette ou
      scène animée). Rien à reconstruire côté Famicard.

---

## Le badge

**75 mm de longueur × 36 mm de hauteur**, paysage. Contenu : le **prénom**, et dessous la
mention en français puis en néerlandais.

| Profil | Mention |
|---|---|
| Étudiant | **Étudiant** / Student |
| Tout le reste | **À votre disposition** / Tot uw dienst |

Rien d'autre n'y figure, et ce n'est pas un oubli : un badge se perd, se photographie et
traîne sur un comptoir. Le modèle marque `'badge' => true` sur le seul prénom — aucun
champ libre ne peut y atterrir.

Tout est coté **en millimètres**, jamais en pixels : un badge coté en px sort à une taille
qui dépend de l'écran et du navigateur, donc jamais à 75 × 36. `@page { size: 75mm 36mm }`
fait que l'imprimante sort le carton seul, pas un badge perdu au milieu d'une A4.

Un prénom long descend automatiquement d'un ou deux crans de taille plutôt que de déborder.

Un administrateur imprime le badge de n'importe qui depuis la liste (`badge.php?id=`).

---

## L'export Excel

`phpspreadsheet` **5.5.0** est déjà dans `Famiformation/vendor/` — rien à installer.

L'administrateur **choisit ses colonnes** parmi toute la fiche, champs libres compris.
Ne sont proposés que les champs qu'un admin a le droit de voir : un champ réservé ne peut
pas sortir par l'export.

Les filtres de `admin.php` sont **repris dans l'URL** : le fichier contient exactement ce
qui était à l'écran. Sinon on retombe sur le classique « le fichier ne dit pas la même
chose que la liste », et personne ne sait laquelle des deux a raison.

Repli **CSV** (avec BOM UTF-8) si PhpSpreadsheet est indisponible : mieux vaut un fichier
ouvrable qu'une page d'erreur.

---

## L'AVATAR 3D — la figurine du collaborateur

**Décision de Jimmy : l'avatar ne remplace pas la photo, il vit à côté.**
La photo reste la pièce qui identifie (fiche, badge, RH) ; l'avatar est l'identité
« vivante » — l'accueil, demain FamiFormation. Aucun écran existant ne perd son visage
réel, et personne ne se retrouve avec un badge sans photo.

L'atelier est `avatar.php`. Le personnage est **entier** (pas un buste) : c'est ce qui
rend la **tenue** visible, et ce qui permettra de le réutiliser en mascotte animée
ailleurs.

### Ce qui a été tranché, et pourquoi

**Aucun fichier 3D.** Tout est bâti en code, à partir de sphères, capsules, boîtes et
tores (`assets/avatar3d.js`). Pas de `.glb` à héberger, pas de licence d'asset, rien qui
puisse manquer au déploiement — le personnage pèse le poids d'un fichier JavaScript. Le
prix à payer est un style volontairement « jouet » : c'est le seul qu'on peut tenir sans
budget d'assets, et il vieillit mieux qu'un modèle réaliste raté.

**La base stocke des mots, pas des pixels.** `famicard_avatars` garde une ligne de
quelques centaines d'octets : `coupe = mi_long`, `couleur_cheveux = chatain`… Deux
conséquences qui valent la peine :

- on peut **retoucher la palette ou améliorer une coupe sans migration** — l'avatar de
  tout le monde s'améliore au prochain affichage ;
- FamiFormation lira **la même ligne** et affichera **le même personnage**.

**Le PNG est un dérivé, jamais la source.** Une vignette 512 px est enregistrée sur le
volume (`divers/avatars/`) pour que les petits affichages (un rond de 40 px sur l'accueil,
une liste, demain un e-mail) n'aient pas à charger un moteur 3D. Si elle disparaît, on la
regénère ; si elle manque, l'écran retombe sur son repli habituel.

**Le catalogue vit en PHP, et nulle part ailleurs** (`includes/avatar.php`). Une seule
liste d'options, envoyée telle quelle au JavaScript : ce que l'atelier **propose** est
exactement ce que le serveur **accepte**. Les couleurs arrivent au moteur 3D **déjà
résolues** en codes hexadécimaux (`famicardAvatarLook()`) — `avatar3d.js` ignore ce qu'est
un « châtain », donc toute la palette se retouche sans ouvrir une ligne de JavaScript.

> ⚠️ **Les clés sont des engagements.** `mi_long` est écrit en base pour chaque personne :
> renommer la clé efface le choix de tout le monde. Les libellés et les codes couleur, eux,
> se changent librement — ils ne sont jamais stockés.

**La vignette ne passe pas par `media.php`.** Ce script vit sur www ; depuis
`famicard.famiformation.com`, la session de www n'existe pas (cookie host-only) et l'image
reviendrait en 403 — donc un avatar cassé une visite sur deux selon l'adresse d'entrée.
Famicard sert **sa** vignette avec **sa** session : `avatar_image.php`.

**Une agence n'a pas d'avatar.** Une société extérieure n'a ni coupe de cheveux ni tenue
de travail. Même règle que pour la carte (voir `includes/agence.php`).

### La réutilisation dans FamiFormation

C'est la raison d'être du découpage. Rien n'est à reconstruire :

```js
fetch('/famicard/avatar_data.php?u=12')
  .then(r => r.json())
  .then(d => creerAvatar(monDiv, d.look));   // d.look : couleurs déjà résolues
```

avec `import { creerAvatar } from '/famicard/assets/avatar3d.js'`.
`avatar_data.php` renvoie aussi `image_url` pour ceux qui ne veulent qu'une vignette.
Ni la table, ni la palette, ni le rangement des fichiers ne sortent de Famicard.

`creerAvatar()` accepte `cadrage: 'buste'` pour un cadrage serré sur le visage, et
`interactif` / `rotationAuto` / `anime` pour une figurine décorative qui ne capte pas la
souris.

### Les points à surveiller

- **three.js vient du CDN jsdelivr, en version figée** (`0.169.0`) — même choix que pdfjs
  dans FamiFormation. Un `@latest` ferait dépendre l'apparence de tout le monde d'une mise
  à jour qu'on ne contrôle pas. Pour l'héberger nous-mêmes, il n'y a qu'une ligne à changer,
  en tête de `assets/avatar3d.js`.
- **Pas de 3D dans une page de consultation.** `fiche.php` et `index.php` affichent la
  vignette PNG, pas la scène : charger six cents kilo-octets de moteur 3D pour un rond de
  cent pixels serait payer très cher un détail. La 3D vit dans l'atelier, où elle sert.
- **Un poste sans WebGL** garde l'atelier utilisable : les boutons de choix fonctionnent,
  seul l'aperçu manque, et un message le dit. La configuration s'enregistre quand même.

---

## RGPD — 🚨 CHANTIER OBLIGATOIRE, PAS UNE OPTION

**Décision de Jimmy (2026-08-10) : la mise en conformité RGPD doit être faite.**
Ce n'est pas un « nice to have » qu'on repousse tant que ça marche : Famicard rassemble
les données personnelles de tous les salariés, et c'est précisément ce que la loi encadre.
Plus la base grossit, plus la remise en ordre coûte cher — les décisions ci-dessous
(durée de conservation, base légale) déterminent ce qu'on a le droit de garder, donc
elles doivent être prises **avant** d'accumuler des années de fiches.

**En place**

- **Minimisation par défaut** — un champ n'est visible que si sa règle l'autorise.
- **Obligatoire / facultatif** séparés, y compris sur les libellés créés par l'admin.
- **Badge muet** — décidé dans le modèle, pas au moment de l'impression.
- **Suppression propre** — supprimer un libellé efface les réponses (`ON DELETE CASCADE`) :
  on ne garde pas des données rattachées à un champ disparu, donc invisibles et
  impossibles à corriger.
- **Registre des modifications** — `famicard_modifications` garde qui a changé quoi et
  quand. C'est la traçabilité, et ça sert aussi au droit de rectification.

**À faire**

- **Base légale** par catégorie : contrat pour l'essentiel, **consentement** pour le
  facultatif (photo, bitmoji, date d'anniversaire).
- **Information** : dire au collaborateur ce qui est stocké, pourquoi, combien de temps.
- **Droits** : accès et rectification passent par la carte ; l'effacement demande une
  décision.
- **Durée de conservation** — **rien n'est décidé**, c'est le vrai trou. Que garde-t-on
  d'un collaborateur parti, et combien de temps ?
- **Traçabilité** : qui a exporté la base, quand, avec quelles colonnes.

---

## Les deux adresses

Famicard se visite de deux façons, et les deux doivent marcher :

| Adresse | Ce que « / » désigne |
|---|---|
| `www.famiformation.com/famicard/` | le site principal ; Famicard est un sous-dossier |
| `famicard.famiformation.com` | **Famicard lui-même** — le Caddyfile réécrit tout vers `famicard/` |

Toute la difficulté tient dans cette bascule. Sur le sous-domaine, un lien écrit
`/index.php` ou `/favicon.ico` est réécrit vers `famicard/` et tombe dans le vide. FamiJob
était déjà tombé dans ce piège sur `student.famiformation.com`.

La règle est donc simple, et il n'y en a qu'une :

- **lien vers le site principal** (favicon, fond, photo de profil, `profil.php`) →
  `famicardSiteUrl()`, qui rend une URL absolue vers `www` quand on est sur le sous-domaine ;
- **lien interne à Famicard** (`badge.php`, `login.php`) → **relatif**, écrit tel quel.
  Famicard est un dossier plat : le relatif vise juste dans les deux dispositions.

Et c'est parce que la session de `www` n'existe pas sur le sous-domaine que `login.php`
existe : il pose exactement les mêmes clés de session que `Famiformation/login.php`, sur la
même table `utilisateurs`. Deux hôtes, deux sessions, un seul compte. Si la vérification du
mot de passe change côté site, elle doit changer ici.

---

## Notes techniques

**Ne pas utiliser `verifierConnexion()` ici.** Passer par `famicardExigeConnexion()`, qui
renvoie vers le `login.php` **de Famicard**. Renvoyer vers celui du site enfermerait le
sous-domaine dans une boucle : on s'y connecterait sans que Famicard le voie jamais.

**Vider les tampons avant d'envoyer un fichier.** `config.php` ouvre un `ob_start()` pour
injecter le thème ; sans `ob_end_clean()`, le HTML se colle devant le `.xlsx` et Excel
refuse de l'ouvrir.

**La DDL est dans `admin_champs.php`, et nulle part ailleurs.** Le site a fait le ménage
une fois pour « retirer la DDL du chemin chaud » : pas de `CREATE TABLE` sur chaque page.

**Deux dispositions coexistent.** Dans le conteneur, FamiFormation est à la racine servie
et Famicard dans `/famicard/`. Dans le dépôt, ce sont deux dossiers frères. `config.php` et
`export.php` essaient les deux chemins au lieu d'en supposer un.

**`Famiformation/` et `Famijob/` sont une copie conforme du live** : ne rien y modifier
pour Famicard sans le dire.

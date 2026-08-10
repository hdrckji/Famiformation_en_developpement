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

### Où va-t-on : la RH de FamiFormation devient Famicard

Décision de Jimmy. Ce qui vit encore dans `Famiformation/admin_collaborateurs.php`
(création de compte, identifiant, mot de passe, activation, suppression) **rejoindra
Famicard**. Il n'y a pas deux produits qui se partagent le collaborateur : il y en a un,
et c'est celui-ci.

État actuel, transitoire :

| Sujet | Aujourd'hui | Cible |
|---|---|---|
| Fiche, champs, photo, badge, export | **Famicard** | Famicard |
| Secteur / département | **Famicard** (+ page RH) | Famicard |
| Création de compte, mot de passe, activation | page RH du site | **Famicard** |
| Rôle, statut, agence, lieu de travail | les deux | **Famicard** |

⚠️ Tant que la bascule n'est pas faite, **ne pas faire écrire la même colonne aux deux
endroits**. Deux écrans qui écrivent l'email finissent toujours par diverger, et c'est
celui qu'on regarde le moins qui gagne.

Ce qui reste vrai après la bascule : la définition des secteurs
(`Famiformation/includes/organisation.php`) est un **emplacement de fichier**, pas un
partage des rôles — Famicard le charge parce qu'il charge déjà la configuration du site.

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
  login.php            connexion (mêmes identifiants, même session que le site)
  logout.php           déconnexion
  index.php            ⭐ L'ACCUEIL DU PORTAIL : 4 tuiles, rien d'autre
  fiche.php            la carte du collaborateur (c'était index.php)
  modifier.php         ⭐ ÉDITION : sa propre fiche, ou celle d'un autre (admin)
                       — la photo se dépose en haut de cet écran (plus de page à part)
  validations.php      les corrections que l'admin doit confirmer
  includes/modifications.php   écriture des champs + registre des changements
  admin.php            la base des collaborateurs (liste, filtres, badge, export)
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

### Qui modifie quoi

Le collaborateur corrige **ses coordonnées** : email, ville, date d'anniversaire, photo.
Le reste — profil, statut, lieu de travail, agence, secteur — est de la donnée de gestion
et reste à l'administrateur. L'identifiant ne se modifie pas depuis Famicard : il sert à
se connecter, le changer couperait l'accès sans prévenir personne.

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

## Ce qui reste

- [ ] **Le secteur sur la carte** — le rattachement existe et se règle depuis
      `admin_collaborateurs.php` (voir `Famiformation/includes/organisation.php`), mais la
      carte ne l'affiche pas encore : il n'est ni une colonne de `utilisateurs` ni un champ
      libre, donc `famicardValeurAffichee()` ne sait pas encore le résoudre.
- [ ] **Le rattachement sur la carte** — les 9 secteurs et leurs 53 départements sont en
      base (`famicard_secteurs`, `famicard_departements`) et se règlent depuis la page RH
      de FamiFormation. La carte ne les affiche pas encore, et ils devraient à terme se
      régler ici, dans Famicard.
      ⚠️ Ne pas confondre `famicard_departements` avec la table `departments` de FamiJob
      (matching étudiants) : même mot, deux choses.
- [ ] **Historique consultable** — les modifications sont toutes enregistrées
      (`famicard_modifications`), mais seules celles en attente sont affichées. « Qui a
      changé ce champ, et quand » demande encore une requête SQL à la main.
- [ ] **Famicard comme autorité d'accès** : ouvrir/fermer FamiFormation et FamiJob depuis ici
- [ ] **Volet RGPD** — voir plus bas
- [ ] **Bitmoji 3D** — le personnage réutilisé dans les jeux three.js de FamiFormation

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

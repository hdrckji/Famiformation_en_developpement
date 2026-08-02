# Famicard

La **carte d'identité du collaborateur Famiflora** : ce qui lui donne accès aux services
de la maison. Aujourd'hui **FamiFormation** et **FamiJob** ; les autres viendront après.

Deux lectures d'un même objet :

| Qui | Ce que Famicard est pour lui |
|---|---|
| **Collaborateur** | une plateforme informative : sa carte, ses infos, ses accès |
| **Administrateur** | la base de données des collaborateurs de l'entreprise |

---

## Le principe de départ

**Famicard n'ouvre pas une nouvelle base.** Elle lit la table `utilisateurs`, celle que
FamiFormation et FamiJob utilisent déjà. C'est ce qui rend la carte vraie : si elle avait
ses propres lignes, elle dirait autre chose que les services auxquels elle donne accès,
et il faudrait synchroniser deux vérités.

Conséquence pratique : la session est déjà partagée (même cookie, même domaine). Un
collaborateur connecté à FamiFormation est connecté à Famicard. Rien à brancher.

---

## Comment c'est construit

```
Famicard/
  config.php           amorçage — réutilise la config du site (session, base, CSRF)
  includes/carte.php   ⭐ LE MODÈLE : la liste des champs et leurs règles
  index.php            la carte du collaborateur
  admin.php            la base des collaborateurs (consultation + filtres)
```

**Tout part de `includes/carte.php`.** Chaque champ y porte : son libellé (FR/NL), sa
colonne en base, s'il est obligatoire, sa nature (service / personnel / sensible), qui a
le droit de le voir, et s'il peut figurer sur le badge imprimé.

La règle de confidentialité voyage **avec le champ**. Une page ne décide pas toute seule
d'afficher une date de naissance : elle demande à `famicardPeutVoir()`. C'est ce qui fait
qu'un futur écran, écrit distraitement, ne peut pas exposer ce qu'il ne doit pas.

Un champ dont la colonne vaut `null` est **prévu mais pas encore en base**. Il s'affiche
« à définir » et les exports l'ignorent. **Aucune colonne n'est créée automatiquement** :
ce sera une décision explicite.

---

## Ce qui est fait

- [x] Le dossier, branché sur `/famicard/` (Dockerfile + Caddyfile)
- [x] L'amorçage sans duplication de la config
- [x] Le modèle de champs avec les règles de visibilité
- [x] La carte du collaborateur
- [x] La base des collaborateurs : liste, recherche, filtre profil + magasin

## Ce qui reste

- [ ] **Badge imprimable 36 × 75 mm** — décidé, contenu à arrêter
- [ ] **Export Excel filtré** — décidé, colonnes à arrêter
- [ ] **Volet RGPD** — voir plus bas
- [ ] **Champs manquants** : téléphone, département
- [ ] **Bitmoji 3D** — le personnage réutilisé dans les jeux three.js de FamiFormation

---

## Les deux fonctionnalités

### 1. Le badge (36 × 75 mm)

Le format est fixé, le contenu non. Les champs autorisés sur un badge sont déjà marqués
`'badge' => true` dans le modèle : prénom, nom, profil, magasin, département. Tout le
reste en est exclu par construction — un badge se perd, se photographie, traîne sur un
comptoir.

Impression au millimètre via `@page { size: 36mm 75mm }` et des dimensions en `mm`
(jamais en pixels : le rendu dépendrait de l'écran).

**À trancher :** un logo ? une photo ? un QR code ? le format est en hauteur (36 large
× 75 haut) ou en largeur ?

### 2. L'export Excel

`phpspreadsheet` est **déjà** dans `Famiformation/vendor/` — rien à installer.

L'export lira **les filtres de `admin.php`**, pour que le fichier contienne exactement ce
qui est à l'écran. C'est la seule façon d'éviter le classique « le fichier ne dit pas la
même chose que la liste ».

**À trancher :** quelles colonnes par défaut ? l'admin les choisit-il ? un export nominatif
laisse-t-il une trace (voir RGPD) ?

---

## RGPD

Ce n'est pas une case à cocher à la fin : Famicard rassemble des données personnelles de
salariés, c'est le cœur du sujet. Ce qui est déjà en place et ce qui manque :

**En place**

- **Minimisation par défaut** — un champ n'est visible que si sa règle l'autorise.
- **Séparation obligatoire / optionnel** — le nécessaire au contrat d'un côté, le
  facultatif de l'autre.
- **Champs exclus du badge** — décidé dans le modèle, pas au moment de l'impression.

**À faire**

- **Base légale** par catégorie : contrat de travail pour l'essentiel, **consentement**
  pour le facultatif (photo, bitmoji, date de naissance).
- **Information** : dire au collaborateur ce qui est stocké, pourquoi, combien de temps.
- **Droits** : accès, rectification, effacement. L'accès et la rectification passent
  naturellement par la carte ; l'effacement demande une décision (que garde-t-on d'un
  collaborateur parti, et combien de temps ?).
- **Durée de conservation** — **rien n'est décidé aujourd'hui**, c'est le vrai trou.
- **Traçabilité** : qui a consulté / exporté la base, et quand. Un export nominatif de
  tout le personnel doit laisser une trace.

---

## Notes techniques

**Ne pas utiliser `verifierConnexion()` ici.** Elle redirige vers `login.php` en relatif,
ce qui depuis `/famicard/` vise `/famicard/login.php` — une page qui n'existe pas. Passer
par `famicardExigeConnexion()`, qui utilise un chemin absolu.

**Deux dispositions coexistent.** Dans le conteneur, FamiFormation est à la racine servie
et Famicard dans `/famicard/`. Dans le dépôt, ce sont deux dossiers frères. `config.php`
essaie les deux chemins au lieu d'en supposer un.

**`Famiformation/` et `Famijob/` sont une copie conforme du live** : ne rien y modifier
pour Famicard sans le dire, ça casserait la conformité.

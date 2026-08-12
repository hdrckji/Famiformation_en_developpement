# Les variables Railway — et la règle qui les impose

> **Aucune information sur une personne dans le code.** Décision de Jimmy, valable pour
> tout le dépôt : Famiformation, FamiJob, Famicard, quiz.

## Pourquoi

Un nom ou une adresse écrits dans le code **survivent à la personne**. Quelqu'un part,
change de poste, ou le projet passe à quelqu'un d'autre — et les mails continuent de
partir dans une boîte que plus personne ne relève, les écrans continuent d'inviter à
« passer voir » quelqu'un qui n'est plus là. Rien ne casse, donc personne ne le voit.

Il faut alors retrouver le nom dans le code, le modifier, redéployer. Dans une variable,
c'est trente secondes et aucun déploiement — et **aucun nom ne traîne dans l'historique
git**, ce qui compte aussi côté RGPD.

## ⚠️ La règle du défaut vide

Une valeur de repli nominative écrite dans le code **annule tout le bénéfice** : elle
continue de fonctionner après le départ, en silence. Donc :

**Pas de variable ⇒ rien n'est envoyé, et l'écran le dit.** Une panne visible vaut mieux
qu'un mail qui part au mauvais endroit pendant six mois.

Les seules exceptions sont les replis qui désignent une **fonction** et non quelqu'un :
`famiContactRh()` retombe sur « le service RH », ce qui reste vrai quoi qu'il arrive.

---

## À poser dans Railway → Variables

### Les personnes (⚠️ sans elles, ces fonctions se taisent)

| Variable | À quoi ça sert | Si absente |
|---|---|---|
| `CONTACT_RH` | Le nom affiché dans les mails et les alertes (« écris un mail à … ») | affiche « le service RH » |
| `MAIL_ADMIN` | Destinataire des rapports automatiques (disponibilités, formations) et des tests SMTP | **aucun rapport envoyé** |
| `MAIL_ACCUEIL` | Adresse prévenue d'une inscription à une formation | **aucune notification** |
| `RH_NOTIF_MAIL` | Adresse prévenue quand quelqu'un devient éligible à une récompense (quiz) | **aucune notification** |
| `MAIL_TO` | Destinataire du formulaire « créateur de contenu » (`/volontaire`) | **le formulaire refuse poliment** |
| `MAIL_RELAIS_VOLONTAIRE` | Qui l'on invite à aller voir sur cette même page | la phrase disparaît |
| `FAMIJOB_HORAIRE_MAIL_FAMIFLORA` | Où partent les horaires des collaborateurs **internes** | filet seulement : la ligne « Famiflora » de `interim_agences` fait foi d'abord |
| `FAMIJOB_HORAIRE_CONTACT_FAMIFLORA` | Le nom affiché en face de cette adresse | aucun nom affiché |

### Les envois d'horaires FamiJob

| Variable | À quoi ça sert | Défaut |
|---|---|---|
| `FAMIJOB_HORAIRE_MAIL_TEST` | `1` = tout est redirigé vers l'adresse de test ; `0` = envoi réel | **`1`** ⚠️ |
| `FAMIJOB_HORAIRE_MAIL_TEST_TO` | L'adresse qui reçoit tout en mode test | vide |

> 🚨 **`FAMIJOB_HORAIRE_MAIL_TEST` vaut `1` par défaut.** Tant qu'elle n'est pas posée à
> `0`, **aucun horaire ne part vers les personnes ni vers les agences** — tout est
> redirigé. Et depuis que l'adresse en dur a été retirée, sans
> `FAMIJOB_HORAIRE_MAIL_TEST_TO` **plus rien ne part du tout**. L'écran d'envoi le dit
> maintenant en toutes lettres, mais c'est le premier réglage à vérifier.

### Famicard

| Variable | À quoi ça sert | Si absente |
|---|---|---|
| `FAMICARD_MDP_IDENTIFIANT` | Le mot de passe qui ouvre le cadenas du champ « identifiant » | **le champ reste verrouillé pour tout le monde** |

### Le reste (déjà en place)

`APP_URL`, `APP_TIMEZONE`, `APP_DEBUG`, `SESSION_TIMEOUT` ·
`DB_HOST`, `DB_HOST_FALLBACK`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` ·
`PLANNING_DB_*`, `PLANNING_DEPARTMENTS_*` ·
`SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME` ·
`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GROQ_API_KEY`, `FAMI_STT_PROVIDER`, `MYMEMORY_EMAIL` ·
`QUIZ_DB_DSN`, `QUIZ_DB_USER`, `QUIZ_DB_PASS`, `QUIZ_ADMIN_PWD` ·
`CRON_TOKEN`, `FORM_FEED_URL`, `FORM_FEED_SECRET`, `MODULE_LOCK_PASSWORD`,
`LAPANNE_RH_PASSWORD`, `RAILWAY_VOLUME_MOUNT_PATH`

---

## Ce qui n'est PAS réglé par une variable

Deux fichiers du dépôt contiennent des **données personnelles**, et une variable
d'environnement n'est pas la bonne réponse pour eux — c'est trop gros et ça se met à jour
trop souvent. Leur place est en base de données.

| Fichier | Ce qu'il contient | Pourquoi c'est un problème |
|---|---|---|
| `Famiformation/includes/personnel_liste.php` | **270 personnes** : nom, prénom, société | versionné dans git, déployé avec le site, à régénérer à chaque arrivée ou départ |
| `Famiformation/evaluations_stock.php` | des **évaluations nominatives** avec commentaires (« Manque de peps… ») | des jugements sur des personnes nommées, dans l'historique git |

Ce sont des chantiers à part, à traiter avec le volet RGPD.

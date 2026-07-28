# FamiFormation - Documentation & Guide d'Installation

## 📋 Vue d'ensemble

**FamiFormation** est une plateforme de formation e-learning destinée à Famiflora (jardinerie/animalerie). Elle permet aux collaborateurs et clients de :

- Suivre des formations en ligne (thèmes : caisse, jardinage, produits, etc.)
- Passer des quiz validant les apprentissages
- Suivre leur progression et leurs points
- Consulter l'administration (pour les admins)

## 🚀 Installation & Déploiement

### Prérequis
- **PHP** 7.4+ avec PDO MySQL
- **MySQL** 5.7+
- Hébergement **IONOS** (configuré)

### Configuration

#### 1. Cloner le projet
```bash
git clone <votre-repo>
cd Mon\ project
```

#### 2. Créer le fichier `.env`
Copier `.env.example` en `.env` et mettre à jour les identifiants :

```bash
cp .env.example .env
```

Puis éditer `.env` avec vos paramètres IONOS :
```
DB_HOST=localhost
DB_NAME=nom_de_la_base
DB_USER=utilisateur_db
DB_PASSWORD=mot_de_passe_db

SESSION_TIMEOUT=900
APP_DEBUG=false
```

**IMPORTANT :** Ne jamais commiter `.env` en git. Le `.gitignore` l'active automatiquement.

#### 3. Base de données
Importer le dump SQL fourni :
```bash
mysql -h votre_hote_mysql -u votre_utilisateur -p votre_base < backup.sql
```

#### 4. Déployer sur IONOS
- Via FTP : Upload tous les fichiers (sauf `.env` local, il faut le créer sur le serveur)
- Via Git : Clone directement sur le serveur et créer `.env`

---

## 📂 Structure du Projet

```
Mon project/
├── config.php                    # Configuration centrale (DB, sessions, includes)
├── login.php                     # Authentification
├── index.php                     # Accueil / Tableau de bord
├── logout.php                    # Déconnexion
├── admin.php                     # Administration (gestion utilisateurs)
├── admin_formations.php          # Gestion des formations (admin)
├── admin_questions.php           # Gestion des questionnaires (admin)
│
├── formation.php                 # Formations présentielles
├── formation_becosoft.php        # Formation Becosoft
├── formation-caisse.php          # Formation Caisse
│
├── quiz_engine.php               # Moteur de quiz interactif
├── garden.php                    # Thème Garden (pilotage)
├── green.php                     # Thème Green
├── becosoft.php                  # Thème Becosoft
├── food.php                      # Thème Alimentation
├── deco.php                      # Thème Décoration
│
├── classement.php                # Classement des utilisateurs
├── export_excel.php              # Export Excel des stats
├── export_formations.php         # Export formations
│
├── includes/
│   ├── functions.php             # Fonctions communes (helpers)
│   └── csrf.php                  # Protection CSRF
│
├── assets/
│   ├── logo.png
│   ├── beco.png
│   ├── background.jpg
│   └── *.pdf                     # Documents PDF
│
├── .env.example                  # Configuration exemple (à copier)
├── .gitignore                    # Fichiers à ignorer
└── README.md                     # Ce fichier
```

---

## 🔒 Sécurité Améliorée

### 1. **Variables d'Environnement**
Les identifiants BD sont maintenant externalisés dans `.env` au lieu d'être en dur dans le code.

```php
// Avant (DANGEREUX)
$host = "localhost";
$pass = "mot_de_passe_en_dur";

// Après (Sécurisé)
$host = getEnv('DB_HOST');
$pass = getEnv('DB_PASSWORD');
```

### 2. **Protection CSRF**
Tous les formulaires ont un token CSRF qui valide les soumissions.

```php
// Dans les formulaires HTML
<?php echo csrfField(); ?>

// Validation automatique en PHP
requireValidCSRF(); // Dans les pages avec POST
```

### 3. **Session Timeout**
Les sessions expirent après 15 minutes d'inactivité (configurable dans `.env`).

### 4. **Hash Bcrypt**
Les mots de passe sont hashés avec `password_hash()` et validés avec `password_verify()`.

### 5. **PDO Prepared Statements**
Toutes les requêtes SQL utilisent des paramètres liés (protection SQL injection).

---

## 📚 Fonctions Communes

Le fichier `includes/functions.php` contient des helpers réutilisables :

```php
// Utilisateur actuel
$user_id = getCurrentUserId();
$role = getCurrentRole();
$is_admin = isAdmin();

// Échappement HTML sécurisé
echo e($user_input); // htmlspecialchars()

// Validation
isValidEmail($email);
isValidUsername($username);
isValidPassword($password);

// Formatage
formatDuration(3661);  // "1h 1m"
formatDate($date);     // "01/03/2026"

// Tokens sécurisés
$token = generateToken(32);
```

---

## 🔐 Protection CSRF

La protection CSRF est automatique pour tous les POST :

```php
// Dans config.php : initCSRF() appelé automatiquement
// Dans les pages POST : requireValidCSRF() valide

// Dans les formulaires HTML :
<form method="POST">
    <?php echo csrfField(); ?>
    <input type="text" name="data">
    <button type="submit">Envoyer</button>
</form>
```

Si un token CSRF est invalide, la page retourne une erreur 403.

---

## 📖 Utilisation des Fonctions Helper

### Vérification de Connexion & Rôles
```php
require_once 'config.php';

// Redirection auto au login si pas connecté
verifierConnexion($db);

// Redirection si pas admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}
// OU (helper)
requireAdmin();
```

### Récupérer l'User Actuel
```php
$user_id = getCurrentUserId();
$role = getCurrentRole();

if (isAdmin()) {
    // Actions admin
}
```

### Délai Inactivité
```php
// Configuré dans .env
SESSION_TIMEOUT=900  // 15 minutes (en secondes)
```

---

## 🛠️ Améliorations Apportées

### ✅ Sécurité
- [x] Variables d'environnement (`.env`) pour identifiants BD
- [x] Protection CSRF sur tous les formulaires
- [x] `.gitignore` pour éviter fuites sensibles
- [x] PDO paramètres liés (déjà présent)
- [x] Hash bcrypt pour mots de passe
- [x] Session timeout + httponly cookies

### ✅ Maintenabilité
- [x] Fonctions communes dans `includes/functions.php`
- [x] Helpers réutilisables (`e()`, `formatDate()`, etc.)
- [x] Structure de dossiers claire
- [x] Code commenté et documenté

### ✅ Documentation
- [x] README.md (ce fichier)
- [x] `.env.example` pour configuration
- [x] Docstrings PHP dans les fonctions

---

## 🚨 À Faire Avant Production

- [ ] Tester tous les formulaires avec CSRF
- [ ] Vérifier que `.env` n'est pas committé en git
- [ ] Configurer HTTPS sur IONOS (secure=true dans config)
- [ ] Activer `APP_DEBUG=false` en production
- [ ] Tester l'import de la base de données
- [ ] Configurer les sauvegardes BD automatiques

---

## 📞 Support & Maintenance

### Logs & Erreurs
En développement, les erreurs s'affichent. En production (`APP_DEBUG=false`), elles sont loggées (infrastructure IONOS).

### Modifier Configuration
Éditer `.env` directement (pas besoin de redéployer le code) :
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` : Identifiants BD
- `SESSION_TIMEOUT` : Durée avant expiration session (en secondes)
- `APP_DEBUG` : Affichage erreurs en développement

### Déboguer
```bash
# Vérifier que .env se charge
php -r "require 'includes/functions.php'; loadEnv('.env'); var_dump(getEnv('DB_HOST'));"

# Tester connexion BD
php -r "require 'config.php'; echo 'BD OK';"
```

---

## 📄 Licence

Interne à Famiflora. Non, lisez le plus.

---

**Dernière mise à jour :** 1er Mars 2026  
**Version :** 2.0 (Améliorations Sécurité & Architecture)

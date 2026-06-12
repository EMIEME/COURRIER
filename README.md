# Gestion Courrier

Application Symfony de gestion des courriers entrants, sortants et internes. Le projet centralise l'enregistrement des courriers, leur imputation aux utilisateurs, le suivi des echeances, les pieces jointes, les destinataires et l'historique des actions.

## Fonctionnalites

- Tableau de bord des courriers.
- Authentification par formulaire avec roles `ROLE_ADMIN`, `ROLE_SECRETARIAT` et `ROLE_USER`.
- Gestion des courriers entrants, sortants et notes internes.
- Filtrage, consultation, creation, modification et suppression controlee des courriers.
- Imputation de courriers a un ou plusieurs utilisateurs avec notification email.
- Suivi des statuts : `en_cours`, `traite`, `urgent`.
- Passage automatique en urgent des courriers dont l'echeance de reponse est depassee.
- Gestion des destinataires et contacts.
- Parametrage des listes de valeurs depuis l'administration.
- Export des courriers en Excel ou PDF.
- Historique des actions sur chaque courrier.
- Sauvegarde SQL de base MySQL/MariaDB via une commande Symfony.

## Stack technique

- PHP `>= 8.4`
- Symfony `8.0.*`
- Doctrine ORM et Doctrine Migrations
- Twig
- Symfony Security, Form, Mailer et Validator
- Composer
- MySQL/MariaDB pour la configuration locale actuelle
- Vercel PHP runtime pour le deploiement defini dans `vercel.json`

## Prerequis

- PHP 8.4 ou plus recent.
- Composer.
- Une base de donnees MySQL ou MariaDB accessible localement.
- `mysqldump` si vous voulez utiliser la commande de sauvegarde.
- Symfony CLI optionnel, utile pour lancer le serveur local.

> Note : `compose.yaml` contient un service PostgreSQL genere par Symfony, mais les migrations presentes dans `migrations/` utilisent du SQL MySQL/MariaDB. Verifiez l'alignement de `DATABASE_URL`, de la base utilisee et des migrations avant de demarrer avec Docker Compose.

## Installation locale

1. Installer les dependances PHP :

```bash
composer install
```

2. Creer ou completer le fichier `.env.local` :

```dotenv
APP_SECRET=change-me
DATABASE_URL="mysql://user:password@127.0.0.1:3306/gestioncourrier?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
APP_MAIL_FROM_ADDRESS=no-reply@example.com
APP_MAIL_FROM_NAME="Gestion Courrier"
APP_MAIL_REPLY_TO_ADDRESS=no-reply@example.com
```

3. Creer la base de donnees :

```bash
php bin/console doctrine:database:create
```

4. Appliquer les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

5. Creer un premier utilisateur administrateur :

```bash
php bin/console app:create-user admin@example.com "mot-de-passe" --name="Administrateur" --role=ROLE_ADMIN
```

6. Lancer l'application :

```bash
symfony server:start
```

Ou, sans Symfony CLI :

```bash
php -S 127.0.0.1:8000 -t public
```

L'application est ensuite accessible sur `http://127.0.0.1:8000`.

## Commandes utiles

Lister les routes :

```bash
php bin/console debug:router
```

Creer un utilisateur :

```bash
php bin/console app:create-user utilisateur@example.com "mot-de-passe" --name="Nom Utilisateur" --role=ROLE_SECRETARIAT
```

Marquer comme urgents les courriers en cours dont l'echeance est depassee :

```bash
php bin/console app:courriers:mark-urgent
```

Generer une sauvegarde SQL MySQL/MariaDB :

```bash
php bin/console app:database:backup --dir=var/backups/database --keep-days=30
```

Nettoyer le cache Symfony :

```bash
php bin/console cache:clear
```

## Structure du projet

```text
api/                 Point d'entree Vercel
bin/                 Commandes Symfony
config/              Configuration Symfony
migrations/          Migrations Doctrine
public/              Racine web publique
public/uploads/      Pieces jointes des courriers
src/Command/         Commandes applicatives
src/Controller/      Controleurs HTTP
src/Entity/          Entites Doctrine
src/Form/            Formulaires Symfony
src/Repository/      Requetes Doctrine
src/Service/         Services metier
templates/           Vues Twig
var/                 Cache, logs et fichiers generes
```

## Variables d'environnement principales

- `APP_ENV`
- `APP_SECRET`
- `APP_SHARE_DIR`
- `DEFAULT_URI`
- `DATABASE_URL`
- `DATABASE_BACKUP_DIR`
- `DATABASE_BACKUP_RETENTION_DAYS`
- `MAILER_DSN`
- `APP_MAIL_FROM_ADDRESS`
- `APP_MAIL_FROM_NAME`
- `APP_MAIL_REPLY_TO_ADDRESS`

Les valeurs locales sensibles doivent rester dans `.env.local`, qui est ignore par Git.

## Deploiement Vercel

Le fichier `vercel.json` configure :

- le runtime `vercel-php@0.9.0` sur `api/index.php` ;
- une redirection de toutes les routes vers `api/index.php` ;
- des variables d'environnement de production ;
- une base SQLite dans `/tmp/gestioncourrier/data_prod.db`.

Le stockage dans `/tmp` sur une plateforme serverless peut etre temporaire. Pour une utilisation en production avec donnees persistantes, prevoyez une base externe adaptee.

## Verification

Aucun repertoire de tests n'est present dans le projet pour le moment. Les verifications minimales recommandees apres modification sont :

```bash
php bin/console lint:container
php bin/console doctrine:migrations:status
```

## Notes Git

Les dossiers et fichiers suivants ne doivent pas etre versionnes :

- `.env.local`
- `.vercel/`
- `vendor/`
- `var/`
- les fichiers envoyes dans `public/uploads/courriers/`
- les dumps SQL locaux


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
> Si vous utilisez Docker Compose, fournissez `MARIADB_PASSWORD` et `MARIADB_ROOT_PASSWORD` via votre shell ou avec `docker compose --env-file .env.local up`.

## Installation locale

1. Installer les dependances PHP :

```bash
composer install
```

2. Creer le fichier `.env.local` a partir du modele, puis remplacer les valeurs :

```bash
cp .env.local.example .env.local
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copier la valeur generee dans `APP_SECRET`, puis renseigner `DATABASE_URL`,
`MAILER_DSN` et les adresses email dans `.env.local`.

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
var/uploads/         Pieces jointes des courriers, servies via Symfony
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

Les valeurs sensibles doivent rester dans `.env.local`, `.env.*.local` ou dans
les variables d'environnement du serveur. Ces fichiers locaux sont ignores par
Git. Les valeurs presentes dans `.env` et `.env.local.example` sont uniquement
des placeholders ou exemples non secrets.

Pour la production, definir au minimum `APP_SECRET`, `DATABASE_URL`,
`MAILER_DSN`, `APP_MAIL_FROM_ADDRESS` et `APP_MAIL_FROM_NAME` dans la plateforme
de deploiement. Si une ancienne valeur de `APP_SECRET` suivie par Git a ete
utilisee en production, la remplacer par une nouvelle valeur aleatoire et
forcer la deconnexion des sessions existantes si necessaire. L'application
refuse de demarrer en production si `APP_SECRET` ou `DATABASE_URL` contient
encore une valeur placeholder.

## Deploiement Vercel

Le fichier `vercel.json` configure :

- le runtime `vercel-php@0.9.0` sur `api/index.php` ;
- une redirection de toutes les routes vers `api/index.php` ;
- des variables d'environnement de production ;
- une base SQLite dans `/tmp/gestioncourrier/data_prod.db`.

Le stockage dans `/tmp` sur une plateforme serverless peut etre temporaire. Pour une utilisation en production avec donnees persistantes, prevoyez une base externe adaptee.

## Deploiement VPS avec Docker Compose

Le projet contient une configuration de production pour VPS :

- `Dockerfile` : image PHP 8.4 avec Apache, Composer et extensions Symfony/MySQL.
- `compose.prod.yaml` : application Symfony, MariaDB et Caddy.
- `docker/caddy/Caddyfile` : HTTPS automatique via Caddy.
- `.env.prod.local.example` : modele de variables de production sans secrets.

Sur le VPS, copier `.env.prod.local.example` vers `.env.prod.local`, puis remplacer
les valeurs sensibles :

```bash
cp .env.prod.local.example .env.prod.local
```

Demarrer ou mettre a jour la production :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --build
docker compose --env-file .env.prod.local -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
```

Verifier l'etat des conteneurs :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml ps
```

## Deploiement VPS avec Nginx et PHP-FPM

Si le VPS dispose deja de Nginx, PHP 8.4-FPM, Composer et MariaDB, la
configuration Nginx de reference est disponible dans :

```text
deploy/nginx-gestioncourrier.conf
```

Apres synchronisation du code dans `/var/www/gestioncourrier`, activer le site :

```bash
sudo cp /var/www/gestioncourrier/deploy/nginx-gestioncourrier.conf /etc/nginx/sites-available/gestioncourrier
sudo ln -sfn /etc/nginx/sites-available/gestioncourrier /etc/nginx/sites-enabled/gestioncourrier
sudo nginx -t
sudo systemctl reload nginx
```

Puis verifier avec :

```bash
curl -H "Host: ddadoc.org" http://127.0.0.1/
```

## Verification

Aucun repertoire de tests n'est present dans le projet pour le moment. Les verifications minimales recommandees apres modification sont :

```bash
php bin/console lint:container
php bin/console doctrine:migrations:status
```

## Notes Git

Les dossiers et fichiers suivants ne doivent pas etre versionnes :

- `.env.local`
- `.env.*.local`
- `.vercel/`
- `vendor/`
- `var/`
- les fichiers envoyes dans `var/uploads/courriers/`
- les dumps SQL locaux

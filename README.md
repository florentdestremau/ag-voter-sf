# AG-Voter

Application de vote en temps réel pour les assemblées générales.

L'admin crée des sessions et des questions, les participants rejoignent via un lien unique et votent depuis leur mobile ou ordinateur. Les résultats s'affichent en direct sans rechargement de page (Turbo Frame polling).

## Stack

- **Symfony 8** + SQLite (Doctrine ORM)
- **Symfony UX** : Turbo (frames polling) + Stimulus.js
- **Bootstrap 5**
- Auth admin : HTTP Basic

## Prérequis

- PHP 8.4+
- Composer
- Extension `pdo_sqlite`

## Installation

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction  # données de démo
symfony serve
```

L'interface admin est accessible sur `/admin` (identifiants : `admin` / `admin123`).

## Utilisation

1. **Admin** — créer une session sur `/admin`, copier le lien de session `/s/{token}` à partager
2. **Participants** — rejoindre via le lien, saisir son nom, voter sur les questions actives
3. **Admin** — activer/fermer les questions, suivre les votes en direct, récupérer le lien personnel d'un participant en cliquant sur son nom

## Lancer les tests

```bash
php bin/phpunit
```

## Linters

```bash
vendor/bin/php-cs-fixer fix          # corrige le style PHP
vendor/bin/twig-cs-fixer lint templates/  # vérifie les templates Twig
```

## CI

GitHub Actions lance automatiquement les linters et les tests sur chaque push.

## Binaire autonome (FrankenPHP embed)

Build d'un binaire Linux x86_64 unique qui embarque PHP, Caddy et toute l'app :

```bash
bin/build-static.sh
```

Produit `dist/ag-voter` (~230 Mo, binaire statique) accompagné de `Caddyfile` et `run.sh`. Le wrapper est nécessaire parce que FrankenPHP extrait l'embed dans `/tmp/frankenphp_<hash>/` et Caddy a besoin de connaître ce chemin pour servir les assets ; `run.sh` le découvre et l'expose via `$AG_VOTER_EMBED_DIR`.

```bash
dist/ag-voter php-cli bin/console doctrine:migrations:migrate --no-interaction
SERVER_NAME=':8080' dist/run.sh
```

La base SQLite est créée dans `dist/data/app.db`. Variables d'env utiles : `SERVER_NAME`, `MERCURE_JWT_SECRET`, `ADMIN_PASSWORD_HASH`, `APP_SECRET`, `APP_CACHE_DIR`, `APP_LOG_DIR`.

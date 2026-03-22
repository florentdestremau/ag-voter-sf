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

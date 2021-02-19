# Installation et déploiement

> Dépôt GitHub : [https://github.com/IGNF/validator-api](https://github.com/IGNF/validator-api)

## Prérequis

- Une distribution Linux (de préférence basée sur Debian) pour la machine hôte
- Git
- PHP 7.3\* (extensions : pgsql)
- Yarn\*
- Serveur web\*
- Zip/unzip\*
- Curl\*
- Java SE >= 11\*
- ogr2ogr >= 2.3.0\*

> Une configuration docker est fournie dans ce dépôt. Si vous décidez de l'utiliser, vous n'avez pas à installer les outils mentionnés ci-dessus avec un astérisque.
>
> - [Docker Engine](https://docs.docker.com/engine/install/)
>   - [Configuration du serveur proxy pour docker](https://docs.docker.com/network/proxy/)

## Étapes

> L'étape n°1 concerne uniquement ceux qui décident de dockeriser l'application.

1. Lancer les conteneurs docker : `docker-compose up -d --build --scale backend=2`
2. Créer à la racine du projet un fichier nommé `.env.local` contenant les informations suivantes.

```ini
APP_ENV=dev

DATABASE_URL=postgresql://db_user:db_password@db_host:db_port/db_name?serverVersion=10&charset=utf8

POSTGRES_USER=db_user
POSTGRES_PASSWORD=db_password
POSTGRES_DB=db_name

http_proxy=
https_proxy=
HTTP_PROXY=
HTTPS_PROXY=

CORS_ALLOW_ORIGIN=
```

> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

> Préfixe pour les commandes suivantes : `docker exec -it validator-api_backend_1 ...`

> Les commandes spécifiques à l'environnement de production sont en _italique_

3. Installer les dépendances PHP : `composer update` _ou `composer update --no-dev`_
4. Installer les dépendances JavaScript : `yarn install` _ou `yarn install --production`_
5. Créer la base de données : `php bin/console doctrine:database:create --if-not-exists`
6. Mettre à jour le schéma de la base de données : `php bin/console doctrine:migrations:migrate --no-interaction`
7. Compiler les assets: `yarn encore dev` _ou `yarn encore production --progress`_
8. Télécharger l'outil Validator CLI (jar) : `./download-validator.sh <VALIDATOR_VERSION>`
9. Vérifier la configuration des arguments du Validator : `php bin/console app:args-config-check`

> La version actuelle de l'API est compatible avec validator v4.0.4.

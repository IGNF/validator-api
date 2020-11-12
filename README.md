# validator-api

# A propos

L'APIsation de l'outil [Validator](https://github.com/IGNF/validator), developpé par [IGNfab (IGN)](https://www.ign.fr/ignfab), permet de valider et de normaliser les données présentes dans une arborescence de fichiers. [En savoir plus](https://github.com/IGNF/validator).

# Installation/déploiement

## Prérequis

 * Une distribution Linux (de préference basée sur Debian) pour la machine hôte
 * Git
 * [Docker Engine](https://docs.docker.com/engine/install/)
   * [Configuration du serveur proxy pour docker](https://docs.docker.com/network/proxy/)

## Docker-Compose

<img src="doc/images/architecture.svg" alt="architecture"/>

L'application est composée de :
```yml
# docker-compose.yml
...
services:
  backend: # image docker personalisée pour cette application symfony. Voir le dockerfile pour plus d'informations
    build:
      context: .
      dockerfile: .docker/backend.dockerfile
    ...
    networks:
      - symfony

  postgres: # instance de base de données postgres, construite à partir de l'image docker officielle
    image: postgres:10
    ...
    networks:
      - symfony

  nginx:  # nginx pour le loadbalancing des instances multiples du "backend", construit à partir de l'image docker officielle
    image: nginx:latest
    volumes:
      - ./.docker/nginx.conf:/etc/nginx/nginx.conf # fichier de configuration de nginx
    networks:
      - symfony
    depends_on:
      - backend

  scheduler:  # un outil comme crontab pour docker, on s'en sert pour programmer des tâches dans des conteneurs docker, construit à partir de l'image docker officielle
    image: mcuadros/ofelia:latest
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./.docker/scheduler-config.ini:/etc/ofelia/config.ini
    depends_on:
      - backend

networks:
  symfony: # tous les services font partie du même réseau virtuel

volumes:
  db-data: # la persistence de données de la base de données sur la machine hôte, ça permet d'éviter la perte de données même après la suppression du conteneur
...
```
> Voir le fichier [docker-compose.yml](docker-compose.yml) complet pour plus de précisions.  
> [En savoir plus sur le scheduler](https://github.com/mcuadros/ofelia).

## Étapes

1. Lancer les conteneurs docker : `docker-compose up -d --build --scale backend=2`
> Actuellement l'application est configurée de fonctionner avec 2 instances de "backend". [Voir ici comment le modifier](doc/md/backend_multiple_instances.md).
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
```
> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

> Préfixe pour les commandes suivantes : `docker exec -it validator-api_backend_1 ...`  
> Les commandes spécifiques à l'environnement de production sont en *italique*

3. Installer les dépendances PHP : `composer update` *ou `composer update --no-dev`*
4. Installer les dépendances JavaScript : `yarn install` *ou `yarn install --production`*
5. Créer la base de données : `php bin/console doctrine:database:create --if-not-exists`
6. Mettre à jour le schéma de la base de données : `echo yes | php bin/console doctrine:migrations:migrate`
7. Compiler les assets: `yarn encore dev` *ou `yarn encore production --progress`*

# Tests

## Docker-Compose

Vu qu'on teste uniquement l'application Symfony, il suffit d'avoir ces 2 conteneurs : une pour l'application Symfony et une pour la base de données.

```yml
# docker-compose.test-yml
...
services:
  backend_test:
    build:
      context: .
      dockerfile: .docker/backend.dockerfile
    env_file:
      - .env.test
    networks:
      - symfony

  postgres_test:
    image: postgres:10
    env_file:
      - .env.test
    ...
    networks:
      - symfony
...
```
> Voir le fichier [docker-compose.test.yml](docker-compose.test.yml) complet pour plus de précisions.  

## Étapes

1. Lancer les conteneurs docker : `docker-compose -f docker-compose.test.yml up -d --build`
2. Créer à la racine du projet un fichier nommé `.env.test` contenant les informations suivantes. La variable `APP_DEV` doit obligatoirement valoir `test`.

```ini
APP_ENV=test

DATABASE_URL=postgresql://db_user:db_password@db_host:db_port/db_name?serverVersion=10&charset=utf8

POSTGRES_USER=db_user
POSTGRES_PASSWORD=db_password
POSTGRES_DB=db_name

http_proxy=
https_proxy=
HTTP_PROXY=
HTTPS_PROXY=
```
> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

> Préfixe pour les commandes suivantes : `docker exec -it validator-api_backend_test_1 ...`

3. Installer les dépendances PHP : `composer update`
4. Créer la base de données : `php bin/console --env=test doctrine:database:create --if-not-exists`
5. Mettre à jour le schéma de la base de donnnées : `php bin/console --env=test doctrine:migrations:migrate --no-interaction`
6. Lancer les tests : `vendor/bin/simple-phpunit`
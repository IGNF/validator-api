# Tests

## Données de test

Dataset : // TODO

Config : https://ocruze.github.io/fileserver/config/cnig_CC_2017.json

## Composition des conteneurs Docker

Vu qu'on teste uniquement l'application Symfony, il suffit d'avoir ces 2 conteneurs : une pour l'application Symfony et une pour la base de données.

```yml
# docker-compose.test-yml
...
services:
  backend_test:
    container_name: validator-api_backend_test_1
    build:
      context: .
      dockerfile: .docker/backend.dockerfile
    env_file:
      - .env.test
    ...
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

> Voir le fichier [docker-compose.test.yml](https://github.com/IGNF/validator-api/blob/master/docker-compose.test.yml) complet pour plus de précisions.

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

SONAR_HOST_URL=
SONAR_TOKEN=
```

?> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

!> Préfixe pour les commandes suivantes : `docker exec -it validator-api_backend_test_1 ...`

3. Installer les dépendances PHP : `composer update`
4. Créer la base de données : `php bin/console --env=test doctrine:database:create --if-not-exists`
5. Mettre à jour le schéma de la base de donnnées : `php bin/console --env=test doctrine:migrations:migrate --no-interaction`
6. Télécharger l'outil Validator CLI (jar) : `./download-validator.sh <VALIDATOR_VERSION>`
7. Vérifier la configuration des arguments du Validator : `php bin/console --env=test app:args-config-check`
8. Lancer les tests : `vendor/bin/simple-phpunit`

## Analyse de code par Sonarqube

> Fichier de configuration de projet Sonarqube : `sonar-project.properties`

!> Les variables d'environnement `SONAR_HOST_URL` et `SONAR_TOKEN` sont requises par Sonarqube

!> Préfixe pour les commandes suivantes : `docker exec -it validator-api_backend_test_1 ...`

- Installer sonar-scanner en local : `./download-sonar-scanner.sh`
- Lancer l'analyse de code : `sonar-scanner/bin/sonar-scanner`

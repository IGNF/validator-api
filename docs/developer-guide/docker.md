# Documentation développeur pour développement avec docker

## Prérequis

* docker
* docker compose

## Principes

* L'image est définie par le fichier [Dockerfile](../../Dockerfile), en plusieurs étapes :
  * `composer` : dépendances PHP (sans les dépendances de dev, sauf `--build-arg COMPOSER_NO_DEV=0`) ;
  * `assets` : front de démonstration construit avec webpack (voir [front.md](front.md)) ;
  * image finale (Ubuntu, Apache, PHP, Java, GDAL et `validator-cli.jar` installé par [bin/install-validator.sh](../../bin/install-validator.sh)).
* L'image est construite par GitHub actions et publiée sur GitHub container registry.
* Le script [bin/application.sh](../../bin/application.sh) est le point d'entrée de l'image :
  * `run` (défaut) : API (apache2) ;
  * `backend` : traitement des validations en boucle ([bin/loop-validate.sh](../../bin/loop-validate.sh)) ;
  * `archive` : archivage des validations expirées ([bin/archive.sh](../../bin/archive.sh)) ;
  * `test` : exécution des tests (image construite avec `COMPOSER_NO_DEV=0`).
* [docker-compose.yml](../../docker-compose.yml) définit les services `api`, `worker` et `database` (PostGIS).
* [compose.override.yaml](../../compose.override.yaml), chargé automatiquement par `docker compose`, contient les réglages de développement : `APP_ENV=dev` et exposition de PostgreSQL sur `127.0.0.1:5432`.

## Paramètrage

Le paramétrage de l'application est réalisé via des variables d'environnements. Voir [.env](../../.env) servant de modèle.

Le script [bin/application.sh](../../bin/application.sh) comporte des options spécifiques au démarrage de l'API :

* `DB_CREATE` à définir à 0 ou 1 pour créer automatiquement la base de données
* `DB_UPGRADE` à définir à 0 ou 1 pour mettre à jour automatiquement la structure

## Construction et démarrage de l'application

```bash
git clone https://github.com/IGNF/validator-api.git
cd validator-api
# Construction de l'image docker (derrière un proxy, les variables http_proxy/https_proxy sont transmises)
docker compose build
# Démarrage de la stack de développement
docker compose up -d
# Ouvrir http://localhost:8000 avec un navigateur
```

## Exécution des tests

```bash
# image avec les dépendances de dev
docker compose build --build-arg COMPOSER_NO_DEV=0
docker compose up -d
# Exécution des tests dans l'image docker
docker compose exec api bin/application.sh test
```

Pour tester via l'interface :

* Ouvrir http://localhost:8000
* Choisir le fichier [tests/data/cnig-pcrs-lyon-01-3946.zip](../../tests/data/cnig-pcrs-lyon-01-3946.zip)
* Choisir la projection EPSG:3946
* Valider et attendre le résultat

## Quelques commandes utiles pour le debug

* Visualiser les logs du worker : `docker compose logs -f worker`
* Ouvrir un terminal dans le conteneur : `docker compose exec api /bin/bash`
* Lister les fichiers : `docker compose exec api find var/data/validations`
* Suivre un traitement particulier : `docker compose exec worker tail -f var/data/validations/${VALIDATION_ID}/validator-debug.log`
* Archiver les validations expirées : `docker compose exec worker bin/application.sh archive`

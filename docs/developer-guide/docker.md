# Documentation développeur pour développement avec docker

## Prérequis

* docker
* docker compose

## Principes

* L'image est définie par le fichier [.docker/Dockerfile](../../.docker/Dockerfile) avec deux "targets" : 
  * `prod` construite avec GitHub actions et stockée sur GitHub container registry
  * `dev` (incluant xdebug) construite et utilisée par [docker-compose.yml](../../docker-compose.yml)
* Un script utilitaire [.docker/application.sh](../../.docker/application.sh) est intégré à l'image pour simplifier l'initialisation de la base et l'exécution des tests en environnement de développement.

## Paramètrage

Le paramétrage de l'application est réalisé via des variables d'environnements. Voir [.env](../../.env) servant de modèle.

Le script [.docker/application.sh](../../.docker/application.sh) comporte des options spécifiques au démarrage du service :

* `DB_CREATE` à définir à 0 ou 1 pour créer automatiquement la base de données
* `DB_UPGRADE` à définir à 0 ou 1 pour mettre à jour automatiquement la structure

## Construction et démarrage de l'application

```bash
git clone https://github.com/IGNF/validator-publi.git
cd validator-publi
# Construction de l'image docker
docker compose build
# Démarrage de la stack de développement
docker compose up -d
# Ouvrir http://localhost:8000 avec un navigateur
```

## Exécution des tests

```bash
# Exécution des tests dans l'image docker
docker compose exec api .docker/application.sh test
```

Pour tester via l'interface :

* Ouvrir http://localhost:8000
* Choisir le fichier [tests/data/cnig-pcrs-lyon-01-3946.zip](../../tests/data/cnig-pcrs-lyon-01-3946.zip)
* Choisir la projection EPSG:3946
* Valider et attendre le résultat

## Quelques commandes utiles pour le debug

* Visualiser les logs du backend : `docker compose logs -f backend`
* Ouvrir un terminal dans le conteneur : `docker compose exec api /bin/bash`
* Lister les fichiers : `docker compose exec api find var/data/validations`
* Suivre un traitement particulier : `docker compose exec api tail -f var/data/validations/${VALIDATION_ID}/validator-debug.log`




# Tests

## Données de tests

* Les jeux tests sont dans le dossier `${projectDir}/tests/Data`
* Les modèles de données tests utilisés sont des modèles externes (ex : https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json)

## Exécution des tests avec docker

Voir [.circleci/config.yml](../../.circleci/config.yml)

## Exécution des tests en local

* 1) Installer les dépendances PHP

```bash
composer install
```

* 2) Configurer la base de données de test

Dans `.env.test`, ajouter la ligne suivante :

```
DATABASE_URL=postgresql://${PGUSER}:${PGPASSWORD}@localhost:5432/validator_api_test?serverVersion=13&charset=utf8
```

> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

Puis :

```bash
# Créer la base de données
php bin/console --env=test doctrine:database:create --if-not-exists
# Mettre à jour le schéma de la base de donnnées
php bin/console --env=test doctrine:schema:update --force
```

* 3) Télécharger l'exécutable java validator-cli.jar

Si `validator-cli.jar` est déjà installé, vous pouvez configurer son emplacement à l'aide de la ligne suivante dans `.env.test` :

```
VALIDATOR_PATH=/opt/ign-validation/validator-cli.jar
```

Sinon, vous pouvez lancer `bash download-validator.sh <VALIDATOR_VERSION>` pour le télécharger dans `${projectDir}/bin/validator-cli.jar` (chemin par défaut)

* 4) Exécuter les tests

```bash
XDEBUG_MODE=coverage APP_ENV=test php vendor/bin/simple-phpunit
```

* 5) Consulter les rapports

Voir `${projectDir}/var/data/output` pour les résultats des tests.


## Analyse de code avec Sonarqube

* Installer [sonar-scanner](https://docs.sonarqube.org/latest/analysis/scan/sonarscanner/)
* Configurer les variables d'environnement `SONAR_HOST_URL` et `SONAR_TOKEN`
* Exécuter `sonar-scanner` :

```bash
cd validator-api
# lancer l'analyse de code
sonar-scanner
```

Remarque : Le fichier de configuration du projet Sonarqube est à la racine : [sonar-project.properties](../../sonar-project.properties)


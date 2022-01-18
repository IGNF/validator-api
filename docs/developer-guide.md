# Documentation développeur

## Installation et déploiement

> Dépôt GitHub : [https://github.com/IGNF/validator-api](https://github.com/IGNF/validator-api)

### Prérequis

- Une distribution Linux (de préférence basée sur Debian) pour la machine hôte
- Git
- PHP 7.3\* (extensions : pgsql)
- Yarn\*
- Serveur web\*
- Zip/unzip\*
- Curl\*
- Java SE >= 11\*
- ogr2ogr >= 2.3.0\*

### Installation classique en local

Clônez le dépôt et installez les dépendances PHP :

```bash
git clone https://github.com/IGNF/validator-api.git
cd validator-api
composer install
```

Dupliquez le fichier `.env` sous le nom `.env.local`.
Utilisez les commentaires de `.env.local` pour compléter les paramètres de votre application locale.

Créez la base de données et initialisez sa structure :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
```

Téléchargez le fichier `bin/validator-cli.jar` :
```bash
bash download-validator.sh 4.1.0
```

Lancez l'application :
```bash
symfony server:start
```

L'application est consultable à l'adresse http://localhost:8000


En cas de modification des dépendances JS (package.json):

```bash
# installer les dépendances de dev
yarn install
# construire le front à l'aide de webpack
yarn run build
# commiter les modifications
```

### Installation avec docker

```bash
git clone https://github.com/IGNF/validator-api.git
cd validator-api
# démarrage de la stack de développement
docker-compose up -d
# ouvrir http://localhost:8000 avec un navigateur
```

### Architecture

![Architecture](architecture.jpg)


## Tests

### Données de tests

* Les jeux tests sont dans le dossier `${projectDir}/tests/Data`
* Les modèles de données tests utilisés sont des modèles externes (ex : https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json)

### Exécution des tests avec docker

Voir [.circleci/config.yml](../../.circleci/config.yml)

### Exécution des tests en local

* 1) Installez les dépendances PHP

```bash
composer install
```

* 2) Configurez la base de données de test

Dans `.env.test`, ajoutez la ligne suivante :

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

* 3) Téléchargez l'exécutable java validator-cli.jar

Si `validator-cli.jar` est déjà installé, vous pouvez configurer son emplacement à l'aide de la ligne suivante dans `.env.test` :

```
VALIDATOR_PATH=/opt/ign-validation/validator-cli.jar
```

Sinon, vous pouvez lancer `bash download-validator.sh <VALIDATOR_VERSION>` pour le télécharger dans `${projectDir}/bin/validator-cli.jar` (chemin par défaut)

* 4) Exécutez les tests

```bash
XDEBUG_MODE=coverage APP_ENV=test php vendor/bin/simple-phpunit
```

* 5) Consulter les rapports

Voir `${projectDir}/var/data/output` pour les résultats des tests.


### Analyse de code avec Sonarqube

* Installer [sonar-scanner](https://docs.sonarqube.org/latest/analysis/scan/sonarscanner/)
* Configurer les variables d'environnement `SONAR_HOST_URL` et `SONAR_TOKEN`
* Exécuter `sonar-scanner` :

```bash
cd validator-api
# lancer l'analyse de code
sonar-scanner
```

Remarque : Le fichier de configuration du projet Sonarqube est à la racine : [sonar-project.properties](../../sonar-project.properties)

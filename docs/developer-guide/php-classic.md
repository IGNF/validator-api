
# Documentation développeur pour développement PHP classique

## Prérequis

- Une distribution Linux (de préférence basée sur Debian) pour la machine hôte
- Git
- PHP 7.4, 8.1 ou 8.2 avec l'extension pgsql
- Yarn
- Serveur web
- Zip/unzip
- Curl
- Java SE >= 11
- ogr2ogr >= 2.3.0

## Procédure d'installation du projet PHP

* Cloner le dépôt et installez les dépendances PHP :

```bash
git clone https://github.com/IGNF/validator-publi.git
cd validator-publi
composer install
```

* Dupliquer et adapter le fichier `.env` sous le nom `.env.local`.

Utilisez les commentaires de `.env.local` pour compléter les paramètres de votre application locale.

* Créer la base de données et initialiser sa structure :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
```

* Télécharger le fichier `bin/validator-cli.jar` :

```bash
# bash download-validator.sh [<MAJOR>.<MINOR>.<PATCH>]
bash download-validator.sh
```

* Lancer l'application :

```bash
symfony server:start
```

L'application est consultable à l'adresse http://localhost:8000


## Exécution des tests

* 1) Configurer la base de données de test

Dans `.env.test`, ajoutez la ligne suivante :

```
DATABASE_URL=postgresql://${PGUSER}:${PGPASSWORD}@localhost:5432/validator_publi_test?serverVersion=15&charset=utf8
```

> [Comment configurer la variable DATABASE_URL (documentation Symfony)](https://symfony.com/doc/4.4/doctrine.html#configuring-the-database)

* 2) Initialiser la base de données de test

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

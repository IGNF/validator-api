# Installation et déploiement

> Dépôt GitHub : [https://github.com/IGNF/validator-api](https://github.com/IGNF/validator-api)

## Pré-requis

- Une distribution Linux (de préférence basée sur Debian) pour la machine hôte
- Git
- PHP 7.3\* (extensions : pgsql)
- Yarn\*
- Serveur web\*
- Zip/unzip\*
- Curl\*
- Java SE >= 11\*
- ogr2ogr >= 2.3.0\*

## Installation classique en local

```bash
# 1) cloner le dépôt
git clone https://github.com/IGNF/validator-api.git
# 2) se placer dans le dossier
cd validator-api
# 3) installer les dépendances PHP
composer install
# 4) créer un fichier .env.local pour configurer les paramètres
# ...
# 5) créer la base de données
php bin/console doctrine:database:create
# 6) initialiser la structure de la base de données
php bin/console doctrine:schema:update --force
# 7) télécharger bin/validator-cli.jar (version >= v4.0.4)
bash download-validator.sh 4.1.0
# 8) Vérifier la configuration des arguments du Validator
php bin/console app:args-config-check
# 9) Démarrer un serveur local à l'aide de l'exécutable symfony
symfony server:start
# ouvrir http://localhost:8000 avec un navigateur
```

En cas de modification des dépendances JS (package.json):

```bash
# installer les dépendances de dev
yarn install
# construire le front à l'aide de webpack
yarn run build
# commiter les modifications
```

## Installation avec docker

```bash
git clone https://github.com/IGNF/validator-api.git
cd validator-api
# démarrage de la stack de développement
docker-compose up -d
# ouvrir http://localhost:8000 avec un navigateur
```

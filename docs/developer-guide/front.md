
# Documentation développeur pour le développement du front (JavaScript)

## Principe

* Les dépendances JS sont commitées dans le dossier `public/vendor`
* En cas de modification des dépendances JS (package.json), il convient de reconstruire et commit

## Procédure de mise à jour

```bash
# installer les dépendances de dev
yarn install
# construire le front à l'aide de webpack
yarn run build
# commiter les modifications
```

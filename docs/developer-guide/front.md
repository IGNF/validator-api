# Documentation développeur pour le développement du front (JavaScript)

## Principe

* Le démonstrateur est fourni par [IGNF/validator-api-client](https://github.com/IGNF/validator-api-client) (`@ignf/validator-client`, version définie dans `package.json`).
* `npm run build` (webpack) produit `public/build`, `public/vendor/validator-api-client`, `public/css`, `public/font` et `public/img` (voir `webpack.config.js`).
* Ces dossiers sont vidés à chaque build (les fichiers d'une ancienne version du client ne restent pas), les autres fichiers de `public/` (`index.php`, `js/`...) sont conservés.
* Si validator-api-client change le nom de ses fichiers JS (ex : `runtime.` et `vendors.`), adapter les balises `<script>` de `templates/demo.html.twig`.
* Ces fichiers ne sont **pas commités** : ils sont construits dans l'image Docker (stage `assets` du `Dockerfile`) et doivent être construits localement pour le développement.

## Construire le front en local

```bash
# installer les dépendances (versions figées par package-lock.json)
npm ci
# construire le front à l'aide de webpack
npm run build
```

## Mettre à jour validator-api-client

```bash
# remplacer vX.Y.Z par le tag publié de validator-api-client
npm install --save-dev "@ignf/validator-client@https://github.com/IGNF/validator-api-client/archive/refs/tags/vX.Y.Z.tar.gz"
npm run build
# commiter package.json et package-lock.json
```

Remarque : la dépendance pointe vers l'archive du tag (et non `git+https`) pour que `npm ci` n'ait besoin ni de git ni d'une clé SSH (Docker, CI).

# Changelog

Les changements notables de ce projet sont documentés dans ce fichier.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).

## [Non publié]

Branche `upgrade/php85-symfony74` : montée de version PHP 8.5 / Symfony 7.4 et durcissement de la sécurité.

### ⚠️ Points d'attention au déploiement

- **PHP 8.5 est désormais requis.**
- **Le téléchargement des données sources et normalisées est désactivé par défaut.** `GET /api/validations/{uid}/files/source` et `/files/normalized` répondent `403 Data download is disabled`. Ils sont aussi retirés de la spécification OpenAPI. Pour les réactiver sur une instance : `DATA_DOWNLOAD_ENABLED=1`.
- **Les archives zip sont désormais contrôlées avant et pendant l'extraction.** Une archive auparavant acceptée peut être rejetée, avec une validation en `error` (voir *Sécurité*). Les extensions autorisées sont listées dans `ZipArchiveValidator::ALLOWED_EXTENSIONS`.
- **Le nom du fichier uploadé est contrôlé.** Sans l'extension `.zip`, il doit respecter `^[A-Za-z0-9_][A-Za-z0-9_.-]{0,99}$` : pas d'espace, d'accent ni de `/`, et pas de `.` ou de `-` en premier caractère. Sinon, l'upload répond `400`.
- **L'argument `model` doit être une URL `https://` sur un hôte autorisé** (`VALIDATOR_MODEL_ALLOWED_HOSTS`, par défaut `geoportail-urbanisme.gouv.fr,ignf.github.io`, sous-domaines compris).
- **`PATCH` et `DELETE` répondent `409`** pendant le traitement d'une validation.
- **Limite de débit** : au plus `VALIDATION_RATE_LIMIT` (100 par défaut) créations et mises à jour de validations par heure et par adresse IP, puis `429`. Derrière un reverse proxy, l'adresse du client est lue dans `X-Forwarded-For` pour les proxys de `TRUSTED_PROXIES` (par défaut `PRIVATE_SUBNETS`). Si l'ingress n'a pas une IP privée, il faut adapter cette variable, sinon tous les clients partagent la même limite.
- **Le front n'est plus commité** (`public/build`, `public/vendor`, `public/css`, `public/font`, `public/img`). Il est construit par le Dockerfile (stage `assets`) ou en local avec `npm ci && npm run build`.
- **`/api` redirige vers la documentation de la démo** (`/#/api`, swagger-ui 5). L'ancienne page swagger-ui 3 chargée depuis unpkg est supprimée.
- **`composer.lock`, `symfony.lock` et `package-lock.json` sont versionnés.**
- **`results.pdf` redirige (`302`) vers le rapport imprimable** `/api/validations/{uid}/report?print=1` : l'API ne génère plus de fichier PDF.
- **Tests** : la base `validator_api_test` est désormais utilisée. Auparavant ils tournaient, par erreur, sur la base `validator_api` et la purgeaient. `make test` crée la base si besoin.

### Sécurité

- Contrôle des archives zip avant extraction : seul le répertoire central est lu, sans décompression.
  - Protection contre les zip bombs : au plus 10 000 entrées, 5 Gio décompressés et un taux de compression de 1000. Code d'erreur `ARCHIVE_TOO_LARGE`.
  - Rejet des chemins absolus ou contenant `..`, des liens symboliques et des fichiers chiffrés. Code d'erreur `BAD_ARCHIVE_ENTRY`.
  - Liste blanche d'extensions (formats lus par validator-cli et fichiers annexes). Code d'erreur `FILE_EXTENSION_NOT_ALLOWED`.
  - Les entrées ajoutées par les systèmes d'exploitation (`__MACOSX/`, `.DS_Store`, `Thumbs.db`, `desktop.ini`) sont ignorées.
- Extraction contrôlée (`ZipArchiveExtractor`) en remplacement de `ZipArchive::extractTo` :
  - les exécutables, scripts et archives imbriquées (ELF, PE, Mach-O, `#!`, zip, gzip…) sont détectés par leur signature, quelle que soit l'extension ;
  - la signature doit correspondre à l'extension pour pdf, shp/shx, dbf, gpkg, xml/gml, json et les images. Code d'erreur `FILE_CONTENT_NOT_ALLOWED` ;
  - la taille réelle de chaque fichier est limitée à la taille déclarée dans l'archive ;
  - les fichiers sont créés comme fichiers ordinaires non exécutables ;
  - en cas d'erreur, le répertoire extrait est supprimé.
- Le nom du dataset (issu du nom du fichier uploadé) est contrôlé à l'upload et avant le traitement. Un fichier `...zip` donnait auparavant le nom `..` et permettait de vider le répertoire de travail de toutes les validations.
- Protection contre la SSRF via l'argument `model` : `https://` uniquement, sans userinfo ni port, et sur un hôte autorisé (`VALIDATOR_MODEL_ALLOWED_HOSTS`). Les URL `file://`, `http://`, les IP internes et les autres hôtes sont refusés.
- Le message d'erreur d'une validation (champ `message`, public) n'expose plus la ligne de commande, les chemins ni la sortie de validator-cli. Il vaut par exemple `Validation failed (exit code 1)`, et le détail reste dans les logs serveur.
- `/health/db` ne renvoie plus le message brut de la base de données (hôte, port, utilisateur).
- `/logs` est servi en `text/plain` avec `X-Content-Type-Options: nosniff`, pour éviter que le navigateur interprète du HTML issu des données.
- Téléchargement des données sources et normalisées désactivable (`DATA_DOWNLOAD_ENABLED`, désactivé par défaut). Le contrôle a lieu avant la recherche de la validation, pour ne pas révéler l'existence d'un uid.
- validator-cli.jar est téléchargé par `bin/install-validator.sh`, avec vérification de l'empreinte sha256. Ce script est la seule source de la version, partagée entre composer et le Dockerfile.
- Le rapport PDF n'est plus généré côté serveur par wkhtmltopdf, qui n'est plus maintenu et a des CVE connues (dont une SSRF) : l'API sert un rapport HTML imprimable, que le navigateur enregistre en PDF.
- En-têtes de sécurité sur toutes les réponses : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` et `Referrer-Policy`. Les pages HTML reçoivent aussi une `Content-Security-Policy` (hors mode debug) : tout est servi par l'API, sans CDN.
- Limite de débit sur `POST` et `PATCH /api/validations` (`VALIDATION_RATE_LIMIT`, `429 Too Many Requests`).
- Export CSV : les valeurs issues du dataset qui commencent par `=`, `+`, `-` ou `@` sont préfixées par `'`, pour que les tableurs ne les interprètent pas comme des formules. Les nombres sont conservés tels quels.
- Démo : plus aucune ressource chargée depuis un CDN (Bootstrap, jQuery, Popper, select2, swagger-ui@3 sur unpkg sans version fixée ni SRI).
- docker-compose : PostgreSQL n'est plus exposé sur l'hôte (seulement `127.0.0.1` via `compose.override.yaml`), et l'environnement `prod` de l'image n'est plus remplacé par le `APP_ENV=dev` du `.env`.
- Image Docker : `curl` et `wget` ne sont plus présents dans l'image finale, et Pebble (embarqué dans l'image Ubuntu, inutilisé et vulnérable) est supprimé.

### Ajouté

- Le fichier `document-info.json` produit par le validator (option `normalize`) est exposé dans le champ `document_info` des validations.
- `GET /api/validations/{uid}/report` : rapport de validation imprimable (HTML), à enregistrer en PDF avec le navigateur. Avec `?print`, la boîte de dialogue d'impression s'ouvre directement.
- Variables d'environnement `DATA_DOWNLOAD_ENABLED`, `VALIDATOR_MODEL_ALLOWED_HOSTS`, `VALIDATION_RATE_LIMIT` et `TRUSTED_PROXIES`.
- `ign-validator:validations:cleanup --processing-timeout` (par défaut `PT1H`) : les validations encore en `processing` au-delà de ce délai (worker tué, manque de mémoire…) passent en `error` avec le message `Validation failed (processing interrupted)`.
- Documentation OpenAPI : route `/logs`, réponses `403`, `404` et `429`, schémas `Error` et `Validation` (`results`, `delete_data`) conformes aux réponses réelles.
- Réponse `409 Conflict` sur `PATCH` et `DELETE` d'une validation en cours de traitement.
- Fichier `CHANGELOG.md`.

### Modifié

- Montée de version :
  - PHP 8.5 et Symfony 7.4 ;
  - Doctrine ORM 3 et DBAL 4 ;
  - doctrine-bundle 3, doctrine-migrations-bundle 4, doctrine-fixtures-bundle 4 ;
  - JMS Serializer 5, monolog-bundle 4, justinrainbow/json-schema 6 ;
  - PHPUnit 13, liip/test-fixtures-bundle 3, phpstan 2.
- Le code utilise désormais les attributs PHP (routes, mapping Doctrine, commandes) et l'`EntityManagerInterface` injecté.
- Configuration Doctrine : les options de proxy supprimées par DoctrineBundle 3 sont retirées, car les objets lazy natifs sont désormais toujours actifs.
- Base de test configurée par `dbname_suffix` au lieu de `dbname`.
- Les rapports de couverture sont générés par `make test` et ne sont plus définis dans `phpunit.xml.dist`. PHPUnit 13 refuse sinon de s'exécuter sans driver de couverture.
- Dockerfile déplacé à la racine et scripts regroupés dans `bin/` : `application.sh`, `archive.sh`, `loop-validate.sh`, `install-validator.sh`.
- Le JRE remplace le JDK dans l'image Docker.
- Refactorings :
  - les routes de fichiers (logs, results.csv/pdf, files/*) passent dans `ValidationFilesController` ;
  - la gestion des répertoires de travail et du stockage passe dans `ValidationWorkspace`.
- Front de démo : `@ignf/validator-client` passe en v0.5.9, avec chargement des chunks `runtime` et `vendors`. La dépendance pointe vers l'archive du tag GitHub (et non `git+ssh`) : `npm ci` n'a besoin ni de git ni d'une clé SSH.
- webpack-cli 4 → 7, et `webpack-copy-plugin` (inutilisé) est supprimé.
- phpstan passe du niveau 2 au niveau 5, sans règle d'exclusion.
- Les tests n'écrivent plus dans `var/data` : le stockage de test est `var/data-test`, supprimé après chaque test.

### Corrigé

- `results.pdf` renvoyait toujours une erreur 500 : le binaire `wkhtmltopdf` utilisé par knp-snappy n'était installé ni dans l'image ni dans la CI.
- Rapport : les messages longs sans espace (chemins, identifiants) ne débordent plus de la page. Ils passent à la ligne, et les en-têtes de colonnes sont répétés sur chaque page imprimée.
- Une validation avec `normalize: false` finissait toujours en `error` : la sauvegarde attendait des données normalisées.
- En cas d'échec d'une validation :
  - le log `validator-debug.log` est désormais sauvegardé, donc `/logs` est disponible ;
  - le répertoire de travail local est supprimé ;
  - l'option `delete-data` est respectée. Les fichiers sont supprimés, mais le statut `error` est conservé.
- `DELETE` supprime désormais les fichiers avant la ligne en base, ainsi que le schéma `validation<uid>` et le répertoire local.
- L'archivage (`ign-validator:validations:cleanup`) :
  - ignore les validations en cours de traitement ;
  - continue en cas d'erreur sur une validation ;
  - renvoie un code d'erreur s'il y a eu des échecs.
- Rollback de la transaction (et donc libération du verrou sur la table) en cas d'erreur dans `popNextPending`.
- `/logs` :
  - répond `200` au lieu de `201` ;
  - répond `404` quand le log n'existe pas, au lieu d'une erreur 500.
- `results.csv` et `results.pdf` :
  - `403` pour une validation non exécutée ;
  - `404` quand la validation n'a pas de résultats, au lieu d'un CSV vide ou d'une erreur 500 ;
  - le CSV est servi en `text/csv` ;
  - le PDF gère les erreurs de pré-validation du zip, qui n'ont pas de niveau.
- Téléchargements :
  - en-tête `Content-Transfer-Encoding` malformé supprimé ;
  - `404` au lieu de `403` quand le fichier est absent ;
  - nom de fichier échappé dans `Content-Disposition`.
- Arrêt du worker (SIGTERM) : validator-cli.jar est désormais arrêté et le répertoire local supprimé, avant que la validation repasse en `pending`.
- `bin/application.sh archive` et `bin/archive.sh` pointaient vers des chemins inexistants : l'archivage ne pouvait pas tourner.
- docker-compose :
  - le service `database` était défini deux fois (la recette Doctrine remplaçait PostGIS par `postgres:16`) ;
  - le worker lançait `.docker/application.sh`, qui n'existe plus ;
  - le `serverVersion` différait entre l'API et le worker.
- La CI de publication Docker pointait vers `.docker/Dockerfile`, qui n'existe plus.
- validator-cli : les options valant `0` (ex : `max-errors`, `dgpr-tolerance`) étaient ignorées, et un `VALIDATOR_JAVA_OPTS` vide produisait un argument vide.
- Tests réactivés et fiabilisés : traitement complet par validator-cli (le test était désactivé, et instable car il dépendait de l'ordre de traitement des validations), téléchargements réussis.
- La démo restait bloquée sur « Chargement… » avec le bundle validator-client découpé en chunks.
- Les tests purgeaient la base de dev : l'option `dbname` de test était ignorée en présence de `url`.
- Build Docker :
  - `VALIDATOR_VERSION` était utilisé sans être déclaré, ce qui donnait une URL de téléchargement invalide ;
  - le paquet `php8.5-opcache` n'existe pas (OPcache est intégré à PHP 8.5).
- `bin/application.sh test` utilisait une base `validator_api_test_test` et l'option obsolète `--complete`.
- Corrections phpmd sur l'ensemble du code :
  - imports manquants et code mort supprimé ;
  - `exit()` remplacé par un code de retour ;
  - ordre des groupes du rapport PDF réellement appliqué.

### Supprimé

- Dépendances inutilisées :
  - `composer/package-versions-deprecated` (abandonné) ;
  - `league/flysystem-aws-s3-v3` et `aws/aws-sdk-php` (seul l'adaptateur async-aws est utilisé) ;
  - `symfony/validator` ;
  - `symfony/requirements-checker` ;
  - `knplabs/knp-snappy-bundle` (wkhtmltopdf), remplacé par l'impression du navigateur.
- Les polyfills PHP 5.6 à 7.1 et `paragonie/random_compat` sont remplacés par les polyfills PHP 7.2 à 8.5, fournis par PHP 8.5.
- Option composer `secure-http: false`.
- Fichiers de configuration en double avec les blocs `when@` (`web_profiler` de dev et test, `routes/dev/`), ainsi que `prod/deprecations.yaml`, entièrement commenté.
- Script `download-validator.sh`, remplacé par `bin/install-validator.sh`.
- `templates/swagger.html.twig` (swagger-ui 3 chargé depuis unpkg).
- Fichiers générés par le build front, qui n'étaient plus synchronisés avec la version de validator-api-client.

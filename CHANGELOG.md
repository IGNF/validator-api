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
- **La structure de la base est gérée par les migrations Doctrine.** `DB_UPGRADE=1` lance `doctrine:migrations:migrate` à la place de `doctrine:schema:update`. La migration initiale aligne aussi les bases existantes : elle ajoute les colonnes manquantes, passe `status` et `delete_data` en `NOT NULL` et ajoute la contrainte `CHECK` sur les statuts.
- **Adresse IP des clients dans les logs** : derrière un reverse proxy, c'est l'IP du client (`X-Forwarded-For`) qui est enregistrée, et non plus celle du proxy. C'est le cas dans les logs applicatifs (`extra.ip`, à partir de `TRUSTED_PROXIES`) comme dans le log d'accès Apache (`mod_remoteip`, proxys des réseaux privés).
- **Tests** : la base `validator_api_test` est désormais utilisée. Auparavant ils tournaient, par erreur, sur la base `validator_api` et la purgeaient. `make test` crée la base si besoin.

### Authentification OIDC

- **Authentification OIDC optionnelle** (`OIDC_ENABLED`, désactivée par défaut, voir [docs/developer-guide/oidc.md](docs/developer-guide/oidc.md)). Quand elle est activée :
  - la création d'une validation nécessite d'être authentifié, par session dans la démo (`/login`, `/logout`) ou avec un jeton `Authorization: Bearer` ;
  - seuls le créateur (colonne `owner`) et les administrateurs (rôle client `OIDC_ADMIN_ROLE`) peuvent modifier ou supprimer une validation ;
  - la consultation reste publique.
- Nouvelles routes :
  - `GET /api/me` : utilisateur courant ;
  - `GET /api/validations/` : liste paginée des validations de l'utilisateur, de toutes les validations pour les administrateurs (`405` sans authentification).
- Les validations exposent `can_edit`.
- Le téléchargement des données sources et normalisées est réservé au créateur et aux administrateurs.
- Migration : colonnes `owner` et `owner_name` dans `validation`, table `sessions` pour les sessions (`PdoSessionHandler`).
- La limite de débit s'applique par utilisateur une fois authentifié.
- Image Docker : derrière un reverse proxy qui termine le TLS (ingress), Apache positionne `HTTPS=on` quand `X-Forwarded-Proto: https` vient d'un proxy du réseau privé. Sans ça, les URLs générées étaient en `http://`, dont la redirect URI OIDC refusée par Keycloak, dès que le client avait une IP publique : `mod_remoteip` masque le proxy à Symfony.

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
- Logs applicatifs : IP du client, URL, méthode HTTP et user-agent ajoutés à chaque log (`WebProcessor`).
- Canal de log `audit`, toujours écrit en prod (y compris sans erreur) : création, mise à jour des arguments et suppression des validations, avec l'uid, le nom du dataset et l'IP du client.
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
- webpack-cli 4 → 7, et `webpack-copy-plugin` (inutilisé) est supprimé. Node 20.9 minimum (`engines`).
- Build webpack :
  - les dossiers générés de `public/` sont vidés à chaque build (plus de chunks périmés après une mise à jour du client) ;
  - les bundles du client sont copiés tels quels, sans être reminifiés (build ~25 fois plus rapide) ;
  - les polices `eot` et `otf`, non référencées par la CSS, ne sont plus copiées (6 Mo de moins dans l'image).
- phpstan passe du niveau 2 au niveau 5, sans règle d'exclusion.
- Routes : `/`, `/api/validator-api.yml`, `/api/schema/*` et `/health/*` n'acceptent plus que `GET`. Les imports de routes du framework et du profiler passent au format PHP (format XML déprécié en Symfony 7.4).
- Entité `Validation` : propriétés et accesseurs typés. `delete_data` n'est plus nullable (`false` par défaut), et le `columnDefinition` de `status` est supprimé : il provoquait une différence permanente entre le mapping et la base.
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
- **Perte de données en cours de validation** : `doctrine:schema:update`, lancé à chaque démarrage de l'API (`DB_UPGRADE=1`), supprimait les tables des schémas `validation<uid>` créés par validator-cli. Doctrine ignore désormais ces schémas (`schema_filter`).
- `STORAGE_TYPE=S3` défini dans un fichier `.env` était ignoré : la variable était lue avec `getenv()`. Elle est désormais injectée par la configuration.
- Rollback de la transaction (et donc libération du verrou sur la table) en cas d'erreur dans `popNextPending`.
- `/logs` :
  - répond `200` au lieu de `201` ;
  - répond `404` quand le log n'existe pas, au lieu d'une erreur 500.
- `results.csv` :
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
- Configuration (`config/`) :
  - `services_test.yaml`, qui dupliquait `services.yaml` : seul le dossier de travail diffère en test (paramètre `validations_dir` dans un bloc `when@test`) ;
  - sous-dossiers `packages/dev`, `packages/prod` et `packages/test`, intégrés dans des blocs `when@` ;
  - `routes.yaml` (entièrement commenté) ;
  - déclaration en double du listener d'exceptions (remplacée par l'attribut `#[AsEventListener]`) ;
  - bloc `App\Controller\` et exclusions de dossiers inexistants ;
  - sessions, inutilisées par l'API ;
  - vieille branche `srcApp_` de `preload.php`.
- Fichiers de configuration en double avec les blocs `when@` (`web_profiler` de dev et test, `routes/dev/`), ainsi que `prod/deprecations.yaml`, entièrement commenté.
- Script `download-validator.sh`, remplacé par `bin/install-validator.sh`.
- Script `sql/validator-api.0.1.sql` et anciennes migrations de 2020 (jamais exécutées), remplacés par une migration initiale unique.
- Fichiers inutilisés :
  - `src/DataFixtures/AppFixtures.php` (fixture vide de la recette) ;
  - `src/.preload.php` (préchargement d'un conteneur `srcApp_` d'avant Symfony 5) ;
  - `bin/phpunit` (wrapper `simple-phpunit`, les tests utilisent `vendor/bin/phpunit`).
- `make clean` ne supprime plus `composer.lock`, `symfony.lock` ni `package-lock.json`, désormais versionnés.
- `templates/swagger.html.twig` (swagger-ui 3 chargé depuis unpkg).
- Fichiers générés par le build front, qui n'étaient plus synchronisés avec la version de validator-api-client.

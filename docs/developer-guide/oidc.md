# Authentification OIDC

L'authentification est désactivée par défaut (`OIDC_ENABLED=false`) : l'API reste alors totalement anonyme.

Quand elle est activée (`OIDC_ENABLED=true`) :

* créer une validation nécessite d'être authentifié ;
* seuls le créateur d'une validation (colonne `owner`, valeur du `sub`) et les administrateurs peuvent la modifier ou la supprimer ;
* chaque utilisateur peut lister ses validations (`GET /api/validations/`, page « Mes validations » de la démo), les administrateurs toutes les validations (page « Administration ») ;
* la consultation d'une validation, de ses rapports et de ses logs reste publique (lien partageable) ;
* le téléchargement des données sources et normalisées (si `DATA_DOWNLOAD_ENABLED=1`) est réservé au créateur et aux administrateurs.

Les permissions sont centralisées dans `App\Security\ValidationVoter`.

## Variables d'environnement

| Variable             | Exemple                                     | Description                                                                 |
| -------------------- | ------------------------------------------- | --------------------------------------------------------------------------- |
| `OIDC_ENABLED`       | `true`                                      | Active l'authentification                                                   |
| `OIDC_ISSUER_URL`    | `https://sso.geopf.fr/realms/geoplateforme` | Issuer (découverte : `${OIDC_ISSUER_URL}/.well-known/openid-configuration`) |
| `OIDC_CLIENT_ID`     | `client_validateur`                         | Client confidentiel                                                         |
| `OIDC_CLIENT_SECRET` |                                             | Secret du client (à définir hors des fichiers commités)                     |
| `OIDC_ADMIN_ROLE`    | `admin`                                     | Rôle client donnant les droits d'administration                             |

## Configuration attendue côté Keycloak

* Client confidentiel `OIDC_CLIENT_ID` avec le flux « Standard flow » (authorization code, PKCE S256).
* Redirect URI : `<url de l'instance>/login_check`. En local : `https://localhost:8000/login_check`, ou `http://localhost:3000/login_check` avec le serveur de démo de validator-api-client.
* Post logout redirect URI : `<url de l'instance>/`.
* Pour les appels avec `Authorization: Bearer` : un mapper d'audience ajoutant `OIDC_CLIENT_ID` à l'`aud` des jetons d'accès. Les jetons sont vérifiés localement (signature via le JWKS de l'issuer, `iss`, `aud`, expiration).
* Administrateurs : un **rôle client** `OIDC_ADMIN_ROLE` sur le client `OIDC_CLIENT_ID`, attribué aux utilisateurs concernés. Il est lu dans `resource_access.<OIDC_CLIENT_ID>.roles` du jeton d'accès : un rôle de même nom sur un autre client ou sur le realm n'est pas pris en compte.

## Fonctionnement

* **Navigateur (démo)** : `GET /login?_target_path=/chemin` redirige vers Keycloak, puis `/login_check` ouvre une session (cookie `PHPSESSID`, `SameSite=Lax`, `HttpOnly`) et ramène l'utilisateur sur le chemin demandé. `GET /logout` ferme la session locale et la session Keycloak. Le firewall `main` repose sur drenso/symfony-oidc-bundle.
  * Les sessions sont stockées en base (table `sessions`, `PdoSessionHandler`) pour être partagées entre les réplicas. Les sessions expirées (24 min d'inactivité) sont supprimées sur 1 % des requêtes (`gc_probability`).
  * En cas d'échec de la connexion (refus, session expirée pendant la connexion...), l'utilisateur revient sur la démo (`/?login_error=1`), qui affiche un message.
  * Les écritures authentifiées par cookie doivent provenir de la même origine (`Sec-Fetch-Site`/`Origin`, voir `SameOriginListener`).
  * Le client doit donc être servi sur la même origine que l'API : directement par validator-api, ou derrière un proxy comme le `server.js` de validator-api-client.
* **Scripts** : `Authorization: Bearer <access_token>`, sans session (firewall `bearer`, token handler `oidc` de Symfony).
* `GET /api/me` indique si l'authentification est activée et retourne l'utilisateur courant.
* La limite de débit (`VALIDATION_RATE_LIMIT`) s'applique par utilisateur une fois authentifié, et par adresse IP sinon.

Les validations créées avant l'activation n'ont pas de créateur : seuls les administrateurs peuvent les modifier ou les supprimer.

## Login factice en développement

Pour tester les droits (créateur, administrateur) sans Keycloak, ajouter dans `.env.local` :

```dotenv
OIDC_ENABLED=true
OIDC_DEV_LOGIN=true
```

« Se connecter » mène alors à `/_dev/login`, où l'on choisit un nom d'utilisateur (identifiant `dev-<nom>`) et, au besoin, le rôle administrateur. On peut aussi y aller directement : `/_dev/login?username=admin&admin=1&_target_path=/admin`. Pour tester le cas « autre utilisateur », il suffit de se reconnecter sous un autre nom.

Cette route n'existe qu'en environnements `dev` et `test`, et répond `404` si `OIDC_DEV_LOGIN` n'est pas activée.

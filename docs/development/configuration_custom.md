# Configuration personalisée

## 1. Nombre d'instances de l'API

Par défaut, l'application est configurée pour 2 instances de backend. Changement de ce comportement nécessite quelques ajustements des autres composants de l'application.

### Lancement de Docker

Voici la commande permettant de lancer les conteneurs Docker : `docker-compose up -d --build --scale <nom_service>=<N>`

L'option `--scale` permet de préciser le nombre d'instances que l'on souhaite.

On remplace `<nom_service>` par le nom du service concerné et `N` par le nombre total d'instances dudit service.

La commande pour avoir 3 instances du service backend :
`docker-compose up -d --build --scale backend=3`

> Il faut avoir configuré les autres composants avant de lancer les conteneurs Docker.

### Nommage des conteneurs

Le nommage des conteneurs du service backend se fait de la manière suivante : `validator-api_backend_n` où `n` est le numéro du conteneur.

Alors, s'il y a 3 instances du service backend, ils seront nommés ainsi :

- validator-api_backend_1
- validator-api_backend_2
- validator-api_backend_3

### Loadbalanceur nginx

Le serveur nginx se comporte comme un load-balanceur. Il faut s'assurer que toutes instances du service backend sont inscrites dans la configuration de nginx.

```conf
# .docker/nginx.conf
http {
    upstream loadbalance {
        # configuration de chaque conteneur docker de backend ici :
        # exemple : server <nom_conteneur_backend>;
        server validator-api_backend_1;
        server validator-api_backend_2;
    }
    ...
}

events {}
```

### Scheduler (mcuadros/ofelia)

Voici le service chargé de lancer les tâches périodiques de l'API, à savoir les commandes `app:validations` et `app:delete-old-files`.

Tant qu'il faut configurer la commande `app:validations` pour chaque instance de backend, il suffit de configurer la commande `app:delete-old-files` sur un seul conteneur.

```ini
# .docker/scheduler-config.ini

# configurer la commande app:validations pour chaque instance de backend
# syntaxe :
[job-exec "<nom_conteneur_backend>: app:validations"]
schedule = @every 30s
container = <nom_conteneur_backend>
command = php /var/www/html/bin/console app:validations

# exemple si on a 2 instances de backend
[job-exec "validator-api_backend_1: app:validations"]
schedule = @every 30s
container = validator-api_backend_1
command = php /var/www/html/bin/console app:validations

[job-exec "validator-api_backend_2: app:validations"]
schedule = @every 31s
container = validator-api_backend_2
command = php /var/www/html/bin/console app:validations

# il suffit de configurer la commande app:delete-old-files uniquement sur un seul conteneur
[job-exec "validator-api_backend_1: app:delete-old-files"]
schedule = @daily
container = validator-api_backend_1
command = php /var/www/html/bin/console app:delete-old-files
```

> [Voir comment configurer le délai de relance d'une commande (Documentation officielle de Go)](https://godoc.org/github.com/robfig/cron)

### Plage de ports

Il faut prévoir autant de ports que le nombre d'instances du service backend.

```yml
# docker-compose.yml
services:
  backend:
    ...
    ports:
      - "3001-3002:80" # plage de ports
    ...
```

---

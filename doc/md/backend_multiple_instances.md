# Plusieurs instances de l'API Validator

## Configuration

### Loadbalanceur nginx

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

### Docker-Compose

```yml
# docker-compose.yml
services:
  backend:
    ...
    ports:
      - "3001-3002:80" # plage de ports
    ...
```
> Prévoir autant de ports que le nombre d'instances du service backend  

## Lancement des conteneurs

 * Commande : `docker-compose up -d --build --scale backend=<N>`

> Remplacer N par le nombre d'instances de backend
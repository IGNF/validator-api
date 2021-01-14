# Multiple instances of Validator API

## Configuration

### Nginx Loadbalanceur 

```conf
# .docker/nginx.conf
http {
    upstream loadbalance {
        # configure every backend container here:
        # example : server <backend_container_name>;
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

# configure the app:validations command for every instance of backend
# syntax :
[job-exec "<nom_conteneur_backend>: app:validations"]
schedule = @every 30s
container = <nom_conteneur_backend>
command = php /var/www/html/bin/console app:validations

# example with 2 instances of backend
[job-exec "validator-api_backend_1: app:validations"]
schedule = @every 30s
container = validator-api_backend_1
command = php /var/www/html/bin/console app:validations

[job-exec "validator-api_backend_2: app:validations"]
schedule = @every 31s
container = validator-api_backend_2
command = php /var/www/html/bin/console app:validations

# configuring the app:delete-old-files command only one container is enough
[job-exec "validator-api_backend_1: app:delete-old-files"]
schedule = @daily
container = validator-api_backend_1
command = php /var/www/html/bin/console app:delete-old-files
```

> [How to configure the command relaunch delay (Go official docs)](https://godoc.org/github.com/robfig/cron)

### Docker-Compose

```yml
# docker-compose.yml
services:
  backend:
    ...
    ports:
      - "3001-3002:80" # port range
    ...
```
> The port range must contain the same number of ports as the number of instances of the backend service

## Launch the containers

 * Command : `docker-compose up -d --build --scale backend=<N>`

> Replace N by the number of instanaces of backend
# Configuration par défaut

## Architecture

<img src="development/images/architecture.jpg" alt="architecture"/>

## Composition des conteneurs Docker

```yml
# docker-compose.yml
...
services:
  backend: # image docker personalisée pour cette application symfony. Voir le dockerfile pour plus d'informations
    build:
      context: .
      dockerfile: .docker/backend.dockerfile
    ...
    networks:
      - symfony

  postgres: # instance de base de données postgres, construite à partir de l'image docker officielle
    image: postgres:10
    ...
    networks:
      - symfony

  nginx:  # nginx pour le loadbalancing des instances multiples du "backend", construit à partir de l'image docker officielle
    image: nginx:latest
    volumes:
      - ./.docker/nginx.conf:/etc/nginx/nginx.conf # fichier de configuration de nginx
    networks:
      - symfony
    depends_on:
      - backend

  scheduler:  # un outil comme crontab pour docker, on s'en sert pour programmer des tâches dans des conteneurs docker, construit à partir de l'image docker officielle
    image: mcuadros/ofelia:latest
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./.docker/scheduler-config.ini:/etc/ofelia/config.ini
    depends_on:
      - backend

networks:
  symfony: # tous les services font partie du même réseau virtuel

volumes:
  db-data: # la persistence de données de la base de données sur la machine hôte, ça permet d'éviter la perte de données même après la suppression du conteneur
...
```

> Voir le fichier `docker-compose.yml` complet à la racine du projet pour plus de précisions.

?> [En savoir plus sur le scheduler](https://github.com/mcuadros/ofelia).

!> Actuellement l'application est configurée de fonctionner avec 2 instances de "backend". [Voir ici comment le modifier](development/configuration_custom.md).

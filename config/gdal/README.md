# Configuration de GDAL

## Description

Le fichier [gmlasconf-validator.xml](gmlasconf-validator.xml) permet d'éviter le renommage des tables et des champs en minuscule lors de la lecture des fichiers GML à l'aide du driver GMLAS de GDAL/ogr2ogr.

## Mise à jour

```bash
cd config/gdal
wget https://ignf.github.io/validator/validator-core/src/main/resources/gdal/gmlasconf-validator.xml
```

## Configuration

La variable d'environnement `GMLAS_CONFIG` permet de spécifier le chemin vers ce fichier.

## Références

Voir https://github.com/IGNF/validator/issues/241

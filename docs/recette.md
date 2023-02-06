# Recette de validator-api

## Contexte

Ces instructions de recette manuelle viennent compléter les recettes automatisées de IGNF/validator et IGNF/validator-api. Elles visent donc surtout à tester des interactions manuelles avec l'interface graphique du démonstrateur et à s'assurer que les documentations sont à jour et correspondent au comportement de l'API.

## Jeux tests

* [tests/data/cnig-pcrs-lyon-01-3946.zip](../tests/data/cnig-pcrs-lyon-01-3946.zip) : jeu test PCRS valide [extrait du dépôt CNIG/PCRS](https://github.com/cnigfr/PCRS/tree/master/Exemples/M%C3%A9tropole%20de%20Lyon)
* ... TODO : référencer les jeux peu volumineux présents dans le dépôt

> TODO : trouver une solution pour référencer des jeux test plus volumineux (FTP existant ?) et ajouter la procédure correspondante.

## Procédure

### Contrôle de la page d'accueil

- se rendre sur la page d'accueil
- vérifier la présence d'un formulaire de validation d'une archive
- vérifier la présence d'un lien vers la documentation de l'API

### Contrôle de la documentation de l'API

A partir de la page d'accueil :

- cliquer sur le lien "Documentation de l'API"
- contrôler la **version affichée** (doit être conforme à celle annoncée pour la recette)
- contrôler l'affichage de la documentation technique de l'API (swagger interactif)

### Contrôle de la page à propos

A partir de la page d'accueil :

- cliquer sur le lien "A propos"
- relire la page
- vérifier l'absence de fautes d'orthographes

### Contrôle de la page à propos

A partir de la page d'accueil :

- cliquer sur le lien "Mentions légales"
- vérifier que les mentions sont conformes à la réalité en production (en particulier pour l'hébergeur)


### Contrôle d'un document PCRS valide

A partir de la page d'accueil :

- Choisir le modèle : **CNIG_PCRS_v2.0**
- Choisir la projection : **EPSG:3946 - CC46 - Conique Conforme Zone 5**
- Choisir le jeu test : [tests/data/cnig-pcrs-lyon-01-3946.zip](../tests/data/cnig-pcrs-lyon-01-3946.zip)
- Cliquer sur "valider"
- Patienter jusqu'à affichage du rapport
- Contrôler la présence d'information sur la validation :
  - Modèle utilisé pour la validation
  - Version du validateur
  - Version de GDAL
  - Projection des données
- Contrôler l'absence d'avertissement et erreur dans le rapport

### Contrôle d'un document avec des erreurs géométriques

A partir de la page d'accueil :

- Choisir le modèle : TODO
- Choisir la projection : TODO
- Choisir le jeu test : TODO
- Cliquer sur "valider"
- Patienter jusqu'à affichage du rapport
- Contrôler la présence d'information sur la validation :
  - Modèle utilisé pour la validation
  - Version du validateur
  - Version de GDAL
  - Projection des données
- Contrôler la présence d'erreur correspondant à des géométries invalides.
- Vérifier que les différents niveaux d'erreurs sont différentiables par un code couleur (information, avertissement, erreur)
- Télécharger le rapport au format CSV
- Ouvrir la données source et le rapport CSV avec QGis
- Vérifier qu'il est possible de localiser les erreurs géométriques

> TODO : screenshot QGis

### Contrôle d'un document avec des erreurs de validation XML

A partir de la page d'accueil :

- Choisir le modèle : TODO
- Choisir la projection : TODO
- Choisir le jeu test : TODO
- Cliquer sur "valider"
- Patienter jusqu'à affichage du rapport
- Contrôler la présence d'information sur la validation :
  - Modèle utilisé pour la validation
  - Version du validateur
  - Version de GDAL
  - Projection des données
- Contrôler la présence d'erreur de type XSD_SCHEMA_ERROR
- Vérifier que les différents niveaux d'erreurs sont différentiables par un code couleur (information, avertissement, erreur)
- Cliquer sur une ligne et vérifier la présence des champs xsdErrorCode, xsdErrorMessage, xsdErrorPath.

> screenshot

- Télécharger le rapport au format CSV
- Ouvrir le rapport CSV avec un tableur (encodage=UTF-8, séparateur=virgule)
- Vérifier la présence des champs xsd_code, xsd_msg, xsd_path pour les erreurs XSD_SCHEMA_ERROR

### Contrôle de la suppression d'une validation

A partir d'un des rapports de validation précédents :

- Copier l'URL d'accès au rapport dans le presse-papier
- Cliquer sur "Supprimer la validation"
- Vérifier qu'une popup de confirmation apparaît
- Annuler et vérifier que l'action est annulée (rafraîchir avec F5)
- Recommencer en confirmant
- Vérifier la bonne redirection sur la page d'accueil
- Tenter de revenir sur le rapport à l'aide de l'URL copiée depuis le presse-papier
- Confirmer la présence d'une erreur indiquant que la validation n'existe pas.




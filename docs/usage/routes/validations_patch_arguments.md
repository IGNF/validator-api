# [PATCH] /validator/validations{uid} <!-- {docsify-ignore-all} -->

Préciser les arguments et les options du programme ligne de commandes Validator.

## URL de la ressource

`[PATCH] ${base_url}/validator/validations/{uid}`

## Paramètres

| Paramètre  | Type         | Type de donnée | Obligatoire | Description                                                                                                                                 | Valeur par défaut | Surcharge possible |
| ---------- | ------------ | -------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ----------------- | ------------------ |
| uid        | path         | string         | oui         | identifiant unique correspondant à une validation                                                                                           |
| model      | payload json | string         | oui         | url vers le modèle de données                                                                                                               |
| srs        | payload json | string         | oui         | SRID correspondant à la projection [(valeurs possibles)](https://github.com/IGNF/validator-api/blob/master/docs/resources/projections.json) |
| max-errors | payload json | integer        | non         | nombre maximum d'erreurs reportées pour chaque code d'erreur                                                                                | 50                | oui                |
| normalize  | payload json | boolean        | non         | demande de normaliser les données durant la validation                                                                                      | true              | oui                |
| plugins    | payload json | string         | non         | plugins du validator.jar à utiliser (cnig ou dgpr)                                                                                          |
| encoding   | payload json | string         | non         | encodage de données                                                                                                                         | UTF-8             | non                |

[(arguments et options du validator)](https://github.com/IGNF/validator-api/blob/master/docs/resources/validator-arguments.json)

## Exemple de requête

```bash
curl --request PATCH \
  --url  ${base_url}/validator/validations/k392kn8syily29qjj18959hs \
  --header 'Content-Type: application/json' \
  --data '{
            "srs": "EPSG:2154",
            "model": "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json"
          }'
```

## Réponses HTTP

| Code HTTP | Signification                                                                                               |
| --------- | ----------------------------------------------------------------------------------------------------------- |
| 200       | Envoi d'arguments réussi                                                                                    |
| 400       | Paramètres uid ou arguments obligatoires manquants, ou certains des arguments fournis ne sont pas autorisés |
| 403       | La validation a été archivée (plus de 30 jours dépassés depuis la création)                                 |
| 404       | Aucune demande de validation ne correspond à l'uid                                                          |

## Exemples de réponse

### Succès

```json
{
  "uid": "g7258vq1t639uagbv8rg7b97",
  "dataset_name": "130010853_PM3_60_20180516",
  "arguments": {
    "srs": "EPSG:2154",
    "model": "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
    "max-errors": 50,
    "normalize": true,
    "encoding": "UTF-8"
  },
  "date_creation": "2020-10-14T17:46:54+02:00",
  "status": "pending",
  "message": null,
  "date_start": null,
  "date_finish": null,
  "results": null
}
```

!> On remarque que cette fois-ci le `status` est passé à "pending". Le moteur de tâches automatiques va donc exécuter cette validation prochainement.

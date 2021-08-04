# [GET] /api/validations/{uid} <!-- {docsify-ignore-all} -->

Récupérer tout le détail d'une validation notamment le résultat du validator.

## URL de la ressource

`[GET] ${base_url}/api/validations/{uid}`

## Paramètres

| Paramètre | Type | Type de donnée | Obligatoire | Description                                       |
| --------- | ---- | -------------- | ----------- | ------------------------------------------------- |
| uid       | path | string         | oui         | identifiant unique correspondant à une validation |

## Exemple de requête

```bash
curl --request GET \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs
```

## Réponses HTTP

| Code HTTP | Signification                                      |
| --------- | -------------------------------------------------- |
| 200       | Récupération réussie                               |
| 400       | Paramètre uid manquant                             |
| 404       | Aucune demande de validation ne correspond à l'uid |

## Exemples de réponse

### Succès

```json
{
  "uid": "k392kn8syily29qjj18959hs",
  "dataset_name": "92022_PLU_20200415",
  "arguments": null,
  "date_creation": "2020-11-18T17:05:35+01:00",
  "status": "waiting_for_args",
  "message": null,
  "date_start": null,
  "date_finish": null,
  "results": null
}
```

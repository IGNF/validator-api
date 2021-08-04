# [GET] /api/validations/{uid}/files/normalized <!-- {docsify-ignore-all} -->

Télécharger les données normalisées qui ont été générées par le validator durant la validation.

## URL de la ressource

`[GET] ${base_url}/api/validations/{uid}/files/normalized`

## Paramètres

| Paramètre | Type | Type de donnée | Obligatoire | Description                                       |
| --------- | ---- | -------------- | ----------- | ------------------------------------------------- |
| uid       | path | string         | oui         | identifiant unique correspondant à une validation |

## Exemple de requête

```bash
curl --request GET \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs/files/normalized
```

## Réponses HTTP

| Code HTTP | Signification                                                                                                                                                                                          |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 200       | Contenu téléchargeable trouvé et téléchargement réussi                                                                                                                                                 |
| 403       | Aucun contenu téléchargeable trouvé parce que soit la validation n'a pas encore été exécutée, elle a été archivée (plus de 30 jours dépassés depuis la création), ou bien elle a terminé en une erreur |
| 404       | Aucune demande de validation ne correspond à l'uid                                                                                                                                                     |

## Exemples de réponse

### Succès

```
un fichier compressé (zip) et nommé {nom_dataset}-normalized.zip
```

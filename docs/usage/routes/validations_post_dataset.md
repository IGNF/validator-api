# [POST] /validator/validations <!-- {docsify-ignore-all} -->

Téléverser un jeu de données sur le serveur afin de créer une demande de validation. La demande de valiation ne sera pas traitée tout de suite.

## URL de la ressource

`[POST] ${base_url}/validator/validations`

## Paramètres

| Paramètre | Type                | Type de donnée  | Obligatoire | Description                                    |
| --------- | ------------------- | --------------- | ----------- | ---------------------------------------------- |
| dataset   | multipart/form-data | string($binary) | oui         | jeu de données compressé (zip) à faire valider |

## Exemple de requête

```bash
curl --request POST \
  --url ${base_url}/validator/validations/ \
  --header 'Content-Type: multipart/form-data' \
  --header 'content-type: multipart/form-data; boundary=---011000010111000001101001' \
  --form dataset=@92022_PLU_20200415.zip;type=application/x-zip-compressed
```

## Réponses HTTP

| Code HTTP | Signification                                                                                  |
| --------- | ---------------------------------------------------------------------------------------------- |
| 201       | Téléversement réussi et demande de validation créée                                            |
| 400       | Jeu de données (dans un fichier compressé .zip) manquant ou autre type de fichier a été fourni |

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

!> On remarque que le `status` est égal à "waiting_for_args". Cette demande de valiation ne sera pas traitée tout de suite.

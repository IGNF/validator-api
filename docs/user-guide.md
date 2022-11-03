# Guide utilisateur

## Demander une validation

Exemple de requête :

```bash
curl --request POST \
  --url ${base_url}/api/validations/ \
  --header 'Content-Type: multipart/form-data' \
  --header 'content-type: multipart/form-data; boundary=---011000010111000001101001' \
  --form dataset=@92022_PLU_20200415.zip;type=application/x-zip-compressed
```

La validation renvoyé en réponse aura pour état (status) `waiting_for_args`. Il est nécessaire de fournir des informations supplémentaires pour que celle-ci soit effectuée.

## Préciser les arguments et les options d'une validation

Exemple de requête :

```bash
curl --request PATCH \
  --url  ${base_url}/api/validations/k392kn8syily29qjj18959hs \
  --header 'Content-Type: application/json' \
  --data '{
            "srs": "EPSG:2154",
            "model": "https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json"
          }'
```

Une fois ces arguments précisés, la validation passe en état `pending`. Le moteur de validation va l'exécuter prochainement.

## Consulter une validation

Exemple de requête :

```bash
curl --request GET \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs
```

### États possibles d'une validation :

| État                | Signification                                                                                                                                                                                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| STATUS_WAITING_ARGS | Une demande de validation a été créée, mais l'utilisateur n'a pas encore fourni les arguments du validator-cli.jar. Si l'utilisateur ne fournit pas les arguments dans les 30 jours suivant la création, la validation (y compris le jeu de données) sera supprimée. |
| STATUS_PENDING      | L'API a bien reçu les arguments du validator. La validation est prête pour l'exécution. Le moteur de tâches automatiques va donc exécuter cette validation prochainement.                                                                                            |
| STATUS_PROCESSING   | La validation est en cours d'exécution.                                                                                                                                                                                                                              |
| STATUS_FINISHED     | La validation a terminé et le validator-cli.jar n'a rencontré aucune erreur.                                                                                                                                                                                         |
| STATUS_ERROR        | Le validator-cli.jar a rencontré une erreur.                                                                                                                                                                                                                         |
| STATUS_ARCHIVED     | La validation a été créée il y a plus de 30 jours, donc elle a été supprimée.                                                                                                                                                                                        |


## Récupérer le résultat d'une validation

Exemple de requête :

```bash
curl --request GET \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs/files/normalized
```

Le résultat de cette requête est un fichier compressé (zip) nommé {nom_dataset}-normalized.zip et contenant les données normalisées par le validateur.

Il est également possible de récupérer les fichiers originaux de la validation :

```bash
curl --request GET \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs/files/source
```


## Supprimer une validation

Exemple de requête :

```bash
curl --request DELETE \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs
```

Si la suppression se déroule correctement, le statut de réponse sera 204 sans contenu.
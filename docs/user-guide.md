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

| État               | Signification |
| ------------------ | ------------- |
| `waiting_for_args` | Une demande de validation a été créée, mais l'utilisateur n'a pas encore fourni les arguments du validator-cli.jar. |
| `pending`          | L'API a bien reçu les arguments du validator. La validation est prête pour l'exécution et sera traitée prochainement par un worker. |
| `processing`       | La validation est en cours d'exécution. Elle ne peut alors être ni modifiée ni supprimée (`409`). |
| `finished`         | La validation est terminée : le rapport est disponible (`results`, `results.csv`, rapport imprimable `report` à enregistrer en PDF avec le navigateur). |
| `error`            | La validation a échoué (archive zip refusée, erreur de validator-cli.jar, traitement interrompu). Le champ `message` indique la cause et les logs restent consultables (`/logs`). |
| `archived`         | Les fichiers de la validation ont été supprimés : automatiquement 5 jours (par défaut) après sa création, ou dès la fin de la validation avec l'argument `delete-data`. Les résultats restent consultables. |


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

> Par mesure de sécurité, ces deux téléchargements sont désactivés par défaut et répondent `403 Data download is disabled`. Pour les autoriser sur une instance, définir la variable d'environnement `DATA_DOWNLOAD_ENABLED=1`.


## Supprimer une validation

Exemple de requête :

```bash
curl --request DELETE \
  --url ${base_url}/api/validations/k392kn8syily29qjj18959hs
```

Si la suppression se déroule correctement, le statut de réponse sera 204 sans contenu.
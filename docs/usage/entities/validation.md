# Validation <!-- {docsify-ignore-all} -->

## Attributs

| attribut     | type     | description                                                                                                                                                                   |
| ------------ | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| uid          | string   | identifiant unique d'une validation                                                                                                                                           |
| datasetName  | string   | le nom du jeu de données à faire valider                                                                                                                                      |
| arguments    | json     | un tableau json contenant les arguments du validator-cli.jar [(valeurs possibles)](https://github.com/IGNF/validator-api/blob/master/docs/resources/validator-arguments.json) |
| dateCreation | datetime | la date de création de la validation                                                                                                                                          |
| status       | string   | le statut de la validation                                                                                                                                                    |
| message      | text     | les messages d'erreur envoyés par le validator-cli.jar                                                                                                                        |
| dateStart    | datetime | la date de début de l'exécution d'une validation                                                                                                                              |
| dateFinish   | datetime | la date de fin de l'exécution d'une validation                                                                                                                                |
| results      | json     | les résultats de la validation générés par validator-cli.jar                                                                                                                  |

## États d'une validation

| état                | signification                                                                                                                                                                                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| STATUS_WAITING_ARGS | Une demande de validation a été créée, mais l'utilisateur n'a pas encore fourni les arguments du validator-cli.jar. Si l'utilisateur ne fournit pas les arguments dans les 30 jours suivant la création, la validation (y compris le jeu de données) sera supprimée. |
| STATUS_PENDING      | L'API a bien reçu les arguments du validator. La validation est prête pour l'exécution. Le moteur de tâches automatiques va donc exécuter cette validation prochainement.                                                                                            |
| STATUS_PROCESSING   | La validation est en cours d'exécution.                                                                                                                                                                                                                              |
| STATUS_FINISHED     | La validation a terminé et le validator-cli.jar n'a rencontré aucune erreur.                                                                                                                                                                                         |
| STATUS_ERROR        | Le validator-cli.jar a rencontré une erreur.                                                                                                                                                                                                                         |
| STATUS_ARCHIVED     | La validation a été créée il y a plus de 30 jours, donc elle a été supprimée.                                                                                                                                                                                        |

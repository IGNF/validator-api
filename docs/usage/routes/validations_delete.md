# [DELETE] /validator/validations/{uid} <!-- {docsify-ignore-all} -->

Supprimer une validation et tous ses fichiers sur le serveur.

## URL de la ressource

`[DELETE] ${base_url}/validator/validations/{uid}`

## Paramètres

| Paramètre | Type | Type de donnée | Obligatoire | Description                                       |
| --------- | ---- | -------------- | ----------- | ------------------------------------------------- |
| uid       | path | string         | oui         | identifiant unique correspondant à une validation |

## Exemple de requête

```bash
curl --request DELETE \
  --url ${base_url}/validator/validations/k392kn8syily29qjj18959hs
```

## Réponses HTTP

| Code HTTP | Signification                                      |
| --------- | -------------------------------------------------- |
| 204       | Suppression réussie (pas de contenu à retourner)   |
| 400       | Paramètre uid manquant                             |
| 404       | Aucune demande de validation ne correspond à l'uid |

## Exemples de réponse

### Succès

```

```

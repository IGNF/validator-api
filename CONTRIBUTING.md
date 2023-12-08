# Contribuer

Merci d'envisager nous aider sur ce projet. Tout type de contribution est bienvenue.

## Contributions autres que du code

N'hésitez pas à formuler toute proposition de nouvelle fonctionnalité, signalement d'anomalie ou même question dans une [nouvelle issue](https://github.com/IGNF/validator-api/issues/new/choose).

Vous pouvez également parcourir les [issues existantes](https://github.com/IGNF/validator-api/issues) pour voir si le sujet n'a pas déjà été abordé et apporter des informations complémentaires ou proposer des pistes de solutions.

Enfin si vous pensez avoir cerné quelle partie du validateur est concernée par votre contribution, vous pouvez créer l'issue dans le dépôt qui est le plus approprié parmi :

* [IGNF/validator](https://github.com/IGNF/validator) : le moteur de validation en Java
* [IGNF/validator-api](https://github.com/IGNF/validator-api) : la surcouche API REST en PHP (le présent dépôt)
* [IGNF/validator-api-client](https://github.com/IGNF/validator-api-client/) : l'interface graphique du démonstrateur

## Modifier le code ou la documentation

Si vous voulez corriger une anomalie ou apporter une nouvelle fonctionnalité vous-même, faites ces modifications dans un fork du dépôt et soumettez-nous une [pull request](https://docs.github.com/fr/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-pull-requests)

Consultez la [documentation développeur](docs/developer-guide.md) pour prendre en main ce dépôt.

Attention, nous utilisons `commitlint` pour s'assurer que les messages de commit restent cohérents, avec l'objectif futur d'automatiser la publication de nouvelles release.  Les règles à suivre pour les messages de commit sont celles de [semantic-release](https://github.com/semantic-release/semantic-release)

La première ligne des messages de commit doit se présenter sous la forme :

`<type>(<scope>): <subject>`

`<type>` est à choisir parmi une liste définie dans `.commitlintrc.json`. `<subject>` est un résumé de la modification. `<scope>` est optionnel.

Exemple :

`feat: ajout export CSV des rapports de validation`

## Livraison d'une nouvelle version

* Consulter le [numéro de la dernière version](https://github.com/IGNF/validator-api/tags).
* Renseigner la future version dans [docs/specs/validator-api.yml](docs/specs/validator-api.yml) (ex : `info.version: 0.3.0`)
* Créer et pousser un tag (ex : `v0.3.0`)
* Créer une release à partir du tag (voir https://github.com/IGNF/validator-api/tags)

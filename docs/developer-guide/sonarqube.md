
# Documentation développeur - analyse de code avec Sonarqube

## Principe

Un fichier de configuration du projet Sonarqube à la racine du projet : [sonar-project.properties](../../sonar-project.properties). Il est exploité pour alimenter une instance sonarqube IGN non exposée sur internet.

## Procédure

* Installer [sonar-scanner](https://docs.sonarqube.org/latest/analysis/scan/sonarscanner/)
* Configurer les variables d'environnement `SONAR_HOST_URL` et `SONAR_TOKEN`
* Exécuter `sonar-scanner` :

```bash
cd validator-api
# lancer l'analyse de code
sonar-scanner
```



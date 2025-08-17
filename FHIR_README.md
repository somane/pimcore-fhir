# Installation FHIR pour Pimcore

## Installation effectuée

Les composants suivants ont été installés :

### Classes FHIR
- Patient
- Practitioner
- Observation
- Organization

### Structure des dossiers
- `/src/Command` : Commandes CLI
- `/src/Controller` : Controllers API et Web
- `/src/Model/DataObject` : Extensions des classes
- `/src/Service` : Services métier
- `/templates/fhir` : Templates Twig

### Endpoints API
- `GET /api/fhir/metadata` : Métadonnées du serveur
- `GET /api/fhir/{resourceType}` : Liste des ressources
- `GET /api/fhir/{resourceType}/{id}` : Détail d'une ressource
- `POST /api/fhir/{resourceType}` : Création d'une ressource

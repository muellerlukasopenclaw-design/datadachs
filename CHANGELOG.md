# Changelog

## [1.0.0] – MVP

### Features
- Upload von .sql, .csv, .json, .txt
- Automatische PII-Erkennung per Spaltennamen + Regex
- Review-Oberfläche zur Regel-Bestätigung
- Faker-basierte Pseudonymisierung (de_DE)
- Konsistente Ersetzung pro Job
- Download pseudonymisierter Dateien
- Automatische Bereinigung temporärer Daten
- Docker/Portainer-Deployment (ARM64)
- Dark Mode UI

### Technologie
- PHP 8.3 mit Slim Framework
- FakerPHP (de_DE)
- SQLite für Job-Metadaten
- Nginx + PHP-FPM
- Supervisor
- Docker (ARM64, Alpine, nicht-Root)

### Sicherheit
- Keine externen APIs
- Keine dauerhafte Datenspeicherung
- Container ohne Root-Rechte
- Automatische Cleanup nach TTL
- Keine Klartextdaten in Logs

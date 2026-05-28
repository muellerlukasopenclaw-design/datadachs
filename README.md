# 🦡 DataDachs

**Lokale Pseudonymisierung für Testdaten**

DataDachs ist eine schlanke, selbst gehostete Webanwendung zur Pseudonymisierung produktionsnaher Testdaten. Sie ersetzt personenbezogene Daten durch realistische, valide Ersatzwerte – lokal, ohne Cloud, ohne externe APIs.

## Features

- **SQL**, **CSV**, **JSON**, **TXT**-Verarbeitung
- Spaltennamen-Heuristik + Regex-Erkennung
- Review-Oberfläche vor der Pseudonymisierung
- Faker-basierte Ersatzdaten (de_DE)
- Konsistente Ersetzung pro Job
- Automatische Bereinigung temporärer Daten
- ARM64-kompatibel (Raspberry Pi 5)

## Schnellstart

```bash
# Repository klonen
git clone https://github.com/mueller-lukas/datadachs.git
cd datadachs

# Abhängigkeiten installieren
composer install --no-dev

# .env anlegen
cp .env.example .env

# Tests ausführen
composer test
```

## Docker / Portainer

```bash
# Image bauen
docker build -t datadachs .

# Oder: Portainer Stack deployen
docker-compose up -d
```

Siehe `docker-compose.yml` für die vollständige Portainer-Stack-Konfiguration.

## Architektur

```
Upload → Typ-Erkennung → Analyse → Review → Pseudonymisierung → Download → Cleanup
```

## Technologie-Stack

- PHP 8.3+ mit Slim Framework
- FakerPHP (de_DE)
- SQLite (temporäre Metadaten)
- Nginx + PHP-FPM
- Docker (ARM64)

## Sicherheit

- Keine externen API-Calls
- Keine dauerhafte Datenspeicherung
- Container ohne Root-Rechte
- Automatische Bereinigung nach konfigurierbarer TTL
- Keine Klartextdaten in Logs

## Lizenz

MIT

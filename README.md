# 🦡 DataDachs

**Lokale Pseudonymisierung für Testdaten**

DataDachs ist eine schlanke, selbst gehostete Webanwendung zur Pseudonymisierung produktionsnaher Testdaten. Sie ersetzt personenbezogene Daten durch realistische, valide Ersatzwerte – lokal, ohne Cloud, ohne externe APIs.

## Features

- **Datei-Modus**: SQL, CSV, JSON, TXT, DOCX, XLSX, PDF
- **Datenbank-Modus**: Direkte SQLite/MySQL/PostgreSQL-Verbindung
- Spaltennamen-Heuristik + Regex-Erkennung für PII
- Review-Oberfläche vor der Pseudonymisierung
- **Preserve Rules**: Ausnahmewerte (admin, root, etc.) nicht pseudonymisieren
- Faker-basierte Ersatzdaten (de_DE)
- Konsistente Ersetzung pro Job
- Batch-Processing für große Datensätze (1000+ Zeilen)
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

## Konfiguration

```bash
# Preserve Rules (Ausnahmewerte)
DATADACHS_PRESERVE_RULES='admin,root,system'
DATADACHS_PRESERVE_CASE_SENSITIVE=false
DATADACHS_PRESERVE_USE_DEFAULTS=true

# Deterministische Pseudonymisierung (reproduzierbar)
DETERMINISTIC_SEED=mein-seed

# Batch-Größe für Datenbank-Modus
DB_BATCH_SIZE=1000
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

### Datei-Modus
```
Upload → Typ-Erkennung → Analyse → Review → Pseudonymisierung → Download → Cleanup
```

### Datenbank-Modus
```
Connect → Schema-Analyse → Review → Batch-Pseudonymisierung → Export
```

### Unterstützte Formate

| Format | Analyse | Pseudonymisierung | Hinweis |
|--------|---------|-------------------|---------|
| SQL | ✅ | ✅ | INSERT-Statements |
| CSV | ✅ | ✅ | Spaltenbasiert |
| JSON | ✅ | ✅ | Nested-Objekte |
| TXT | ✅ | ✅ | Regex-basiert |
| DOCX | ✅ | ✅ | Benötigt PHP-zip |
| XLSX | ✅ | ✅ | Benötigt PHP-zip |
| PDF | ✅ | ⚠️ | Export als TXT |
| SQLite | ✅ | ✅ | Direkt-Modus |
| MySQL | ✅ | ✅ | Direkt-Modus |
| PostgreSQL | ✅ | ✅ | Direkt-Modus |

## Technologie-Stack

- PHP 8.3+ mit Slim Framework
- FakerPHP (de_DE)
- SQLite (temporäre Metadaten)
- Nginx + PHP-FPM
- Docker (ARM64)

## Sicherheit

- Keine externen API-Calls
- Keine dauerhafte Datenspeicherung
- Container ohne Root-Rechte (read-only root fs)
- Cap-Dropping (cap_drop: ALL)
- Automatische Bereinigung nach konfigurierbarer TTL
- Keine Klartextdaten in Logs
- SBOM + Provenance für Container-Images

## Lizenz

MIT

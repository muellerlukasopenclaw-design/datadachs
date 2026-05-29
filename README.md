# 🦡 DataDachs

**DSGVO-konforme Testdaten – ohne Datenverlust, ohne Cloud**

DataDachs ist eine schlanke, selbst gehostete Webanwendung zur Pseudonymisierung produktionsnaher Testdaten. Sie ersetzt potenziell personenbezogene Daten durch realistische, valide Ersatzwerte – lokal auf deinem Server, ohne Cloud, ohne externe APIs.

## Features

- **Datei-Modus**: SQL, CSV, JSON, TXT (DOCX, XLSX, PDF experimentell)
- **Datenbank-Modus**: Direkte SQLite/MySQL/PostgreSQL-Verbindung (optional)
- Spaltennamen-Heuristik + Regex-Erkennung für potenziell personenbezogene Daten
- Review-Oberfläche vor der Pseudonymisierung
- **Preserve Rules**: Ausnahmewerte (z. B. `*@vdi.de`) nicht pseudonymisieren
- Faker-basierte Ersatzdaten (de_DE)
- Konsistente Ersetzung pro Job
- Batch-Processing für große Datensätze (1000+ Zeilen)
- Automatische Bereinigung temporärer Daten
- ARM64-kompatibel (z. B. Raspberry Pi)

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
# Preserve Rules (Ausnahmewerte, kommagetrennt)
DATADACHS_PRESERVE_RULES='admin@example.com,root@example.com,*@vdi.de,noreply@*'
DATADACHS_PRESERVE_CASE_SENSITIVE=false
DATADACHS_PRESERVE_USE_DEFAULTS=true

# Footer-Links (optional)
DATADACHS_IMPRESSUM_URL=https://example.com/impressum
DATADACHS_DATENSCHUTZ_URL=https://example.com/datenschutz

# Deterministische Pseudonymisierung (reproduzierbar)
DETERMINISTIC_SEED=mein-seed

# Batch-Größe für Datenbank-Modus
DB_BATCH_SIZE=1000

# Datenbank-Modus aktivieren (default: false)
DB_MODE_ENABLED=false
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

| Format | Analyse | Pseudonymisierung | Status |
|--------|---------|-------------------|--------|
| SQL | ✅ | ✅ | Stabil |
| CSV | ✅ | ✅ | Stabil |
| JSON | ✅ | ✅ | Stabil |
| TXT | ✅ | ✅ | Stabil |
| DOCX | ⚠️ | ⚠️ | Experimentell |
| XLSX | ⚠️ | ⚠️ | Experimentell |
| PDF | ⚠️ | ⚠️ | Experimentell (TXT-Export) |
| SQLite | ✅ | ✅ | Stabil (DB-Modus) |
| MySQL | ✅ | ✅ | Stabil (DB-Modus) |
| PostgreSQL | ✅ | ✅ | Stabil (DB-Modus) |

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

# DataDachs – Architektur

## Übersicht

```
┌─────────────────────────────────────────────────────────────┐
│                      Nginx Proxy Manager                       │
│                    (Reverse Proxy, SSL, Auth)                │
└──────────────────────┬────────────────────────────────────────┘
                       │
┌──────────────────────▼────────────────────────────────────────┐
│                      DataDachs Container                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │   Nginx     │  │  PHP-FPM    │  │    Supervisor       │  │
│  │  (Port 8080)│  │  (Port 9000)│  │  (Prozessmanager)   │  │
│  └──────┬──────┘  └──────┬──────┘  └─────────────────────┘  │
│         │                │                                    │
│  ┌──────▼────────────────▼──────┐                          │
│  │      Slim PHP Application      │                          │
│  │  ┌─────────────────────────┐  │                          │
│  │  │  UploadController       │  │                          │
│  │  │  ProcessController      │  │                          │
│  │  └─────────────────────────┘  │                          │
│  │  ┌─────────────────────────┐  │                          │
│  │  │  SqlParser              │  │                          │
│  │  │  CsvParser              │  │                          │
│  │  │  JsonParser             │  │                          │
│  │  │  TxtParser              │  │                          │
│  │  └─────────────────────────┘  │                          │
│  │  ┌─────────────────────────┐  │                          │
│  │  │  PiiDetector            │  │                          │
│  │  │  FakerEngine            │  │                          │
│  │  │  JobManager             │  │                          │
│  │  └─────────────────────────┘  │                          │
│  └────────────────────────────────┘                          │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  SQLite (Job-Metadaten)  │  Storage (Uploads/Jobs)   │  │
│  └────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Datenfluss

```
1. Upload → temporärer Speicher
2. Analyse → PII-Erkennung (Spaltennamen + Regex + Kontext)
3. Review → Nutzer bestätigt/korrigiert Regeln
4. Pseudonymisierung → Faker-basierte Ersetzung
5. Download → Ergebnisdatei
6. Cleanup → automatische Löschung nach TTL
```

## Komponenten

### Parser
- **SqlParser**: INSERT INTO ... VALUES, Multi-Row, Quotes, NULL, Escaping
- **CsvParser**: Header, Delimiter, zeilenweise Streaming
- **JsonParser**: Rekursiv, verschachtelte Objekte/Arrays
- **TxtParser**: Regex-basierte Freitext-Ersetzung

### Services
- **PiiDetector**: Spaltennamen-Heuristik + Regex + Kontextsignale
- **FakerEngine**: Ersatzwerte mit Konsistenz-Mapping (de_DE)
- **JobManager**: SQLite-Metadaten, Upload-Verwaltung, Cleanup

### Controller
- **UploadController**: Datei-Upload, Analyse-Trigger
- **ProcessController**: Pseudonymisierung, Download

## Sicherheitsmodell

- Container: nicht-Root, read-only FS
- Netzwerk: nur intern, Reverse Proxy
- Daten: temporär, keine Persistenz
- Logs: keine Klartextdaten

# DataDachs – MVP-Umsetzungsplan

## Phase 1: Grundstruktur ✅
- [x] Projektstruktur anlegen
- [x] composer.json mit Slim + Faker
- [x] Dockerfile (ARM64, Alpine, nicht-Root)
- [x] docker-compose.yml für Portainer
- [x] Nginx + PHP-FPM + Supervisor Konfiguration

## Phase 2: Kern-Engine ✅
- [x] PII-Regelset (Spaltennamen + Regex + Kontext)
- [x] PiiDetector-Service
- [x] FakerEngine mit Konsistenz-Mapping
- [x] JobManager (SQLite, Upload, Cleanup)

## Phase 3: Parser ✅
- [x] SQL-Parser (INSERT INTO, Multi-Row, Quotes, NULL)
- [x] CSV-Parser (Header, Delimiter, Streaming)
- [x] JSON-Parser (rekursiv, verschachtelt)
- [x] TXT-Parser (Regex-basiert)

## Phase 4: Web-UI ✅
- [x] Upload-Seite (Drag & Drop)
- [x] Review-Seite (Regeln anzeigen/bearbeiten)
- [x] Download-Seite
- [x] Hilfe-Seite
- [x] Dark Mode UI

## Phase 5: Integration & Tests
- [ ] composer install testen
- [ ] PHPUnit-Tests ausführen
- [ ] Docker-Build testen
- [ ] Portainer-Stack deployen
- [ ] End-to-End-Test mit Beispieldaten

## Phase 6: Deployment
- [ ] GitHub-Repository anlegen
- [ ] GitHub Actions für ARM64-Build
- [ ] GHCR-Image pushen
- [ ] Portainer-Stack deployen
- [ ] Nginx Proxy Manager konfigurieren
- [ ] DNS-Eintrag in Cloudflare
- [ ] SSL-Zertifikat einrichten
- [ ] Basic Auth oder IP-Whitelist aktivieren

## Phase 7: Dokumentation
- [x] README.md
- [x] NGINX-PROXY-MANAGER.md
- [x] .env.example
- [ ] Changelog
- [ ] Nutzungsanleitung

## Spätere Ausbaustufen

### v1.1
- [ ] ZIP/GZIP-Verarbeitung
- [ ] Parallele Verarbeitung großer Dateien
- [ ] Fortschrittsbalken bei großen Dumps

### v1.2
- [ ] Presidio-Integration (optional)
- [ ] HMAC/Salt-basierte deterministische Pseudonymisierung
- [ ] Regelprofile speichern

### v1.3
- [ ] API-Modus
- [ ] CLI-Modus
- [ ] Audit-Report
- [ ] Benutzerverwaltung

### v2.0
- [ ] Projektverwaltung
- [ ] Gespeicherte Maskierungsprofile
- [ ] Erweiterte SQL-Unterstützung (UPDATE, MERGE)

# DataDachs – Sicherheits- und Datenschutzmaßnahmen

## Grundprinzipien

1. **Verarbeitung komplett lokal** – keine Daten verlassen den Server
2. **Keine externen APIs** – kein LLM, kein Cloud-Service
3. **Keine dauerhafte Speicherung** – alles temporär, automatische Löschung
4. **Minimale Datenhaltung** – nur Metadaten, keine Inhalte

## Technische Maßnahmen

### Container-Sicherheit
- Container läuft als User `datadachs` (UID 1000), nicht als Root
- Dateisystem ist read-only (außer `/storage`)
- Nur notwendige Ports exponiert (8080 intern)
- Healthcheck für Überwachung

### Datenverarbeitung
- Uploads werden in temporärem Verzeichnis gespeichert
- Maximale Dateigröße konfigurierbar (default: 50 MB)
- Mapping-Tabellen nur im RAM, nicht persistent
- Nach Job-Ende: automatische Löschung nach konfigurierbarer TTL
- Keine Klartextdaten in Logs

### Netzwerk
- Zugriff über Reverse Proxy (Nginx Proxy Manager)
- Empfohlen: Basic Auth oder IP-Whitelist
- Optional: Cloudflare Access für öffentliche Domains
- HTTPS erzwungen

### Datenschutz
- Keine Telemetrie
- Kein Tracking
- Keine Cookies (außer Session-für-Job)
- Keine Weitergabe an Dritte

## Konfigurationsempfehlungen

### .env
```
APP_ENV=production
APP_DEBUG=false
JOB_TTL_MINUTES=60
MAX_FILE_SIZE_MB=50
LOG_LEVEL=warning
```

### Nginx Proxy Manager
- Force SSL: ✅
- Block Common Exploits: ✅
- Access List mit Basic Auth: ✅

### Cloudflare (falls verwendet)
- Proxy Status: Orange (Proxied)
- SSL/TLS: Full (strict)
- Always Use HTTPS: On

## Audit-Checkliste

- [ ] Container läuft nicht als Root
- [ ] Keine offenen Ports außer über Proxy
- [ ] Basic Auth oder IP-Whitelist aktiv
- [ ] HTTPS erzwungen
- [ ] DEBUG-Modus ausgeschaltet
- [ ] Log-Level auf warning oder höher
- [ ] Job-TTL auf sinnvollen Wert (z. B. 60 Min)
- [ ] Speicherplatz-Monitoring aktiv

## Incident Response

Falls sensible Daten kompromittiert wurden:

1. Container stoppen: `docker stop datadachs`
2. Volumes löschen: `docker volume rm datadachs-storage`
3. Logs prüfen: `docker logs datadachs`
4. Container neu starten: `docker start datadachs`

## Verantwortlichkeiten

DataDachs ist ein Werkzeug. Der Betreiber ist verantwortlich für:
- Sicheren Betrieb (Reverse Proxy, Auth)
- Regelmäßige Updates
- Zugriffskontrolle
- Backup-Strategie für Konfiguration

# DataDachs – Kontext für nächste Session

## 1. Projekt-Übersicht

- **Repository:** `muellerlukasopenclaw-design/datadachs` auf GitHub
- **Stack-ID in Portainer:** 125 (Endpoint 2)
- **Container-Name:** `/datadachs`
- **Image:** `ghcr.io/muellerlukasopenclaw-design/datadachs:1.0.11`
- **URL:** `https://datadachs.xn--mller-lukas-thb.de/`
- **Läuft auf:** Raspberry Pi 5 (ARM64) via Portainer

## 2. Aktueller Status & offene Probleme

### 🔴 KRITISCH: phone_de Regex-Bug
- **Problem:** `config/pii-rules.php` Zeile 107 enthält `\[\s\-/\]?` – das `/` in der Character-Class ist unescaped und bricht `preg_match_all()` in `TxtParser.php`
- **Fehlermeldung:** `Unknown modifier ']'`
- **Fix:** `\[\s\-/\]?` → `\[\s\-]?` (das `/` entfernen, da Telefonnummern ohnehin nicht mit `/` getrennt werden)
- **Status:** Der Fix wurde in Commit `42d97d1` committed, aber **das Docker-Image enthält immer noch die alte Datei** wegen Build-Cache-Problemen

### 🟡 Build-Cache-Problem
- GitHub Actions Build #46 war erfolgreich, aber `COPY . .` wurde aus dem Cache geladen (`DONE 0.0s`)
- `ARG CACHE_BUST` hat nicht funktioniert
- **Lösung nötig:** Entweder `no-cache` in der Workflow-Datei oder einen `RUN echo`-Befehl vor `COPY . .` einfügen

### 🟢 Bereits erledigt
- Footer mit Version, GitHub-Link, PayPal-Link
- Sticky Footer
- Favicon
- Homepage-Redesign mit Slogan
- Terminologie: "PII-Muster" → "potenziell personenbezogene Daten"

## 3. Zugangsdaten & APIs

### Portainer (Raspberry Pi)
- URL: `https://192.168.178.84:9443`
- Login: `openclaw` / `Reproduce-Undertook5-Bubble-Require-Recall-Alkalize`
- API-Token: `ptr_cjHpPAy1XQAPNECMESQqt3xySiPGIY7AQXcUOvFKu6g=`
- JWT holen via: `POST /api/auth` mit JSON `{"username":"openclaw","password":"***"}`

### GitHub
- PAT: `github…CdAI` (vollständig in TOOLS.md)
- Repo: `muellerlukasopenclaw-design/datadachs`

### Bitwarden
- Server: `https://vault.bitwarden.eu`
- Email: `mueller.lukas.openclaw@gmail.com`
- Master-Passwort: `Skittle0-Amuck-Take`
- OAuth Client ID: `user.030b335e-a900-4d15-a727-b423012d6e74`
- Client Secret: In Bitwarden-Eintrag "Bitwarden API" gespeichert

## 4. Wichtige Dateien & Pfade

| Datei | Pfad |
|-------|------|
| pii-rules.php (Bug) | `/data/workspace/datadachs/config/pii-rules.php` |
| Dockerfile | `/data/workspace/datadachs/Dockerfile` |
| CI Workflow | `/data/workspace/datadachs/.github/workflows/docker-build.yml` |
| version.php | `/data/workspace/datadachs/config/version.php` |
| UploadController | `/data/workspace/datadachs/app/Controller/UploadController.php` |
| ReviewController | `/data/workspace/datadachs/app/Controller/ReviewController.php` |
| CsvParser | `/data/workspace/datadachs/app/Parser/CsvParser.php` |
| TxtParser | `/data/workspace/datadachs/app/Parser/TxtParser.php` |

## 5. Redeploy-Prozess (sauber)

1. **Build triggern:** Push auf `main` → GitHub Actions Build startet automatisch
2. **Warten:** Build dauert ~6-7 Minuten
3. **Image löschen:** In Portainer → Images → `datadachs:1.0.11` auswählen → Remove
4. **Stack stoppen:** Portainer → Stacks → datadachs → Stop
5. **Stack starten:** Portainer → Stacks → datadachs → Start (pullt neues Image)

**Alternative:** Stack-Editor öffnen → "Update the stack" mit "Re-pull image" aktivieren

## 6. Test-Dateien

Bereits erstellt in `/tmp/openclaw/uploads/`:
- `test-users.sql` – SQL mit deutschen Testdaten
- `test-users.csv` – CSV mit firstname, lastname, email, phone
- `test-users.json` – JSON-Array
- `test-users.txt` – Plaintext mit Namen, E-Mail, Telefonnummer

## 7. Noch zu testen (offene Punkte)

- **Phase 3:** TXT-Upload (blockiert durch Regex-Bug)
- **Phase 4:** Review-Seite verifizieren
- **Phase 5:** Preserve Rules testen
- **Phase 6:** Pseudonymisierungsergebnis prüfen
- **Phase 7:** DOCX/XLSX/PDF – im README als experimentell markiert, prüfen ob im Frontend verfügbar
- **Phase 8:** Datenbank-Modus
- **Phase 9:** Fehlerbehandlung/Stabilität
- **Phase 10:** Security
- **Phase 11:** UX-Professionalisierung

## 8. Wichtige Erkenntnisse

- **Docker-Image-Cache:** `ARG CACHE_BUST` funktioniert nicht zuverlässig. Besser: `RUN echo "timestamp: $(date)"` vor `COPY . .` oder `--no-cache` in GitHub Actions
- **Container-Patching:** Direktes Editieren im Container via `docker exec` ist extrem schwierig wegen JSON-Escaping. Immer sauber über GitHub → Build → Redeploy gehen
- **Version im Footer:** Kommt aus `config/version.php`, wird im Controller ersetzt

## 9. Letzte Commits

- `42d97d1` – fix: phone_de Regex (unescaped / entfernt)
- `8db483f` – fix: Docker Cache-Bust v2 (CACHE_BUST=2)
- `d1a8324` – docs: README aktualisiert

---
*Erstellt: 2026-05-29 für Kontext-Reset*

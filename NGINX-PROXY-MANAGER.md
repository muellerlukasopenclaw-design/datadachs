# DataDachs – Nginx Proxy Manager Konfiguration

## Interne Domain

**Empfohlene Domain:** `datadachs.müller-lukas.de`

**Punycode-Variante:** `xn--datadachs.mller-lukas-thb.de`

> Hinweis: Die Umlaut-Domain `müller-lukas.de` ist technisch als IDN (Internationalized Domain Name) gültig. In DNS- und Proxy-Konfigurationen wird die Punycode-Schreibweise verwendet.

## Nginx Proxy Manager Setup

### 1. Proxy Host anlegen

| Feld | Wert |
|------|------|
| Domain Names | `datadachs.müller-lukas.de` |
| Scheme | `http` |
| Forward Hostname / IP | `datadachs` (Container-Name) |
| Forward Port | `8080` |
| Cache Assets | ❌ |
| Block Common Exploits | ✅ |
| Websockets Support | ❌ |

### 2. SSL-Zertifikat

- **SSL Certificate:** `Request a new SSL Certificate`
- **Force SSL:** ✅
- **HTTP/2 Support:** ✅
- **HSTS Enabled:** ✅ (optional)
- **HSTS Subdomains:** ✅ (optional)

### 3. Zugriffsschutz (empfohlen)

#### Option A: Basic Auth
- Im Nginx Proxy Manager unter "Access Lists" eine Liste anlegen
- Benutzername + Passwort definieren
- Der Liste dem Proxy Host zuweisen

#### Option B: IP-Allowlist
- Nur interne IPs erlauben (z. B. `192.168.178.0/24`)
- VPN-IPs hinzufügen falls vorhanden

#### Option C: Cloudflare Access (falls öffentlich)
- Cloudflare Zero Trust → Access → Applications
- Policy: nur erlaubte E-Mail-Adressen

### 4. Cloudflare DNS

**A-Record:**
```
datadachs.müller-lukas.de → A → 192.168.178.84 (deine öffentliche IP)
```

**Empfohlene Cloudflare-Einstellungen:**
- Proxy Status: 🟡 Orange (Proxied) – für DDoS-Schutz
- SSL/TLS: Full (strict)
- Always Use HTTPS: On
- Minimum TLS Version: 1.2

### 5. Portainer Stack-Anpassung

Falls das `proxy`-Netzwerk nicht existiert:

```bash
docker network create proxy
```

In der `docker-compose.yml` ist das Netzwerk bereits konfiguriert:
```yaml
networks:
  proxy:
    external: true
```

## Sicherheitshinweise

- DataDachs verarbeitet potenziell sensible Daten
- **Empfohlung:** Zugriff nur intern oder per VPN
- Falls öffentlich: unbedingt Basic Auth oder Cloudflare Access aktivieren
- Keine Weiterleitung von Logs an externe Dienste
- Container läuft ohne Root-Rechte

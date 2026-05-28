# DataDachs – Testplan

## Unit-Tests

### SqlParserTest
- [ ] Einfaches INSERT analysieren
- [ ] Multi-Row INSERT verarbeiten
- [ ] NULL-Werte erhalten
- [ ] Zahlen erhalten
- [ ] Konsistenz gleicher Werte
- [ ] Deutsche Umlaute
- [ ] Quoted Identifiers
- [ ] SQL-Syntax erhalten

### FakerEngineTest
- [ ] Konsistenz pro Job
- [ ] Sichere E-Mail-Domains
- [ ] Unterschiedliche Originals → unterschiedliche Fakes
- [ ] Deterministischer Seed
- [ ] Mapping löschen
- [ ] IBAN-Format
- [ ] Telefonnummer

### CsvParserTest
- [ ] Header-Erkennung
- [ ] Delimiter-Erkennung
- [ ] Zeilenweise Verarbeitung
- [ ] Quotes/Escaping

### JsonParserTest
- [ ] Einfache Keys
- [ ] Verschachtelte Objekte
- [ ] Arrays von Objekten
- [ ] Strukturerhalt

### TxtParserTest
- [ ] E-Mail-Erkennung
- [ ] Telefon-Erkennung
- [ ] Überlappungsbehandlung

## Integrationstests

### End-to-End
1. SQL-Datei hochladen
2. Analyse prüfen
3. Regeln bestätigen
4. Pseudonymisierung ausführen
5. Download prüfen
6. Ergebnis validieren

### Docker-Tests
- [ ] Image baut auf ARM64
- [ ] Container startet
- [ ] Healthcheck funktioniert
- [ ] Nginx erreicht PHP-FPM
- [ ] Upload funktioniert
- [ ] Cleanup funktioniert

### Performance
- [ ] 1 MB SQL-Datei < 5 Sekunden
- [ ] 10 MB SQL-Datei < 30 Sekunden
- [ ] 1000 Zeilen CSV < 2 Sekunden

## Testdaten

### test.sql
- Einfache Inserts
- Multi-Row Inserts
- NULL-Werte
- Deutsche Umlaute
- Zahlen
- Datumsangaben

### test.csv
- Header mit deutschen Spaltennamen
- 3 Zeilen Testdaten
- Semikolon-Delimiter

### test.json
- Verschachtelte Objekte
- Arrays von Objekten
- Deutsche Werte

### test.txt
- E-Mail-Adressen
- Telefonnummern
- Adressen
- IBANs

## Manuelle Tests

- [ ] UI in Chrome/Firefox/Safari
- [ ] Drag & Drop Upload
- [ ] Mobile Ansicht
- [ ] Dark Mode
- [ ] Fehlermeldungen
- [ ] Große Dateien (> 10 MB)

## Regressionstests

Vor jedem Release:
- [ ] Alle Unit-Tests
- [ ] Docker-Build
- [ ] End-to-End mit allen Dateitypen
- [ ] Cleanup-Verifikation

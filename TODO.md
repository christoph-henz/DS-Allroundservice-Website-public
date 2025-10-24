# TODO Liste - DS-Allroundservice Website

**Datum:** 14. Oktober 2025  
**Branch:** `questionnaire-base-questions`

---

## ✅ Erledigt

- [x] OOP Refactoring API Endpoints
- [x] Email unread status (FT_PEEK)
- [x] Email scroll overflow (CSS fix)
- [x] Questionnaire Success ohne GET-Parameter (Session-basiert)
- [x] QuestionnaireSuccess Page-Vererbung
- [x] Unit-Tests für AdminPage (40 Tests, alle bestanden)
- [x] **Fixed Contact Fields Feature** (Feste Kontaktfelder in Fragebögen) ✅
  - [x] Datenbankschema erweitert (is_fixed, sort_order_in_group)
  - [x] Migration für existierende Fragebögen erstellt
  - [x] Backend-API geschützt (Löschen/Bearbeiten verhindert)
  - [x] Frontend-UI angepasst (visuell gekennzeichnet, Drag&Drop deaktiviert)
  - [x] FixedContactFieldsManager Helper-Klasse erstellt
  - [x] Dokumentation in migrations/FIXED_CONTACT_FIELDS_README.md

---

## 🔄 In Arbeit

### Aufräumen: Manuelle Datenbankverbindungen
- [x] `src/Views/QuestionnaireSuccess.php` - Page-Vererbung nutzen ✅
- [ ] `generate_legal_documents_pdf.php` - Page-Vererbung nutzen
- [ ] `tests/generate_service_pages_test_pdf.php` - Prüfen
- [ ] `tests/generate_landing_page_test_pdf.php` - Prüfen

---

## ⏳ Geplant

### Questionnaire Success ohne GET-Parameter
- [x] Session-Daten vor Redirect setzen ✅
- [x] GET-Fallback entfernen ✅
- [x] Frontend-Redirect anpassen ✅
- [x] Session-Cleanup nach Anzeige ✅

### Email-System
- [ ] Automatisches Laden von neuen Emails (Bug)
- [ ] Email schreiben/antworten aus Email-Sektion
- [ ] Paged Loading testen
- [ ] Performance-Monitoring

### API Sicherheit
- [ ] **PHP API-Endpunkte absichern** (CSRF-Token, Input-Validierung, Rate Limiting)
- [ ] **JavaScript API-Aufrufe absichern** (XSS-Protection, Content Security Policy)
- [ ] **Autorisierungsprüfung für alle API-Aktionen** (Rollen-basierte Zugriffskontrolle)
- [ ] **SQL-Injection Prevention prüfen** (Prepared Statements überall)
- [ ] **Session-Sicherheit härten** (Secure Cookies, Session Timeout, HTTPS-only)

### Admin-Panel
- [ ] PDF-Export testen
- [ ] Fragebogen-Builder prüfen
- [ ] **Dashboard-Werte berechnen** (Offene Anfragen, Service-Performance, Zeitbasierte Statistiken)
- [ ] **Dashboard-Metriken implementieren** (Submissions heute, Monatliche Trends, Conversion Rates)

### Dokumentation & Testing
- [x] Unit-Tests für AdminPage Features erstellt ✅
- [ ] API-Dokumentation aktualisieren
- [ ] Unit-Tests für OOP-APIs
- [ ] Cross-Browser-Testing


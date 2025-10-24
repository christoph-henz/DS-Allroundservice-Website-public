# DS-Allroundservice Website

Moderne, datenbankgestützte Website für DS-Allroundservice mit umfangreichem Admin-Panel und dynamischen Fragebögen.

## 📋 Inhaltsverzeichnis

- [Übersicht](#übersicht)
- [Technologie-Stack](#technologie-stack)
- [Features - Home-Sektion](#features---home-sektion)
- [Features - Admin-Sektion](#features---admin-sektion)
- [Installation](#installation)
- [Testing](#testing)
- [Projektstruktur](#projektstruktur)

---

## 🎯 Übersicht

Die DS-Allroundservice Website ist eine vollständige Geschäftslösung für Dienstleistungsunternehmen mit:
- **Öffentlicher Website** für Kundenanfragen
- **Admin-Panel** für Verwaltung und Angebotserstellung
- **Dynamischen Fragebögen** für verschiedene Services
- **Email-Integration** für Kundenkorrespondenz
- **PDF-Generierung** für Angebote und Dokumente

---

## 🛠️ Technologie-Stack

### Backend
- **PHP 7.4+** - Server-side Logik
- **SQLite** - Leichtgewichtige Datenbank
- **PDO** - Datenbank-Abstraction Layer
- **TCPDF** - PDF-Generierung
- **PHPMailer** - Email-Versand
- **IMAP** - Email-Empfang

### Frontend
- **HTML5 / CSS3** - Moderne Webstandards
- **JavaScript (Vanilla)** - Dynamische Interaktionen
- **Responsive Design** - Mobile-First Ansatz
- **CSS Grid & Flexbox** - Moderne Layouts

### Architecture
- **OOP Design** - Objektorientierte Programmierung
- **MVC Pattern** - Model-View-Controller Struktur
- **Router System** - Saubere URL-Verwaltung
- **Session Management** - Sichere Authentifizierung

---

## 🏠 Features - Home-Sektion

### 1. Landing Page
**Datei:** `src/Views/Home.php`

**Features:**
- ✅ Hero-Sektion mit Call-to-Action
- ✅ Service-Übersicht Karten
- ✅ Responsive Design (Mobile, Tablet, Desktop)
- ✅ Smooth Scrolling Navigation
- ✅ Performance-optimierte Assets
- ✅ SEO-optimierte Meta-Tags

**Komponenten:**
- Header mit Logo und Navigation
- Service-Karten mit Hover-Effekten
- Kontakt-Sektion
- Footer mit Social Media Links

---

### 2. Service-Seiten (Dynamisch)
**Dateien:** `src/Views/ServicePage.php`, `src/ServiceRouter.php`

**Features:**
- ✅ **Dynamische Service-Seiten** aus Datenbank
- ✅ **Individuelle Hero-Bereiche** pro Service
- ✅ **Pricing-Tabellen** mit konfigurierbaren Preisen
- ✅ **Features-Listen** mit Icons
- ✅ **Intro-Content** mit Rich-Text
- ✅ **SEO-Metadaten** (Title, Description)
- ✅ **Call-to-Action** zum Fragebogen

**Service-Konfiguration:**
```php
- name: Service-Name
- slug: URL-freundlicher Slug
- description: Kurzbeschreibung
- pricing_data: JSON mit Preistabellen
- meta_title: SEO-Titel
- meta_description: SEO-Beschreibung
- hero_title: Haupt-Überschrift
- intro_content: Einleitungstext
- features_content: Feature-Liste
```

---

### 3. Dynamische Fragebögen
**Dateien:** `src/Views/DynamicQuestionnaire.php`, `public/assets/js/dynamic-questionnaire.js`

**Features:**
- ✅ **Multi-Step Wizard** (Schritt-für-Schritt Navigation)
- ✅ **Verschiedene Fragetypen:**
  - Text-Eingabe
  - Nummer-Eingabe
  - Ja/Nein (Boolean)
  - Radio-Buttons
  - Checkboxen
  - Dropdown-Listen
  - Textarea (lange Texte)
  - Datum-Auswahl
- ✅ **Conditional Logic** (Bedingte Fragen)
- ✅ **Client-side Validierung**
- ✅ **Fortschrittsanzeige**
- ✅ **Autosave (localStorage)**
- ✅ **Responsive Design**
- ✅ **Accessibility** (Keyboard-Navigation, ARIA-Labels)

**Verfügbare Fragebögen:**
- `/umzug-fragebogen` - Umzug Anfrage
- `/entruempelung-fragebogen` - Entrümpelung Anfrage
- `/transport-fragebogen` - Transport Anfrage

**Features im Detail:**
```javascript
- Echtzeit-Validierung
- Fehlermeldungen inline
- Navigation: Vor/Zurück/Abbrechen
- Progress Bar (0-100%)
- Autosave alle 30 Sekunden
- Daten-Wiederherstellung nach Reload
```

---

### 4. Fragebogen-Submission
**Datei:** `api/submit-questionnaire.php`

**Features:**
- ✅ **Automatische Referenznummer** (z.B. `UMZ-20250109-0001`)
- ✅ **Datenbank-Speicherung** (SQLite)
- ✅ **Email-Benachrichtigung** an Admin
- ✅ **PDF-Generierung** der Anfrage
- ✅ **Session-basierte Erfolgsseite**
- ✅ **Input-Sanitization**
- ✅ **Error Handling**

**Workflow:**
1. Fragebogen ausfüllen
2. Validierung (Client + Server)
3. Daten speichern in DB
4. Referenznummer generieren
5. PDF erstellen
6. Email an Admin senden
7. Redirect zur Erfolgsseite

---

### 5. Erfolgsseite (Questionnaire Success)
**Datei:** `src/Views/QuestionnaireSuccess.php`

**Features:**
- ✅ **Session-basierte Datenanzeige** (keine GET-Parameter)
- ✅ **Referenznummer-Anzeige**
- ✅ **Service-Name-Anzeige**
- ✅ **Sicherheits-Features:**
  - Page Reload Protection
  - Session Cleanup nach Anzeige
  - beforeunload Warning
  - localStorage Clearing
- ✅ **Responsive Design**
- ✅ **Call-to-Action** zurück zur Startseite

**Sicherheits-Implementierung:**
```javascript
// Page Reload Warning
window.addEventListener('beforeunload', (e) => {
    e.preventDefault();
    return 'Möchten Sie die Seite wirklich verlassen?';
});

// Session Cleanup
fetch('/api/clear-success-session.php')
    .then(() => localStorage.removeItem('success_shown'));
```

---

### 6. Rechtliche Seiten
**Dateien:** `src/Views/Impressum.php`, `src/Views/AGB.php`, `src/Views/Datenschutz.php`

**Features:**
- ✅ **Impressum** - Rechtliche Pflichtangaben
- ✅ **AGB** - Allgemeine Geschäftsbedingungen
- ✅ **Datenschutz** - DSGVO-konforme Datenschutzerklärung
- ✅ **PDF-Download** der Dokumente
- ✅ **Responsive Layout**
- ✅ **Suchmaschinen-freundlich**

**Verfügbare PDFs:**
- Download auf der Website

---

## 🔐 Features - Admin-Sektion

### 1. Authentifizierung & Session Management
**Dateien:** `api/auth.php`, `src/Utils/AuthMiddleware.php`

**Features:**
- ✅ **Benutzer-Login** mit Username/Password
- ✅ **Password Hashing** (bcrypt)
- ✅ **Session-Management** (sichere Sessions)
- ✅ **Role-Based Access Control** (RBAC)
  - Admin: Volle Rechte
  - Moderator: Create, Read, Update
  - Viewer: Nur Lesen
  - Editor: Create, Read, Update
- ✅ **Logout-Funktion**
- ✅ **Session Timeout**
- ✅ **Remember Me** (Optional)

**Benutzerrollen:**
```php
Admin:     create, read, update, delete, manage_users, manage_settings
Moderator: create, read, update
Viewer:    read
Editor:    create, read, update
```

---

### 2. Dashboard
**Datei:** `src/Views/AdminPage.php` (Dashboard-Sektion)

**Features:**
- ✅ **Statistik-Übersicht:**
  - Anfragen heute
  - Offene Anfragen
  - Aktive Services
  - Gesamt-Anfragen
- ✅ **Neueste Submissions** (letzte 5)
- ✅ **Service-Performance** (Anfragen pro Service)
- ✅ **Quick Actions:**
  - Neue Anfrage erstellen
  - Service verwalten
  - Emails prüfen
- ✅ **Responsive Dashboard-Layout**
- ✅ **Echtzeit-Daten** (Live-Updates)

**Dashboard-Metriken:**
```php
- Submissions heute: COUNT WHERE DATE(submitted_at) = TODAY
- Offene Anfragen: COUNT WHERE status = 'new'
- Aktive Services: COUNT WHERE is_active = 1
- Service-Stats: GROUP BY service_id
```

---

### 3. Service-Verwaltung
**Datei:** `src/Views/AdminPage.php` (Services-Sektion)

**Features:**
- ✅ **CRUD-Operationen:**
  - ✅ Service erstellen
  - ✅ Service bearbeiten
  - ✅ Service löschen (Soft-Delete)
  - ✅ Service aktivieren/deaktivieren
- ✅ **Service-Konfiguration:**
  - Name & Slug
  - Beschreibung
  - Pricing-Daten (JSON)
  - SEO-Metadaten
  - Hero-Content
  - Intro-Content
  - Features-Liste
- ✅ **Pricing-Tabellen Editor:**
  - Mehrere Preisreihen
  - Von-Bis Preise
  - Einheiten (pauschal, pro m², pro Stunde)
  - Beschreibungen
- ✅ **Vorschau-Funktion**
- ✅ **Sortierung & Filterung**

**Service-Datenstruktur:**
```sql
CREATE TABLE services (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    description TEXT,
    pricing_data TEXT,  -- JSON
    is_active INTEGER DEFAULT 1,
    created_at TEXT,
    updated_at TEXT
)

CREATE TABLE service_page_content (
    service_id INTEGER,
    meta_title TEXT,
    meta_description TEXT,
    hero_title TEXT,
    intro_content TEXT,
    features_content TEXT,
    pricing_data TEXT
)
```

---

### 4. Anfragen-Verwaltung (Submissions)
**Datei:** `src/Views/AdminPage.php` (Submissions-Sektion)

**Features:**
- ✅ **Anfragen-Liste:**
  - Referenznummer
  - Service-Name
  - Status (new, in_progress, completed, cancelled)
  - Eingangsdatum
  - Aktionen (Anzeigen, Bearbeiten, Löschen)
- ✅ **Detail-Ansicht:**
  - Alle Fragebogen-Antworten
  - Kundendaten
  - Zeitstempel
  - Status-Historie
- ✅ **Status-Verwaltung:**
  - Status ändern (Dropdown)
  - Status-Farben (new=blau, in_progress=gelb, completed=grün)
- ✅ **Filter & Suche:**
  - Nach Status filtern
  - Nach Service filtern
  - Nach Datum filtern
  - Freitext-Suche (Referenznummer, Name)
- ✅ **Sortierung:**
  - Nach Datum (neueste zuerst)
  - Nach Status
  - Nach Service
- ✅ **PDF-Generierung:**
  - Anfrage als PDF exportieren
  - Automatische PDF-Speicherung
- ✅ **Angebotserstellung:**
  - Direkter Link zum Angebot erstellen
  - Pre-fill mit Anfragedaten

**Submission-Workflow:**
```
new → in_progress → completed
                 ↘ cancelled
```

---

### 5. Angebotserstellung & PDF-Export
**Datei:** `src/Views/AdminPage.php` (Offers-Sektion)

**Features:**
- ✅ **Angebots-Generator:**
  - Referenznummer (basierend auf Anfrage)
  - Kundendaten (aus Anfrage)
  - Service-Auswahl
  - Positionen-Tabelle (Beschreibung, Menge, Einzelpreis, Gesamt)
  - Zwischensumme, MwSt., Gesamt
  - Gültigkeit (Datum)
  - Zusätzliche Notizen
- ✅ **PDF-Generierung (TCPDF):**
  - Firmen-Logo
  - Professionelles Layout
  - Mehrere Positionen
  - Steuerberechnung
  - Footer mit Kontaktdaten
- ✅ **PDF-Speicherung:**
  - `data/offers/angebot_[REF]_[TIMESTAMP].pdf`
  - Automatische Versionierung
- ✅ **Angebots-Versand:**
  - Email mit PDF-Anhang
  - Angebot per Post
- ✅ **Angebots-Historie:**
  - Alle erstellten Angebote
  - Status (Entwurf, Versendet, Angenommen, Abgelehnt)
  - Zeitstempel

**PDF-Features:**
```php
- TCPDF Library
- A4 Format
- Header: Logo + Firmenname
- Kunden-Adresse (links oben)
- Angebots-Details (Datum, Referenz, Gültigkeit)
- Positions-Tabelle mit Preisen
- MwSt.-Berechnung (19%)
- Footer: Kontaktdaten, Bankverbindung
- Seitenzahlen
```

---

### 6. Fragebogen-Builder
**Datei:** `api/questionnaire-builder.php`

**Features:**
- ✅ **Fragebogen erstellen:**
  - Titel & Slug
  - Service-Verknüpfung
  - Status (draft, active, archived)
  - Versions-Verwaltung
- ✅ **Fragen-Editor:**
  - Frage-Text
  - Frage-Typ auswählen (text, number, boolean, etc.)
  - Optionen konfigurieren (für Radio/Checkbox/Dropdown)
  - Validierung definieren (required, min, max, pattern)
  - Hilfetext hinzufügen
  - Reihenfolge festlegen
- ✅ **Gruppen-Management:**
  - Fragen in Gruppen organisieren
  - Gruppen mit Drag & Drop sortieren
  - Gruppenbeschreibungen
- ✅ **Feste Kontaktfelder (NEU):**
  - Automatische "Kontaktinformationen"-Gruppe
  - 5 Standard-Felder: Vorname, Nachname, E-Mail, Telefon, Mobil
  - Nicht bearbeitbar oder löschbar
  - Immer als erste Gruppe angezeigt
  - Dokumentation: `migrations/FIXED_CONTACT_FIELDS_README.md`
- ✅ **Conditional Logic:**
  - Fragen basierend auf Antworten anzeigen
  - "Zeige Frage X wenn Antwort Y = Z"
- ✅ **CRUD-Operationen:**
  - Fragebogen erstellen
  - Fragebogen bearbeiten
  - Fragebogen duplizieren
  - Fragebogen löschen
  - Fragen hinzufügen/bearbeiten/löschen
  - Gruppen erstellen/bearbeiten/löschen
- ✅ **Vorschau-Modus:**
  - Live-Vorschau des Fragebogens
  - Test-Submission

**Questionnaire-Datenstruktur:**
```sql
CREATE TABLE questionnaires (
    id INTEGER PRIMARY KEY,
    service_id INTEGER,
    title TEXT,
    slug TEXT UNIQUE,
    status TEXT,  -- draft, active, archived
    version INTEGER,
    created_at TEXT
)

CREATE TABLE question_groups (
    id INTEGER PRIMARY KEY,
    questionnaire_id INTEGER,
    name TEXT,
    description TEXT,
    sort_order INTEGER,
    is_fixed BOOLEAN,  -- NEU: Feste Gruppen (nicht löschbar)
    is_active BOOLEAN
)

CREATE TABLE questions (
    id INTEGER PRIMARY KEY,
    group_id INTEGER,
    text TEXT,
    type TEXT,  -- text, number, boolean, radio, checkbox, select, textarea, date
    options TEXT,  -- JSON für Auswahlmöglichkeiten
    validation TEXT,  -- JSON für Validierungsregeln
    is_required INTEGER,
    is_fixed BOOLEAN,  -- NEU: Feste Fragen (nicht löschbar)
    sort_order_in_group INTEGER,  -- NEU: Sortierung innerhalb Gruppe
    help_text TEXT,
    order_index INTEGER,
    conditional_logic TEXT  -- JSON
)
```

---

### 7. Benutzer-Verwaltung
**Datei:** `src/Views/AdminPage.php` (Users-Sektion)

**Features:**
- ✅ **Benutzer-Liste:**
  - Username
  - Email
  - Rolle (Admin, Moderator, Viewer, Editor)
  - Status (Aktiv/Inaktiv)
  - Letzter Login
  - Erstellt am
- ✅ **CRUD-Operationen:**
  - Benutzer erstellen
  - Benutzer bearbeiten
  - Benutzer deaktivieren (Soft-Delete)
  - Passwort zurücksetzen
- ✅ **Rollen-Verwaltung:**
  - Rolle zuweisen/ändern
  - Berechtigungen anzeigen
- ✅ **Sicherheits-Features:**
  - Password Hashing (bcrypt)
  - Email-Validierung
  - Unique Username/Email
  - Passwort-Stärke-Prüfung (min. 8 Zeichen)
- ✅ **Filter & Suche:**
  - Nach Rolle filtern
  - Nach Status filtern
  - Freitext-Suche

**User-Datenstruktur:**
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    email TEXT UNIQUE,
    password TEXT,  -- bcrypt hash
    role TEXT,  -- Admin, Moderator, Viewer, Editor
    is_active INTEGER,
    first_name TEXT,
    last_name TEXT,
    created_at TEXT,
    last_login TEXT
)
```

---

### 8. Einstellungen (Settings)
**Datei:** `src/Views/AdminPage.php` (Settings-Sektion)

**Features:**
- ✅ **System-Einstellungen:**
  - Site Name
  - Contact Email
  - Support Email
  - Max Upload Size
  - Session Timeout
  - Date Format
  - Timezone
- ✅ **Email-Einstellungen:**
  - SMTP Host
  - SMTP Port
  - SMTP Username/Password
  - From Address
  - From Name
- ✅ **PDF-Einstellungen:**
  - Company Logo Path
  - Footer Text
  - Default Font
  - Page Margins
- ✅ **Einstellungs-Typen:**
  - String
  - Number
  - Boolean
  - JSON
  - Text (Textarea)
- ✅ **Öffentlich/Privat:**
  - Öffentliche Settings (Frontend-zugänglich)
  - Private Settings (nur Admin)
- ✅ **Einstellungs-Kategorien:**
  - Allgemein
  - Email
  - PDF
  - Sicherheit
  - Features

**Settings-Datenstruktur:**
```sql
CREATE TABLE settings (
    id INTEGER PRIMARY KEY,
    key TEXT UNIQUE,
    value TEXT,
    type TEXT,  -- string, number, boolean, json, text
    description TEXT,
    is_public INTEGER,  -- 1 = öffentlich, 0 = privat
    category TEXT
)
```

---

### 9. Email-Inbox System
**Dateien:** `src/Utils/EmailInbox.php`, `src/Views/AdminPage.php` (Emails-Sektion)

**Features:**
- ✅ **IMAP-Integration:**
  - Email-Abruf von Server
  - Multi-Account Support
  - SSL/TLS Verbindung
- ✅ **Email-Liste:**
  - Absender (Von)
  - Betreff
  - Datum
  - Status (Gelesen/Ungelesen)
  - Anhänge-Indikator
- ✅ **Email-Detail-Ansicht:**
  - Vollständige Email-Anzeige
  - HTML & Plain-Text Support
  - Anhänge anzeigen/downloaden
  - Header-Informationen
- ✅ **Email-Verwaltung:**
  - Als gelesen/ungelesen markieren
  - Email löschen
  - Email archivieren
  - In Ordner verschieben
- ✅ **Unread-Status Management:**
  - FT_PEEK Flag (Email bleibt ungelesen beim Abrufen)
  - Unread Counter
  - Visual Indicators (fett = ungelesen)
- ✅ **Paged Loading:**
  - 20 Emails pro Seite
  - "Load More" Button
  - Performance-Optimierung
- ✅ **Filter & Suche:**
  - Nach Status (gelesen/ungelesen)
  - Nach Datum
  - Freitext-Suche (Betreff, Absender)
- ✅ **Auto-Refresh:**
  - Automatisches Laden neuer Emails (geplant)
  - Manual Refresh Button

**Email-Features im Detail:**
```php
- IMAP Connection mit FT_PEEK
- Attachment Handling
- HTML Email Rendering
- XSS Protection
- Email Threads
- Search Functionality
- Inbox Pagination
- Unread Counter Badge
```

---

### 10. Search & Filter System
**Datei:** `src/Views/AdminPage.php` (Alle Sektionen)

**Features:**
- ✅ **Globale Suche:**
  - Über alle Datentypen hinweg suchen
  - Relevanz-Ranking
- ✅ **Sektion-spezifische Suche:**
  - Submissions: Referenznummer, Name, Email
  - Services: Name, Slug, Beschreibung
  - Users: Username, Email, Name
  - Emails: Betreff, Absender
- ✅ **Advanced Filters:**
  - Datum-Range (Von-Bis)
  - Status-Filter (Dropdown)
  - Service-Filter
  - Rolle-Filter
- ✅ **Sortierung:**
  - Aufsteigend/Absteigend
  - Nach Spalte
  - Custom Sort Logic
- ✅ **Live-Search:**
  - Echtzeit-Filterung während Eingabe
  - Debounced Input (300ms)
  - Highlight Matches

---

### 11. Responsive Admin-Design
**Datei:** `public/assets/css/admin.css`

**Features:**
- ✅ **Mobile-First Design**
- ✅ **Sidebar-Navigation:**
  - Collapsible auf Mobile
  - Icons + Labels
  - Active State Indicator
- ✅ **Responsive Tables:**
  - Horizontal Scroll auf Mobile
  - Sticky Headers
  - Compact Mode
- ✅ **Touch-Optimiert:**
  - Große Click-Targets (min. 44x44px)
  - Swipe-Gesten
  - Touch-Feedback
- ✅ **Breakpoints:**
  - Mobile: < 768px
  - Tablet: 768px - 1024px
  - Desktop: > 1024px

---

## 🧪 Testing

### Unit Tests
**Datei:** `tests/AdminPageTest.php`

**Features:**
- ✅ **40 Unit Tests** für AdminPage-Features
- ✅ **Standalone Framework** (kein PHPUnit erforderlich)
- ✅ **Test-Kategorien:**
  - Authentication (4 Tests)
  - Dashboard Statistics (4 Tests)
  - Service Management (6 Tests)
  - Submission Management (4 Tests)
  - User Management (6 Tests)
  - Settings Management (5 Tests)
  - Search & Filter (3 Tests)
  - Input Validation (3 Tests)
  - Permissions (3 Tests)
  - Utilities (4 Tests)

**Ausführung:**
```bash
php tests/AdminPageTest.php
```

**Ergebnis:**
```
Total:  40
Passed: 40
Failed: 0
```

---

### Integration Tests
**Datei:** `tests/AdminPageIntegrationTest.php`

**Features:**
- ✅ **54 Integration Tests** (28 API + 26 Authorization)
- ✅ **API-Endpoint Testing:**
  - GET, POST, PUT, DELETE
  - JSON Response Validation
  - Database Operations
- ✅ **Authorization Testing:**
  - Role-Based Access Control
  - Unauthorized Access Prevention
  - Permission Validation
- ✅ **Test-Kategorien:**
  - API Data Retrieval (5 Tests)
  - Service Management API (3 Tests)
  - Submission Management API (4 Tests)
  - User Management API (5 Tests)
  - Settings API (4 Tests)
  - Questionnaire API (4 Tests)
  - Service Page Content API (2 Tests)
  - Dashboard Statistics (2 Tests)
  - **Authorization & Permissions (26 Tests)**

**Authorization Tests:**
- ✅ Unauthorized access prevention
- ✅ Viewer role restrictions (read-only)
- ✅ Moderator role limitations (no delete, no user/settings management)
- ✅ Admin full permissions
- ✅ Editor role (create, read, update)
- ✅ Unknown role = no permissions
- ✅ Inactive user cannot login
- ✅ Session validation

**Ausführung:**
```bash
php tests/AdminPageIntegrationTest.php
```

**Ergebnis:**
```
Total:  54
Passed: 54
Failed: 0
```

---

## 📁 Projektstruktur

```
DS-Allroundservice-Website/
│
├── 📂 api/                                    # Backend API Endpoints
│   ├── admin.php                              # Haupt-Admin-API (Services, Users, Settings, Submissions)
│   ├── auth.php                               # Login/Logout Authentication
│   ├── submit-questionnaire.php               # Fragebogen-Submission Handler
│   ├── questionnaire_api.php                  # Fragebogen REST-API
│   ├── questionnaire-builder.php              # Fragebogen-Builder API (CRUD)
│   └── clear-success-session.php              # Session Cleanup nach Erfolgsseite
│
│
├── 📂 public/                                 # Öffentlich zugängliche Assets
│   └── assets/
│       ├── 📂 css/                            # Stylesheets
│       │   ├── admin.css                      # Admin-Panel Styles
│       │   ├── home.css                       # Landing-Page Styles
│       │   ├── services.css                   # Service-Seiten Styles
│       │   ├── questionnaire.css              # Fragebogen Styles
│       │   ├── dynamic-questionnaire.css      # Dynamischer Fragebogen Styles
│       │   ├── questionnaire-builder.css      # Fragebogen-Builder Styles
│       │   ├── login.css                      # Login-Seite Styles
│       │   ├── law.css                        # Rechtliche Seiten Styles
│       │   └── page-components.css            # Wiederverwendbare Komponenten
│       │
│       ├── 📂 js/                             # JavaScript
│       │   ├── admin.js                       # Admin-Panel Logik
│       │   ├── admin-drag-drop.js             # Drag & Drop für Admin
│       │   ├── dynamic-questionnaire.js       # Fragebogen Multi-Step Logik
│       │   ├── questionnaire-builder.js       # Fragebogen-Builder Frontend
│       │   ├── questionnaire-behavior.js      # Fragebogen-Verhalten
│       │   ├── home-behavior.js               # Landing-Page Interaktionen
│       │   ├── login.js                       # Login-Formular Logik
│       │   └── sticky-header.js               # Sticky Header Behavior
│       │
│       └── 📂 img/                            # Bilder, Icons, Logos
│           └── (Logo, Service-Icons, etc.)
│
├── 📂 src/                                    # Source Code (MVC)
│   ├── Router.php                             # Haupt-URL-Router
│   ├── ServiceRouter.php                      # Spezial-Router für Service-Seiten
│   │
│   ├── 📂 Models/                             # Datenmodelle
│   │   └── Offer.php                          # Angebots-Modell (PDF-Generierung)
│   │
│   ├── 📂 Utils/                              # Utility-Klassen
│   │   ├── auth_middleware.php                # Authentifizierungs-Middleware
│   │   ├── EmailInbox.php                     # IMAP Email-Empfang
│   │   ├── EmailInboxFallback.php             # Email-Fallback (ohne IMAP)
│   │   ├── EmailEventStore.php                # Email-Event Tracking
│   │   ├── OfferPDFGenerator.php              # PDF-Generierung für Angebote
│   │   └── QuestionnaireSubmissionHandler.php # Fragebogen-Verarbeitung
│   │
│   └── 📂 Views/                              # View-Klassen (Pages)
│       ├── Page.php                           # Basis-Page-Klasse (Template)
│       ├── Home.php                           # Landing-Page / Startseite
│       ├── ServicePage.php                    # Dynamische Service-Seiten
│       ├── DynamicQuestionnaire.php           # Multi-Step Fragebogen
│       ├── QuestionnaireSuccess.php           # Erfolgsseite nach Submission
│       ├── QuestionnaireBuilder.php           # Admin: Fragebogen-Builder
│       ├── AdminPage.php                      # Admin-Panel (Dashboard, Services, etc.)
│       ├── LoginPage.php                      # Login-Formular
│       ├── Contact.php                        # Kontakt-Seite
│       ├── Impressum.php                      # Impressum
│       ├── AGB.php                            # Allgemeine Geschäftsbedingungen
│       ├── Datenschutz.php                    # Datenschutzerklärung
│       └── CookieHandler.php                  # Cookie-Consent Handler
│
│
├── 📂 debug/                                  # Debug & Entwickler-Tools
│   └── (Debug-Scripts, Password-Reset, etc.)  # Nicht für Produktion
│
├── 📂 tests/                                  # Test-Suite
│   ├── AdminPageTest.php                      # 40 Unit Tests
│   ├── AdminPageIntegrationTest.php           # 54 Integration Tests
│   └── (weitere Test-Dateien)                 # PDF-Tests, Setup-Scripts
│
├── 📂 vendor/                                 # Composer Dependencies
│   ├── autoload.php                           # Composer Autoloader
│   └── tecnickcom/tcpdf/                      # TCPDF Library (PDF-Generierung)
│
├── 📂 .idea/                                  # PhpStorm IDE Config (gitignored)
│
├── .htaccess                                  # Apache URL-Rewriting
├── .gitignore                                 # Git Ignore Rules
├── index.php                                  # Application Entry Point
├── composer.json                              # Composer Dependencies
├── composer.lock                              # Locked Dependency Versions
├── database.db                                # SQLite Haupt-Datenbank
├── db-sqlite.sql                              # DB-Schema (SQL)
├── DS-Allroundservice_Admin-Anleitung.pdf     # Admin-Handbuch
├── README.md                                  # Projekt-Dokumentation (diese Datei)
└── TODO.md                                    # Entwicklungs-TODO-Liste

```

### 📋 Verzeichnis-Beschreibungen

| Verzeichnis | Zweck | Wichtigste Dateien |
|-------------|-------|-------------------|
| **api/** | Backend REST-APIs | `admin.php`, `auth.php`, `submit-questionnaire.php` |
| **public/** | Frontend Assets (CSS, JS, Images) | Alle öffentlich zugänglichen Dateien |
| **src/** | PHP Anwendungscode (MVC) | Models, Views, Utils, Router |
| **data/** | Uploads & generierte Dateien | PDFs, Datenbank, Submissions |
| **debug/** | Entwicklungs-Tools | Password-Reset, Event-Store-Reset |
| **tests/** | Automatisierte Tests | 94 Unit & Integration Tests |
| **vendor/** | Composer Packages | TCPDF, andere Dependencies |

### 🗂️ Wichtigste Dateien

**Entry Point:**
- `index.php` - Application Bootstrap, Routing, DB-Init

**Datenbank:**
- `database.db` - SQLite Haupt-Datenbank
- `db-sqlite.sql` - SQL-Schema für Setup

**Konfiguration:**
- `composer.json` - PHP Dependencies
- `.htaccess` - Apache URL-Rewriting
- `.gitignore` - Ausgeschlossene Dateien

**Dokumentation:**
- `README.md` - Vollständige Projekt-Doku
- `TODO.md` - Entwicklungs-Roadmap
- `DS-Allroundservice_Admin-Anleitung.pdf` - Admin-Handbuch

---

## 🚀 Installation

### Voraussetzungen
- PHP 7.4 oder höher
- SQLite3
- Composer
- IMAP Extension (optional, für Email-Funktion)

### Schritt 1: Repository klonen
```bash
git clone https://github.com/christoph-henz/DS-Allroundservice-Website-public.git
cd DS-Allroundservice-Website
```

### Schritt 2: Dependencies installieren
```bash
composer install
```

### Schritt 3: Datenbank initialisieren
```bash
# SQLite-Datenbank wird automatisch erstellt
# Optional: SQL-Schema laden
sqlite3 database.db
```

### Schritt 4: Konfiguration anpassen
```php
// config/database.php
define('DB_PATH', __DIR__ . '/../database.db');

// Email-Einstellungen (optional)
define('IMAP_HOST', '{imap.example.com:993/imap/ssl}INBOX');
define('IMAP_USER', 'your-email@example.com');
define('IMAP_PASSWORD', 'your-password');   hashed
```

### Schritt 5: Webserver starten
```bash
# PHP Built-in Server
php -S localhost:8000

# Oder Apache/Nginx konfigurieren
```

### Schritt 6: Admin-Login
```
URL: http://localhost:8000/admin
Username: admin
Password: [siehe Datenbank oder reset_admin_password.php verwenden]
```

---

## 🔧 Verwendung

### Neuen Service erstellen
1. Admin-Panel öffnen (`/admin`)
2. "Services" → "Neuer Service"
3. Name, Slug, Beschreibung eingeben
4. Pricing-Daten konfigurieren
5. SEO-Metadaten hinzufügen
6. Speichern & Aktivieren

### Fragebogen erstellen
1. Admin-Panel → "Questionnaires"
2. "Neuer Fragebogen"
3. Titel & Service auswählen
4. Fragen hinzufügen (Drag & Drop Reihenfolge)
5. Validierung konfigurieren
6. Auf "Active" setzen

### Angebot erstellen
1. Admin-Panel → "Submissions"
2. Anfrage auswählen
3. "Angebot erstellen"
4. Positionen hinzufügen
5. Preise eingeben
6. PDF generieren & versenden

---

## 📊 Datenbank-Schema

### Wichtigste Tabellen

**services**
- Speichert alle Service-Definitionen
- JSON pricing_data für flexible Preisstrukturen

**questionnaire_submissions**
- Speichert eingehende Anfragen
- JSON form_data für flexible Fragebögen
- Status-Tracking (new, in_progress, completed, cancelled)

**users**
- Benutzer-Accounts mit Rollen
- Bcrypt Password Hashing
- Soft-Delete Support

**questionnaires & questions**
- Dynamische Fragebogen-Definitionen
- Conditional Logic Support
- Versions-Verwaltung

**settings**
- Key-Value Einstellungen
- Typisierte Werte (string, number, boolean, json)
- Public/Private Flag

**service_page_content**
- SEO & Content für Service-Seiten
- 1:1 Beziehung zu services

---

## 🔐 Sicherheit

### Implementierte Sicherheitsmaßnahmen

✅ **Authentifizierung:**
- Password Hashing (bcrypt)
- Session Management
- Role-Based Access Control (RBAC)

✅ **Input Validation:**
- Server-side Validierung
- Prepared Statements (SQL-Injection Prevention)
- XSS-Protection (htmlspecialchars)

✅ **Session Security:**
- Session-basierte Datenübergabe (keine GET-Parameter für sensitive Daten)
- Session Cleanup
- beforeunload Protection

✅ **Database:**
- PDO mit Prepared Statements
- Foreign Key Constraints
- Soft Deletes (Daten-Integrität)

### Geplante Sicherheitsverbesserungen

⏳ **PHP API-Endpunkte absichern:**
- CSRF-Token Implementation
- Rate Limiting
- Input Sanitization erweitern

⏳ **JavaScript API-Aufrufe absichern:**
- XSS-Protection erweitern
- Content Security Policy (CSP)

⏳ **Session-Sicherheit härten:**
- Secure Cookies
- Session Timeout
- HTTPS-only Flag

---

## 📝 Entwicklung

### Code-Style
- PSR-12 Coding Standard
- OOP Design Patterns
- MVC Architecture
- Dependency Injection

### Testing ausführen
```bash
# Unit Tests
php tests/AdminPageTest.php

# Integration Tests
php tests/AdminPageIntegrationTest.php

# Alle Tests
php tests/AdminPageTest.php && php tests/AdminPageIntegrationTest.php
```

### Debug-Modus aktivieren
```php
// debug/DEBUG_MODE_README.md
// Verschiedene Debug-Tools verfügbar
```

---

## 📄 Lizenz

Proprietary - Alle Rechte vorbehalten

---

## 👥 Kontakt

**DS-Allroundservice**
- Website: [DS-Allroundservice.de](https://ds-allroundservice.de)
- Email: christophhenz@gmail.com

---

## 🎯 Roadmap

### Aktuelle Version
- ✅ Vollständiges Admin-Panel
- ✅ Dynamische Fragebögen
- ✅ PDF-Generierung
- ✅ Email-Integration
- ✅ Umfassende Tests (94 Tests)

### Nächste Features
- ⏳ Email schreiben/antworten aus Admin-Panel
- ⏳ Automatisches Laden neuer Emails
- ⏳ API-Sicherheits-Härtung
- ⏳ Performance-Optimierung
- ⏳ Multi-Language Support
- ⏳ Advanced Analytics Dashboard

---

**Letzte Aktualisierung:** 9. Oktober 2025
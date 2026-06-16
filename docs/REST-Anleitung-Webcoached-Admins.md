# Webcoached → Moodle: REST-Anleitung für Webcoached-Admins

Diese Anleitung beschreibt, wie das **Webcoached-System** über die Moodle-REST-API
zwei Aktionen in einer `mod_webcoached`-Aktivität auslöst:

1. **Aktivitätsabschluss** für einen Lernenden setzen (optional mit Note).
2. **Benachrichtigung** an einen Lernenden senden („Sie haben eine neue Nachricht
   in Webcoached").

> Zielgruppe: technische Administrator/innen bzw. Entwickler/innen auf
> Webcoached-Seite, die die ausgehenden API-Aufrufe konfigurieren.

---

## 1. Platzhalter

Ersetzen Sie in allen Beispielen die folgenden Platzhalter durch Ihre echten Werte:

| Platzhalter         | Bedeutung | Beispiel |
|---------------------|-----------|----------|
| `<ADMIN_TOKEN>`     | **Web-Service-Token** des Webcoached-Dienstkontos (von Moodle-Admin ausgestellt). | `a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6` |
| `<MOODLE_BASE_URL>` | Basis-URL der Moodle-Installation (ohne abschließenden Schrägstrich). | `https://lernen.example.org` |
| `<COURSEID>`        | Numerische Moodle-Kurs-ID. | `7` |
| `<CMID>`            | **Kursmodul-ID** der Webcoached-Aktivität (siehe [Abschnitt 3](#3-wie-finde-ich-die-ids)). | `123` |
| `<USERID>`          | Moodle-Benutzer-ID des Lernenden (= `moodle_user_id` aus dem SSO-Login). | `42` |

Der REST-Endpunkt ist immer:

```
<MOODLE_BASE_URL>/webservice/rest/server.php
```

Alle Aufrufe verwenden `POST` mit `Content-Type: application/x-www-form-urlencoded`
und liefern JSON zurück (`moodlewsrestformat=json`).

---

## 2. Voraussetzungen (einmalig durch Moodle-Admin)

Bevor die Aufrufe funktionieren, muss auf Moodle-Seite Folgendes eingerichtet sein
(zur Information – nicht durch Webcoached durchzuführen):

- Web Services und das **REST-Protokoll** sind aktiviert.
- Es existiert ein **Dienstkonto** mit den nötigen Rechten:
  - für den **Abschluss/Note**: Capability `moodle/grade:edit` im Kurs,
  - für die **Benachrichtigung**: Capability `mod/webcoached:sendmessage`.
- Für dieses Konto ist ein **Token** ausgestellt → das ist Ihr `<ADMIN_TOKEN>`.

---

## 3. Wie finde ich die IDs?

| ID | Woher |
|----|-------|
| `<USERID>` | Wird beim SSO-Login als `moodle_user_id` an Webcoached übergeben. Speichern Sie diesen Wert je Lernendem und senden Sie ihn zurück. |
| `<CMID>`   | Die Kursmodul-ID steht in der Moodle-Aktivitäts-URL: `…/mod/webcoached/view.php?id=<CMID>`. Pro Webcoached-Aktivität fix. |
| `<COURSEID>` | Die Moodle-Kurs-ID (z. B. aus der Kurs-URL `…/course/view.php?id=<COURSEID>`). Nur für den Abschluss-Aufruf nötig. |

> **Hinweis:** `<CMID>` ist **nicht** dieselbe ID wie die interne Webcoached-Kurs-ID
> (`remotecourseid`, z. B. 1111), die beim SSO als `course_id` gesendet wird.

---

## 4. Aktivitätsabschluss triggern

Verwendet die **Moodle-Standardfunktion** `core_grades_update_grades`. Es wird eine
Note in das Moodle-Notenbuch geschrieben; ist in der Aktivität die
Abschlussbedingung **„Bewertung erforderlich"** aktiv, wird die Aktivität dadurch
automatisch als **abgeschlossen** markiert.

### Parameter

| Parameter                  | Wert |
|----------------------------|------|
| `wstoken`                  | `<ADMIN_TOKEN>` |
| `wsfunction`               | `core_grades_update_grades` |
| `moodlewsrestformat`       | `json` |
| `source`                   | `mod_webcoached` |
| `courseid`                 | `<COURSEID>` |
| `component`                | `mod_webcoached` |
| `activityid`               | `<CMID>` |
| `itemnumber`               | `0` |
| `grades[0][studentid]`     | `<USERID>` |
| `grades[0][grade]`         | Notenwert (siehe unten) |

### Notenwert / „einfach abgeschlossen"

- **Einfach „abgeschlossen":** Senden Sie den Höchstwert der Aktivität (bei einer
  Punktebewertung z. B. `100`, bei einer Ja/Nein-Skala `2` für „Ja").
- **Echte Bewertung:** Senden Sie den tatsächlichen Punktwert (z. B. `80`).

### Beispiel (curl)

```bash
curl -s "<MOODLE_BASE_URL>/webservice/rest/server.php" \
  --data-urlencode "wstoken=<ADMIN_TOKEN>" \
  --data-urlencode "wsfunction=core_grades_update_grades" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "source=mod_webcoached" \
  --data-urlencode "courseid=<COURSEID>" \
  --data-urlencode "component=mod_webcoached" \
  --data-urlencode "activityid=<CMID>" \
  --data-urlencode "itemnumber=0" \
  --data-urlencode "grades[0][studentid]=<USERID>" \
  --data-urlencode "grades[0][grade]=100"
```

### Antwort

- **Erfolg:** Rückgabe `0` (entspricht „OK").
- **Mehrere Lernende** in einem Aufruf möglich:
  `grades[1][studentid]=…&grades[1][grade]=…` usw.
- **Idempotent:** Mehrfaches Senden überschreibt dieselbe Note – sicher
  wiederholbar.

---

## 5. Benachrichtigung an den User triggern

Verwendet die **plugin-eigene Funktion** `mod_webcoached_send_message`. Sie sendet
dem Lernenden eine Moodle-Benachrichtigung (Popup/E-Mail je nach dessen
Einstellungen). Der **Nachrichtentext** stammt aus den Aktivitätseinstellungen in
Moodle und enthält automatisch den Aktivitätsnamen und einen Link zur Aktivität.

### Parameter

| Parameter            | Wert |
|----------------------|------|
| `wstoken`            | `<ADMIN_TOKEN>` |
| `wsfunction`         | `mod_webcoached_send_message` |
| `moodlewsrestformat` | `json` |
| `cmid`               | `<CMID>` |
| `userid`             | `<USERID>` |
| `send_message`       | `1` (true = senden; `0` = nichts senden) |

### Beispiel (curl)

```bash
curl -s "<MOODLE_BASE_URL>/webservice/rest/server.php" \
  --data-urlencode "wstoken=<ADMIN_TOKEN>" \
  --data-urlencode "wsfunction=mod_webcoached_send_message" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "cmid=<CMID>" \
  --data-urlencode "userid=<USERID>" \
  --data-urlencode "send_message=1"
```

### Antwort

```json
{ "status": true, "warnings": [] }
```

- `status = true` → Benachrichtigung wurde gesendet.
- `status = false` mit einem `warnings`-Eintrag (Feld `warningcode`):

  | `warningcode` | Bedeutung / Abhilfe |
  |---------------|---------------------|
  | `notsent` | `send_message` war nicht `1`/`true`. |
  | `invaliduser` | Empfänger nicht gefunden oder inaktiv (gelöscht/gesperrt/Gast). |
  | `providernotregistered` | Der Benachrichtigungs-Provider `mod_webcoached/webcoachedmessage` ist auf der Site nicht registriert. Auf der Zielsite das Plugin-Upgrade ausführen (`php admin/cli/upgrade.php`) und Caches leeren (`php admin/cli/purge_caches.php`), dann erneut senden. |
  | `messagenotsent` | `message_send()` hat nicht zugestellt – z. B. gepuffert in einer offenen DB-Transaktion oder durch die Mitteilungseinstellungen des Empfängers blockiert. |

> Der Nachrichtentext wird in Moodle pro Aktivität gepflegt (Feld
> **„Benachrichtigungstext"**) und unterstützt die Platzhalter `{name}`
> (Aktivitätsname) und `{link}` (Link zur Aktivität). Webcoached muss **keinen**
> Text mitsenden – nur das Flag `send_message=1`.

---

## 6. Typischer Ablauf

```
Lernende/r startet Aktivität in Moodle
        │  (SSO-Login: Moodle sendet moodle_user_id, course_id …)
        ▼
Webcoached speichert moodle_user_id  →  führt das Training extern durch
        │
        ├─►  Training abgeschlossen:
        │      POST core_grades_update_grades   (Abschnitt 4)   → Aktivität abgeschlossen
        │
        └─►  Neue Nachricht in Webcoached:
               POST mod_webcoached_send_message (Abschnitt 5)   → User wird benachrichtigt
```

---

## 7. Fehlerbehandlung

Moodle liefert bei Problemen ein JSON-Objekt mit `exception`/`errorcode`, z. B.:

| `errorcode`                    | Ursache / Abhilfe |
|--------------------------------|-------------------|
| `invalidtoken`                 | `<ADMIN_TOKEN>` falsch oder abgelaufen → neues Token anfordern. |
| `accessexception` / `nopermissions` / `required_capability_exception` | Dienstkonto fehlt die nötige Capability (`moodle/grade:edit` bzw. `mod/webcoached:sendmessage`) im Kontext. |
| `invalidparameter` / `invalidrecord` | Falsche `courseid`/`cmid`/`userid`. |
| `webservice_access_exception`  | Die Funktion ist dem genutzten Service nicht zugeordnet, oder das Konto ist nicht autorisiert. |

Empfehlung: Aufrufe bei temporären Fehlern (Netzwerk/5xx) **wiederholen** – beide
Aufrufe sind idempotent bzw. ungefährlich bei Mehrfachausführung.

---

## 8. Schnellreferenz

| Aktion | `wsfunction` | Pflichtparameter (zusätzlich zu `wstoken`, `moodlewsrestformat=json`) |
|--------|--------------|------------------------------------------------------------------------|
| Abschluss / Note | `core_grades_update_grades` | `source=mod_webcoached`, `courseid`, `component=mod_webcoached`, `activityid=<CMID>`, `itemnumber=0`, `grades[0][studentid]=<USERID>`, `grades[0][grade]` |
| Nachricht senden | `mod_webcoached_send_message` | `cmid=<CMID>`, `userid=<USERID>`, `send_message=1` |

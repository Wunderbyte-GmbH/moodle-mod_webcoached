# Webcoached Training (mod_webcoached)

A Moodle activity module that bridges a Moodle course to an **external Webcoached
training** via single sign-on (SSO), and reports the learner's progress back into
Moodle as an **activity completion** and/or **grade**.

- **Outbound (Moodle → Webcoached):** when a learner opens the activity, Moodle
  builds an HMAC-SHA256 signed login payload and redirects (auto-posts) the
  browser to the Webcoached `directlogin` endpoint. The learner does the training
  outside Moodle.
- **Inbound (Webcoached → Moodle):** when the learner finishes the external
  training, Webcoached calls back into Moodle's **REST API** to write a grade,
  which automatically completes the activity. A teacher can do the same manually
  in Moodle.
- **Notifications (Webcoached → Moodle):** Webcoached can also call the plugin's
  REST API to **notify a learner** (with a configurable message) that there is a
  new message waiting for them in Webcoached.

- **Component:** `mod_webcoached`
- **Maturity:** stable · **Release:** v1.1.0
- **Requires:** Moodle 4.5 (`2024100400`) · **Supported:** 4.05, 5.02

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Site configuration](#site-configuration)
- [Adding an activity](#adding-an-activity)
- [How it works](#how-it-works)
  - [Outbound SSO interface](#outbound-sso-interface-moodle--webcoached)
  - [Completion & grading](#completion--grading)
- [REST API — completion / grade callback](#rest-api--completion--grade-callback)
  - [One-time setup: web service token](#one-time-setup-web-service-token)
  - [Calling `core_grades_update_grades`](#calling-core_grades_update_grades)
  - [Request parameters](#request-parameters)
  - [Examples](#examples)
  - [Simple "Completed" vs. real grade](#simple-completed-vs-real-grade)
  - [Behaviour & guarantees](#behaviour--guarantees)
- [REST API — message notification callback](#rest-api--message-notification-callback)
  - [Setup](#setup)
  - [Calling `mod_webcoached_send_message`](#calling-mod_webcoached_send_message)
  - [Request parameters](#request-parameters-1)
  - [Example](#example)
  - [Message body & placeholders](#message-body--placeholders)
- [Capabilities](#capabilities)
- [Library / interface functions](#library--interface-functions)
- [Database schema](#database-schema)
- [Backup & restore](#backup--restore)
- [Privacy](#privacy)
- [Testing](#testing)
- [Appendix: optional custom web service](#appendix-optional-custom-web-service)

---

## Requirements

- Moodle 4.5+ (PHP 8.1–8.3).
- An agreed **Client ID** and **Secret Key** with Webcoached for the SSO handshake.
- For the REST callback: the Moodle site's **web services** must be enabled and a
  **token** issued to a service account (see [REST API](#rest-api--completion--grade-callback)).

## Installation

1. Place the plugin in `mod/webcoached` (it is tracked as a git submodule in this
   install).
2. Visit **Site administration → Notifications** (or run
   `php admin/cli/upgrade.php`) to install/upgrade the database.
3. Configure the site settings (below).

## Site configuration

**Site administration → Plugins → Activity modules → Webcoached Training**
(`mod/webcoached/settings.php`):

| Setting        | Config key                   | Description |
|----------------|------------------------------|-------------|
| Client ID      | `mod_webcoached/client_id`   | SSO client id agreed with Webcoached (e.g. `moodle_test`). |
| Secret Key     | `mod_webcoached/secret_key`  | Shared secret used to sign the SSO payload (HMAC-SHA256). **Server-side only** — never exposed to the browser. |
| Endpoint URL   | `mod_webcoached/endpoint_url`| Webcoached browser SSO endpoint. Default: `https://www.webcoachedtraining.de/app/moodle/directlogin`. |

## Adding an activity

When adding/editing a **Webcoached Training** activity (`mod_form.php`):

| Field                | Description |
|----------------------|-------------|
| **Name / Title**     | Activity name shown in the course. |
| **Description**      | Standard intro text (optional). |
| **Webcoached course ID** (`remotecourseid`) | Numeric internal Webcoached course id sent outbound as `course_id` (e.g. `1111` Lesetechnik, `1112` Lesestrategie, `1113` Nachhaltigkeit). Fixed by Webcoached. |
| **Grade** (`grade`)  | Grade type for the activity — **None**, **Point** (max value), or **Scale** (e.g. a Yes/No scale for a simple "Completed"). Drives completion (see below). |
| **Notification message** (`messagebody`) | HTML body sent to the learner when the `send_message` REST call is triggered. Supports the placeholders `{name}` (activity name) and `{link}` (link to the activity). Pre-filled with a default; leave empty to use the language default. |
| **Activity completion** | Enable **"Student must receive a grade to complete this activity"** (`completionusegrade`) so a written grade auto-completes the activity. |

---

## How it works

### Outbound SSO interface (Moodle → Webcoached)

When a learner opens the activity with `?launch=1`, `view.php` builds a signed
login payload **for the current user** and auto-posts it to the endpoint.

Signed parameters (only these are included):

| Field            | Source |
|------------------|--------|
| `client_id`      | `mod_webcoached/client_id` |
| `timestamp`      | `time()` |
| `nonce`          | `bin2hex(random_bytes(16))` (32 hex chars) |
| `moodle_user_id` | `$USER->id` |
| `course_id`      | the activity's `remotecourseid` |
| `firstname`      | `$USER->firstname` |
| `lastname`       | `$USER->lastname` |

Signing:

```
canonical = http_build_query( ksort(params), RFC3986 )   // alphabetical, RFC3986-encoded
signature = hash_hmac('sha256', canonical, secret_key)
```

The `signature` is appended to the payload and all fields are submitted via an
auto-submitting HTML form (`templates/autopost.mustache`) from the **user's own
browser** (so the Webcoached session cookie is set in that browser). The secret
key never leaves the server.

> **Note:** `moodle_user_id` is sent outbound, so Webcoached can store it and send
> it back as `studentid` in the completion callback below.

### Completion & grading

The module is a **gradable activity** (`FEATURE_GRADE_HAS_GRADE`). It keeps **no
internal result table**: results live entirely in the Moodle **gradebook**
(`grade_grades`). A grade item is created per instance in
`webcoached_add_instance()`.

Completion uses **Moodle core's built-in `completionusegrade` condition** — no
custom completion rule. The cascade is automatic:

```
grade written  →  grade_grade::notify_changed  →  completion_info::inform_grade_changed
               →  completion_info::update_state  →  activity marked COMPLETE
```

So **any** path that writes a grade (REST, gradebook UI, teacher single-view)
completes the activity when `completionusegrade` is enabled.

**Three ways to complete the activity:**

1. **REST callback** — Webcoached writes a grade (see below).
2. **Teacher, with a grade** — enter a grade in the course **Gradebook** /
   single-view; completion follows automatically.
3. **Teacher, without a grade** — use the **Activity completion** report's
   per-user **override** (core capability `moodle/course:overridecompletion`).

---

## REST API — completion / grade callback

The inbound callback uses the **official Moodle core web service**
`core_grades_update_grades`. **No custom plugin endpoint is required** — the
module only has to be a properly gradable activity, which it is.

### One-time setup: web service token

As a site administrator:

1. **Enable web services:** *Site administration → Advanced features →* enable
   *Web services*; enable the **REST protocol**
   (*Server → Web services → Manage protocols*).
2. **Create a service account** (a dedicated user) and grant it the
   `moodle/grade:edit` capability in the relevant course(s) (e.g. via a role
   assigned at course or category level). This is the capability
   `core_grades_update_grades` checks when editing grades.
3. **Create an external service** (*Server → Web services → External services →
   Add*), tick *Authorised users only*, and add the function
   **`core_grades_update_grades`** to it.
4. **Authorise the service account** for that service and **create a token** for
   it (*Server → Web services → Manage tokens*).

The Webcoached system then calls Moodle with that `wstoken`.

### Calling `core_grades_update_grades`

```
POST https://<your-moodle>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=<TOKEN>
&wsfunction=core_grades_update_grades
&moodlewsrestformat=json
&source=mod_webcoached
&courseid=<COURSEID>
&component=mod_webcoached
&activityid=<CMID>
&itemnumber=0
&grades[0][studentid]=<MOODLE_USERID>
&grades[0][grade]=<VALUE>
```

`activityid` is the **course module id (cmid)** of the Webcoached activity — the
same `id` used in `view.php?id=…`. `studentid` is the Moodle user id (the value
sent outbound as `moodle_user_id`).

### Request parameters

| Parameter                 | Type    | Required | Description |
|---------------------------|---------|----------|-------------|
| `source`                  | string  | yes      | Free-text origin label, e.g. `mod_webcoached`. |
| `courseid`                | int     | yes      | Moodle course id containing the activity. |
| `component`               | string  | yes      | Must be `mod_webcoached`. |
| `activityid`              | int     | yes      | Course module id (cmid) of the activity. |
| `itemnumber`              | int     | yes      | Always `0` (single grade item). |
| `grades[n][studentid]`    | int     | yes      | Moodle user id of the learner. |
| `grades[n][grade]`        | float   | yes      | Grade value (see below). For a scale, use the scale item index (e.g. `2` = "Yes"). |
| `grades[n][str_feedback]` | string  | no       | Optional feedback text. |
| `itemdetails[...]`        | object  | no       | Grade-item overrides. **Leave empty** — the item already exists; sending it requires `moodle/grade:manage`. |

**Returns:** an integer status — `0` = OK (`GRADE_UPDATE_OK`). Non-zero indicates
failure.

### Examples

**Real grade (80 points) for user 42 in cmid 123, course 7:**

```bash
curl -s "https://moodle.example.org/webservice/rest/server.php" \
  --data-urlencode "wstoken=YOUR_TOKEN" \
  --data-urlencode "wsfunction=core_grades_update_grades" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "source=mod_webcoached" \
  --data-urlencode "courseid=7" \
  --data-urlencode "component=mod_webcoached" \
  --data-urlencode "activityid=123" \
  --data-urlencode "itemnumber=0" \
  --data-urlencode "grades[0][studentid]=42" \
  --data-urlencode "grades[0][grade]=80"
```

**Simple "Completed"** (Point grade item with max 100 → send full marks):

```bash
  ... --data-urlencode "grades[0][grade]=100"
```

**Batch** (multiple learners in one call):

```
grades[0][studentid]=42&grades[0][grade]=100
grades[1][studentid]=57&grades[1][grade]=100
```

### Simple "Completed" vs. real grade

Choose per activity in the **Grade** field:

- **Point** (e.g. max `100`): send the real score, or send the max value for a
  plain "completed".
- **Scale** (e.g. a `No,Yes` scale): send the scale index — `2` for "Yes" — for a
  pass/complete mark. The activity's `grade` field stores `-scaleid`.
- **None**: no grade item; use a teacher **completion override** instead of REST
  grades.

In all grade cases, with `completionusegrade` enabled the activity is marked
complete as soon as the grade is written.

### Behaviour & guarantees

- **Idempotent:** `core_grades_update_grades` upserts the single grade per
  `(item, user)`. Repeated callbacks keep one grade row and re-confirm completion
  — safe to retry.
- **Synchronous completion:** completion is recalculated inside the same request
  that writes the grade.
- **Capability enforced:** without `moodle/grade:edit` on the course the call
  throws an exception (no silent no-op).
- **Restore-safe:** core suppresses the completion cascade while a backup is being
  restored, so restoring grades does not mis-fire completion.

---

## REST API — message notification callback

A **plugin-specific** web service that sends a Moodle notification to a learner
when the external Webcoached system signals that there is a new message for them
in Webcoached. Unlike the grade/completion path, this is a **custom** function
provided by the plugin (`classes/external/send_message.php`).

The notification body is taken from the activity's **Notification message**
setting (or a language default). The learner can click the embedded link to open
the activity and SSO into Webcoached to read the message.

### Setup

In addition to enabling web services and the REST protocol (see above):

1. Grant the service account the capability **`mod/webcoached:sendmessage`** in the
   relevant context (course/category/system) — e.g. via a role.
2. Add the function **`mod_webcoached_send_message`** to an external service and
   issue a token. The plugin also ships a ready-made external service
   *"Webcoached external"* (shortname `mod_webcoached_external`, authorised users
   only) in `db/services.php` that you can authorise the account against.

### Calling `mod_webcoached_send_message`

```
POST https://<your-moodle>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=<TOKEN>
&wsfunction=mod_webcoached_send_message
&moodlewsrestformat=json
&cmid=<CMID>
&userid=<MOODLE_USERID>
&send_message=1
```

### Request parameters

| Parameter      | Type | Required | Description |
|----------------|------|----------|-------------|
| `cmid`         | int  | yes      | Course module id (cmid) of the Webcoached activity. |
| `userid`       | int  | yes      | Moodle user id of the recipient (the learner). |
| `send_message` | bool | no (default `true`) | When `1`/`true`, the notification is sent. When `0`/`false`, nothing is sent and `status` is `false`. |

**Returns:** `{ "status": true|false, "warnings": [...] }`. `status` is `true` when
a notification was sent. A `warnings` entry explains a `false` result:

| `warningcode` | Meaning |
|---------------|---------|
| `notsent` | `send_message` was not `true`. |
| `invaliduser` | Recipient not found or inactive (deleted/suspended/guest). |
| `providernotregistered` | The notification provider `mod_webcoached/webcoachedmessage` is not registered/available on the site. Run the plugin upgrade (`php admin/cli/upgrade.php`) and purge caches (`php admin/cli/purge_caches.php`), then retry. |
| `messagenotsent` | `message_send()` did not deliver — e.g. buffered inside an open DB transaction, or blocked by the recipient's messaging preferences. |

### Example

```bash
curl -s "https://moodle.example.org/webservice/rest/server.php" \
  --data-urlencode "wstoken=YOUR_TOKEN" \
  --data-urlencode "wsfunction=mod_webcoached_send_message" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "cmid=123" \
  --data-urlencode "userid=42" \
  --data-urlencode "send_message=1"
```

### Message body & placeholders

- The body comes from the activity's **Notification message** field
  (`messagebody`); if empty, the language string `messagebodydefault` is used.
- Two placeholders are substituted at send time:
  - `{name}` → the activity name,
  - `{link}` → a link to the activity (`/mod/webcoached/view.php?id=<cmid>`).
- The message is sent as a **notification** (`message_send`, provider
  `mod_webcoached/webcoachedmessage`, registered in `db/messages.php`) from the
  site no-reply user, with the activity link also attached as the notification's
  context URL. It appears in the recipient's Moodle notifications/email per their
  message preferences.

Default body (German install):

> Sie haben eine neue Nachricht für die Aktivität "*{name}*" erhalten. Klicken Sie
> auf den Link *{link}*, um auf der Plattform "Webcoached" die Nachricht
> einzusehen.

---

## Capabilities

Defined in `db/access.php`:

| Capability                  | Type  | Default roles | Purpose |
|-----------------------------|-------|---------------|---------|
| `mod/webcoached:addinstance`| write | editingteacher, manager | Add the activity to a course. |
| `mod/webcoached:view`       | read  | student, teacher, editingteacher, manager | View / launch the activity. |
| `mod/webcoached:sendmessage`| write | teacher, editingteacher, manager | Trigger the `mod_webcoached_send_message` REST callback. Grant to the web-service service account. |

The grade/completion REST callback relies on the **core** capability
`moodle/grade:edit`; teacher no-grade completion uses **core**
`moodle/course:overridecompletion`. The plugin defines its own
`mod/webcoached:sendmessage` only for the message callback (see the
[appendix](#appendix-optional-custom-web-service) if you also want a module-scoped
grading capability).

## Library / interface functions

Public API in `lib.php`:

| Function | Purpose |
|----------|---------|
| `webcoached_supports($feature)` | Declares features: intro, description, backup, **`FEATURE_GRADE_HAS_GRADE`**, **`FEATURE_GRADE_OUTCOMES`**, **`FEATURE_COMPLETION_TRACKS_VIEWS`**. |
| `webcoached_add_instance($formdata, $mform)` | Inserts the instance and creates its grade item. |
| `webcoached_update_instance($formdata, $mform)` | Updates the instance and syncs the grade item (e.g. grade-type changes). |
| `webcoached_delete_instance($id)` | Deletes the instance and its grade item. |
| `webcoached_grade_item_update($webcoached, $grades = null)` | Creates/updates the grade item; pass grades to write them (`'reset'` to reset). |
| `webcoached_update_grades($webcoached, $userid = 0, $nullifnone = true)` | Gradebook hook; ensures the grade item exists (no internal store to sync). |
| `webcoached_grade_item_delete($webcoached)` | Removes the grade item. |
| `webcoached_scale_used_anywhere($scaleid)` | Reports whether a scale is in use, so it cannot be deleted. |
| `webcoached_set_messagebody($data, $formdata)` | Copies the notification body from the form (editor array) or a plain string onto the instance record. |

External / interface files:

| File | Purpose |
|------|---------|
| `classes/external/send_message.php` | The `mod_webcoached_send_message` web service function. |
| `db/services.php` | Registers the function and the *"Webcoached external"* service. |
| `db/messages.php` | Registers the `webcoachedmessage` notification provider. |
| `db/access.php` | Capability definitions. |

## Database schema

Table `webcoached` (`db/install.xml`):

| Field            | Type      | Notes |
|------------------|-----------|-------|
| `id`             | int, PK   | |
| `course`         | int       | Course id. |
| `name`           | char(255) | Activity name. |
| `intro`          | text      | Description. |
| `introformat`    | int       | Intro format. |
| `remotecourseid` | char(255) | Webcoached course id sent outbound as `course_id`. |
| `grade`          | int       | Grade aggregate: **positive** = max points, **negative** = `-scaleid`, **0** = no grade. |
| `messagebody`    | text      | Notification body (supports `{name}`/`{link}`); empty falls back to the language default. |
| `messagebodyformat` | int    | Text format of `messagebody`. |
| `timecreated`    | int       | |
| `timemodified`   | int       | |

Per-user results are **not** stored here — they live in core `grade_grades`.

## Backup & restore

Standard Moodle backup/restore is supported (`FEATURE_BACKUP_MOODLE2`). The
instance (including `grade`) is backed up by the activity step; **grade items and
grades are backed up and restored automatically by core** — no extra handling
needed.

## Privacy

The plugin stores **no personal data locally** (`classes/privacy/provider.php`
implements the null provider). It only transmits a signed SSO payload to redirect
the user's browser. Grades written via the gradebook are covered by core's
gradebook privacy provider.

## Testing

Run the PHPUnit suite (this checkout uses the moodle-docker stack):

```bash
# After the schema/version change, re-init the test DB first:
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit mod/webcoached/tests/webcoached_test.php
```

`tests/webcoached_test.php` covers SSO signature generation, grade-item creation
(point and scale), the REST grade → completion cascade, idempotency, the
capability check, grade-item removal on delete, and the `send_message` callback
(notification sent, flag-false no-op, custom body, capability check).

---

## Appendix: optional custom web service

The core `core_grades_update_grades` path is recommended and sufficient. A small
custom external function (e.g. `mod_webcoached_set_completion`, modelled on
`mod_assign_save_grade`) would only be worth adding if you need:

- a **module-scoped** capability (e.g. `mod/webcoached:grade`) instead of
  course-level `moodle/grade:edit`;
- to accept a learner **`idnumber`/email** and resolve the Moodle user id
  server-side; or
- to set completion **without** writing a grade.

This is intentionally **not** implemented in the current release.

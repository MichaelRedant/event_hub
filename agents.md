# Event Hub - Agents Notes

## Structuur
- `event-hub.php`: plugin header, constants, autoloader, activatie -> `Activator` + `Migrations`.
- `includes/Plugin.php`: bootstrapt CPT/tax, meta boxes, shortcodes, admin columns, assets, blocks, Elementor, admin menus, settings, REST, logger.
- Kern classes: `CPT_Session` (CPT + meta + templates + shortcodes + admin notices/columns + sticky savebar + bulk mail), `Registrations` (DB CRUD, capaciteit/boekingsvenster, wachtlijst promotie, extra fields validatie, REST endpoint), `Emails` (placeholders, bevestiging/reminder/follow-up scheduling, per-event overrides), `Settings` (e-mail + algemene instellingen incl. reCAPTCHA, CPT/tax labels, single builder), `Admin_Menus` (menus, registratielijst/export, kalender AJAX, public calendar, eventdashboard, stats, logs), `CPT_Email`, `Blocks` (Gutenberg), `Elementor/*` (widgets), `Security`, `Locale`, `Logger`, `Migrations`, `Meta_Box_Field_Helper`.
- `assets/`: css/admin.css, css/frontend.css; js/admin-calendar.js (FullCalendar), js/admin-ui.js, js/frontend-form.js (REST submit), js/frontend-calendar.js (public calendar), js/blocks.js; vendor bevat FullCalendar fallback.
- `templates/single-event-hub.php`: standaard single layout (hero, details, sticky formulier).

## Belangrijke hooks en endpoints
- `Plugin::init()` registreert CPT/tax/shortcodes/meta boxes/admin columns, shared assets, block types, i18n, migraties, logger hooks en notices; bootstrapt Elementor op `elementor/loaded`; REST routes via `Registrations::register_rest_routes` op `rest_api_init`; admin AJAX `event_hub_calendar_events` en `event_hub_public_calendar` in `Admin_Menus`.
- Activator maakt tabel en seedt e-mailsjablonen, zet `event_hub_show_cpt_prompt`; `Migrations::run()` houdt `event_hub_db_version` in sync.
- E-mail hooks: `event_hub_registration_created`, `event_hub_waitlist_created`, `event_hub_waitlist_promoted`, `event_hub_registration_deleted`; cron acties `event_hub_send_reminder`, `event_hub_send_followup`.
- Front-end assets gelokaliseerd via `localize_frontend_assets` (REST endpoint/nonce) en `frontend-calendar.js` (AJAX URL + labels).
- Staff portal: REST `GET /wp-json/event-hub/v1/registrations` (requires `edit_posts`), Elementor widget `Medewerkersportaal` met JS `assets/js/staff-portal.js` voor tabel + CSV/HTML export (velden selecteerbaar).

## Registratieflow
- Front-end forms (Elementor widgets of shortcodes/blokken) posten naar REST `POST /wp-json/event-hub/v1/register` met nonce en optionele captcha token; `frontend-form.js` voegt honeypot `_eh_hp` toe en verstuurt JSON.
- Validatie: verplichte velden, e-mail, CAPTCHA via `Security::verify_token` (reCAPTCHA v3 of hCaptcha), boekingsvenster `_eh_booking_open/_eh_booking_close`, eventstatus (open/full), module toggle `_eh_enable_module`, capaciteit/wachtlijst met `people_count`, dubbele check per session/e-mail, rate limiting.
- Extra velden per event (`_eh_extra_fields`) worden gesanitized en opgeslagen als JSON. Statuslabels: registered, confirmed, cancelled, attended, no_show, waitlist.
- Annuleren: elke inschrijving krijgt `cancel_token`; `{cancel_link}` placeholder geeft URL `?eh_cancel=TOKEN` (publieke handler in `Plugin::maybe_handle_public_cancel`) of REST `GET /wp-json/event-hub/v1/cancel?token=...` die status op cancelled zet, capaciteit vrijmaakt en wachtlijst promoot.
- Capaciteit synct event meta status; promotie van wachtlijst bij deletes/updates (`promote_waitlist`). Admin edits bypassen restricties.

## Admin UI
- Menu: Info, Evenementen (CPT), E-mailsjablonen, Inschrijvingen (filters, badges, inline acties, CSV-export), Eventkalender (FullCalendar, create-on-click), E-mailinstellingen, Algemene instellingen, Eventdashboard (detail + export), Statistieken en Logs.
- Notices/prompts: CPT-keuze prompt + fallback naar ingebouwde CPT, notices voor ontbrekende templates en bulk mail resultaat, melding na CPT-save.
- Single template fallback `templates/single-event-hub.php`; `force_edit_referer_script` en sticky savebar in post edit schermen.

## Handige CLI/Dev
- Geen Composer; autoloader laadt namespace `EventHub\\` vanuit `includes/`.
- PHP lint: `php -l file.php`.
- Registratietabel: `{wp_prefix}eh_session_registrations`, DB versie `1.3.0`.
- Front-end test: REST endpoint `wp-json/event-hub/v1/register`; publieke kalender via AJAX `action=event_hub_public_calendar`.
- Medewerkersportaal test: logged-in user met `edit_posts`, REST endpoint `wp-json/event-hub/v1/registrations?session_id=ID`.
- CSV-export via admin UI; logs bekeken in `Admin_Menus::render_logs_page`.

## TODO/Ideeen
- Integraties (Zoom, betalingen) ontbreken nog.
- Extra filters/segmentatie voor admin kalender en statistieken.

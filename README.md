# Event Hub

Event Hub is een WordPress-plugin voor infosessies, opleidingen en webinars. De plugin combineert eventbeheer, inschrijvingen, e-mails en front-end weergave (Elementor + Gutenberg) in een lichte, Nederlandstalige ervaring.

- **Snel starten (beginner)**:
  1) Activeer de plugin en ga naar **Event Hub > Algemene instellingen**. Kies het type events: gebruik de ingebouwde `eh_session` of koppel je eigen CPT (selecteer slug + taxonomie).
  2) Ga naar **E-mailinstellingen** en vul afzender/naam in. Laat de standaard sjablonen staan of pas ze later aan.
  3) Maak een nieuw event aan bij **Evenementen**. Vul titel, datum, locatie/online link en (optioneel) capaciteit en boekingsvenster in. Publiceer.
  4) Plaats een formulier op de site via een blok (Gutenberg: Event Hub Form), een Elementor-widget (categorie “Event Hub”) of shortcode `[event_hub_session id="EVENT_ID"]`. De REST-API en scripts worden automatisch geladen.
  5) Test een inschrijving op de front-end. Controleer de inschrijving in **Event Hub > Inschrijvingen** of het **Eventdashboard**. Verzenden de mails? Pas zo nodig de sjablonen aan.

- **Events**: eigen CPT `eh_session` of koppel een bestaand CPT; configureer taxonomie, labels, menu-icoon en positie. Metaboxen voor datum/tijd, locatie of online link, status, capaciteit en boekingsvenster, prijs/no-show fee, taal/doelgroep, agenda, collega's, ticketnotitie en per-event extra formulier-velden. Single layout modern of compact, met optionele eigen HTML/CSS/JS.
- **Registraties**: eigen DB-tabel `eh_session_registrations` met REST endpoint (`POST /wp-json/event-hub/v1/register`) en JS helper (`assets/js/frontend-form.js`). Capaciteit en boekingsvenster checks, dubbele inschrijvingscontrole per event/e-mail, wachtlijst met automatische promotie, optionele reCAPTCHA (Google v3 of hCaptcha), honeypot en rate limiting. Admin kan inschrijvingen toevoegen/bewerken, status wijzigen en CSV-exporteren. Deelnemers kunnen zichzelf annuleren via een annulatielink.
- **E-mail**: e-mailsjablonen via CPT `eh_email`, placeholders, extra placeholders uit settings. Automatische bevestiging, reminder en follow-up (per event override mogelijk) met cron scheduling. Opties voor afzender, transportkeuze en offsets. Placeholder `{cancel_link}` beschikbaar voor zelf-annulatie.
- **Front-end weergave**: shortcodes `[event_hub_session id="123"]` en `[event_hub_list count="6" status="open"]`. Gutenberg-blokken: Session Detail, Session List, Calendar (FullCalendar, AJAX). Elementor-widgets: list, upcoming, detail, form, field, calendar, slider, collega's, agenda, status, en een medewerkersportaal (registraties bekijken + export).
- **Admin UX**: Event Hub menu met info, evenementen, e-mailsjablonen, inschrijvingen (filters + export), eventkalender (FullCalendar + create-on-click), eventdashboard (detail + export), statistieken en logs. Sticky savebar, admin columns en bulk e-mailactie per event.
- **Instellingen**: algemene instellingen voor CPT/tax, labels, single layout en custom code, reCAPTCHA provider/keys, builder-secties. E-mailinstellingen voor afzender, transport, reminder/follow-up offsets en eigen placeholders. Lokale SMTP-helper voor `local` environment.

## Systeemeisen
- WordPress 5.8+
- PHP 7.4+
- Elementor optioneel (alleen nodig voor Elementor-widgets); Gutenberg/shortcodes werken zonder.

## Installatie
1. Plaats `event_hub/` in `wp-content/plugins/` of upload de ZIP.
2. Activeer "Event Hub" in het WP-beheer (maakt de registratie-tabel aan en seedt standaard e-mailsjablonen).
3. Kies CPT/tax en layout onder **Event Hub > Algemene instellingen** (bestaand CPT of ingebouwde `eh_session`).
4. Zet afzender/transport en offsets onder **Event Hub > E-mailinstellingen**; voeg optioneel eigen placeholders toe.
5. Activeer optioneel reCAPTCHA/hCaptcha in de algemene instellingen.
6. Maak events aan en koppel e-mailsjablonen of custom teksten per event.
7. Voeg de front-end toe via Gutenberg-blokken, Elementor-widgets (categorie "Event Hub") of shortcodes; het formulier gebruikt automatisch het REST-endpoint en de geregistreerde assets (`event-hub-frontend`).

## Snelle referentie
- Shortcodes: `[event_hub_session id="123"]`, `[event_hub_list count="6" order="ASC" status="open"]`.
- Public calendar: Gutenberg-blok "Event Hub Calendar" of Elementor Calendar; gebruikt AJAX (`event_hub_public_calendar`) en `assets/js/frontend-calendar.js`.
- REST payload: `session_id`, `first_name`, `last_name`, `email`, optioneel `phone`, `company`, `vat`, `role`, `people_count`, `consent_marketing`, `extra[slug]`, `waitlist_opt_in`, `captcha_token`.
- Annuleren: `{cancel_link}` in je e-mailsjablonen geeft een link `?eh_cancel=TOKEN` (of `GET /wp-json/event-hub/v1/cancel?token=...`) waarmee deelnemers zichzelf op "cancelled" zetten en capaciteit vrijmaken.
- Medewerkersportaal: Elementor-widget “Medewerkersportaal” toont events (publish), haalt inschrijvingen via `GET /wp-json/event-hub/v1/registrations?session_id=...` (alleen ingelogd met `edit_posts`), laat velden kiezen en exporteren naar CSV of HTML.
- CSV-export: via **Event Hub > Inschrijvingen** of het eventdashboard.

## Ontwikkelaars
- Geen Composer; custom PSR-4 autoloader in `event-hub.php` laadt `includes/`.
- Migrations houden DB-versie bij (`Migrations::DB_VERSION`), activator zet defaults en transients.
- Shared front-end assets worden geregistreerd in `Plugin::register_shared_assets()`; admin kalenderscripts staan in `assets/js/admin-calendar.js`.
- REST-inschrijvingen zitten in `Registrations::register_rest_routes()`. Admin kalender/AJAX in `Admin_Menus`.
- Zie `agents.md` voor workflow- en hooknotities.

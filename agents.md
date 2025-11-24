# Event Hub – Agents Notes

## Structuur
- event-hub.php: bootstrap + PSR-4 autoloader.
- includes/ bevat alle classes (Activator, Registrations, Emails, Settings, CPT's, Elementor, etc.).
- ssets/css/admin.css: algemene admin-styling.
- ssets/js/admin-calendar.js: FullCalendar + create-on-click.

## Belangrijke hooks
- EventHub\Plugin::init() hangt alle acties/filters.
- Custom CPT slug/tax ingesteld via Settings (kan extern CPT gebruiken).
- Elementor-integratie bootstrapt zichzelf op elementor/loaded (widgets in includes/Elementor).

## Registratieflow
- Formulieren posten naar Registrations::create_registration().
- CAPTCHA-check via EventHub\Security (instelbaar).
- Emails plant reminders/follow-ups (cron) en vervangt placeholders.

## Handige CLI/Dev
- Geen Composer; autoloader is custom.
- Check PHP-lint lokaal: php -l file.php.
- Exporteer registraties via admin UI (CSV).

## TODO/Ideeën
- Front-end kalender widget.
- Extra filters voor admin-kalender (status, team).
- Integraties (Zoom, betalingen). 

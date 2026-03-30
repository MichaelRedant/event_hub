# Event Hub - Mail Robuustheid Roadmap

Laatst bijgewerkt: 2026-03-06

## Doel
E-mailverzending in Event Hub betrouwbaar maken voor shared hosting (PHP mail), met correcte automatische flows, duidelijke foutdiagnose en beheersbare templates.

## Reeds uitgevoerd

### Fase 1 - Kritieke fixes (afgerond)
- `waitlist_opt_in` wordt correct meegestuurd in REST registratieflow.
- Wachtlijst-promotie triggert enkel hooks bij echte DB-update (`$updated === 1`).
- CAPTCHA action mismatch opgelost (`event_hub_register`) met backward compatibility voor legacy action.
- Statusguards toegevoegd op kernmails:
  - confirmation
  - followup
  - waitlist created
  - waitlist promotion
  - registration cancelled
- Testmail in sjablonen geeft nu correcte `ok/fail` i.p.v. altijd succes.
- Sjablonen worden bij opslaan automatisch gepubliceerd vanuit `draft/auto-draft/pending` (voor gebruikers met edit-rechten).

### Fase 2 - Hardening (afgerond)
- Throttling uitgebreid zodat dubbele verzendingen verder gereduceerd zijn.
- Mailtype-normalisatie toegevoegd (`*_custom` -> basis type) voor consistente throttle/retry-keys.
- Retry-map uitgebreid, inclusief `event_cancelled` per registratie.
- Scheduling gededupliceerd: zelfde hook + args wordt niet opnieuw ingepland.
- `wp_mail_failed` foutboodschap wordt gecapteerd en gelogd voor betere diagnose.
- Testmail toont foutdetail in admin notice (indien beschikbaar).

### Extra UX-fixes (afgerond)
- Nieuwe events tonen nu automatisch de in E-mailinstellingen gekozen standaardtemplates als voorgeselecteerde event-templates.
- Bij manueel toevoegen van een inschrijving kan voor multi-events nu een specifieke datum/occurrence gekozen worden.

## Open aandachtspunten
- PHP CLI lint kon lokaal niet uitgevoerd worden in deze omgeving.
- Historische jobs in bestaande WP-Cron queues moeten na deploy gecontroleerd worden op duplicaten.
- Deliverability buiten plugin (SPF, DKIM, DMARC, hosting mail limits) blijft essentieel.

## Volgende stappen

### Fase 3 - Template governance en migraties
- [x] Nieuwe migratie toevoegen (DB version bump) voor mailcomponent.
- [x] Ontbrekende standaardtemplates automatisch (her)seeden op basis van `_eh_email_system_key`.
- [x] Controle op default template-koppelingen in settings/events en auto-herstel bij ontbrekende IDs.
- [x] Admin notice toevoegen bij ontbrekende of lege kernsjablonen.

Acceptatiecriteria:
- Op bestaande installaties worden ontbrekende defaults automatisch teruggezet zonder dataverlies.
- Bestaande aangepaste templates blijven onaangeraakt.
- Nieuwe installaties starten met complete, bruikbare defaults.

### Fase 4 - Monitoring en diagnose
- [x] Maildiagnosepaneel uitbreiden met:
  - laatste fout per type
  - retry teller
  - volgende geplande mailjobs
- [x] Extra context in logs (hook, timing mode, transportkeuze, retry-attempt).
- [x] Snelle "mail health check" actie in admin (config + template checks).

Acceptatiecriteria:
- Beheerder kan in 1 scherm zien waarom mails niet vertrekken.
- Herhaalproblemen zijn traceerbaar zonder server shell toegang.

### Fase 5 - E2E regressietesten
- [x] Testmatrix uitvoeren:
  - registratie (open event)
  - volzet + wachtlijst
  - wachtlijst promotie
  - annulatie door deelnemer
  - event annulatie
  - reminder/followup cron pad
- [x] Controle op:
  - juiste statusovergangen
  - exact 1 verzending per mailtype binnen throttle-venster
  - correcte placeholders in output

Acceptatiecriteria:
- Geen dubbele mails in standaardflows.
- Geen gemiste mails in de hoofdflows.
- Logging sluit aan op effectieve verzendingen/fouten.

### Fase 6 - Release en nazorg
- [ ] Release note opstellen met impact op mailflow.
- [ ] Safe deploy checklist (backup, deploy, cache clear, cron check, smoke test).
- [ ] 48u post-deploy observatie met focus op failed mail ratio.

Acceptatiecriteria:
- Upgrade zonder impact op bestaande inschrijvingsdata.
- Eventuele fouten zijn snel diagnoseerbaar en rollbackbaar.


# Event Hub Roadmap (post-launch)

Deze roadmap vat de gewenste aanpassingen samen en bundelt vaste e-mailsjablonen
als referentie voor implementatie in de seed/migraties.

## 1. Multi events (1 event, meerdere dagen/slots)
Doel: een event kan meerdere data hebben, elk met eigen inschrijfformulier en
eigen deelnemerslijst.

Scope-idee:
- Introduceer "occurrences" per event (sub-entity of herhaalvelden).
- Elke occurren
- ce heeft: datum/tijd, capaciteit, booking window, status.
- Registraties koppelen aan occurrence i.p.v. alleen parent event.
- UI: keuze van datum in formulier + aparte lijsten per occurrence.
- Kalender en admin-overzichten tonen occurrence als losse items.

Acceptatiecriteria:
- Meerdere data per event kunnen aanmaken en beheren.
- Inschrijvingen worden per datum gescheiden en exporteerbaar.
- Wachtlijst/capaciteit werkt per occurrence.

## 2. Betere connectie met CPT (sync/overname)
Doel: data uit de bestaande evenementen-CPT kunnen hergebruiken voor Event Hub
events om dubbel invullen te vermijden.

Scope-idee:
- Mapping-tool in admin: veldkoppelingen tussen extern CPT en Event Hub.
- Eenmalige import en/of "sync on save" via hooks.
- Conflictstrategie (externe CPT overschrijft, of alleen bij lege velden).

Acceptatiecriteria:
- Mapping instelbaar per site.
- Initiele import + optionele automatische sync bij CPT-save.
- Duidelijke logging/notices bij sync.

## 3. Vaste e-mailsjablonen (seed + defaults)
Doel: vaste HTML-sjablonen voor standaard flows; per event nog steeds override
mogelijk.

Actie:
- Activator/Migrations seedt onderstaande HTML in `eh_email` templates.
- Standaard subjects + bodies per type.

### 3.1 Bevestiging wachtlijst
Subject: `Bevestiging wachtlijst - {event_title}`

```html
<title>Bevestiging wachtlijst - {event_title}</title>

<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">
  Je staat op de wachtlijst voor {event_title}.
</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef3f6">
  <tr>
    <td align="center" style="padding:24px 12px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden">

        <!-- Header -->
        <tr>
          <td style="padding:22px 22px 14px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A">
              <div style="font-size:14px;letter-spacing:0.02em;color:#2b5f83;font-weight:700">
                {site_name}
              </div>
              <div style="margin-top:6px;font-size:20px;font-weight:800">
                Bevestiging wachtlijst
              </div>
              <div style="margin-top:6px;font-size:14px;color:#35556b;line-height:1.6">
                {event_title}
              </div>
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:20px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Dag {first_name},<br /><br />
              Bedankt voor je interesse in <strong>{event_title}</strong>.
            </div>

            <div style="margin-top:10px;font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Je staat momenteel op de <strong>wachtlijst</strong> voor dit event.
              Zodra er een plaats vrijkomt, brengen we je automatisch op de hoogte.
            </div>

            <div style="margin-top:14px;font-family:Arial, sans-serif;color:#35556b;font-size:14px;line-height:1.7">
              Praktische info ter herinnering:
              <ul style="margin:8px 0 0 18px;padding:0">
                <li><strong>Datum:</strong> {event_date}</li>
                <li><strong>Uur:</strong> {event_time}</li>
                <li><strong>Locatie:</strong> {event_location}</li>
              </ul>
            </div>

            <div style="margin-top:14px;font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Kan je intussen toch niet meer deelnemen?
              Dan hoef je niets te doen. Je plaats op de wachtlijst vervalt automatisch.
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:14px 22px 22px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Met vriendelijke groeten,<br />
              <strong>{site_name}</strong>
            </div>
            <div style="font-family:Arial, sans-serif;color:#6a8597;font-size:12px;line-height:1.6;margin-top:10px">
              {site_url}
            </div>
          </td>
        </tr>

      </table>

      <div style="height:18px;line-height:18px;font-size:18px">&nbsp;</div>
    </td>
  </tr>
</table>
```

### 3.2 Wachtlijst promotie
Subject: `Je bent ingeschreven - {event_title}`

```html
<title>Je bent ingeschreven - {event_title}</title>

<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">
  Goed nieuws! Je bent nu ingeschreven voor {event_title}.
</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef3f6">
  <tr>
    <td align="center" style="padding:24px 12px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden">

        <!-- Header -->
        <tr>
          <td style="padding:22px 22px 14px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A">
              <div style="font-size:14px;letter-spacing:0.02em;color:#2b5f83;font-weight:700">
                {site_name}
              </div>
              <div style="margin-top:6px;font-size:20px;font-weight:800">
                Je bent ingeschreven
              </div>
              <div style="margin-top:6px;font-size:14px;color:#35556b;line-height:1.6">
                {event_title}
              </div>
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:20px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Dag {first_name},<br /><br />
              Goed nieuws! Er is een plaats vrijgekomen en je bent nu
              <strong>officieel ingeschreven</strong> voor <strong>{event_title}</strong>.
            </div>

            <div style="margin-top:12px;font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Hieronder vind je nog even de praktische details:
            </div>
          </td>
        </tr>

        <!-- Details card -->
        <tr>
          <td style="padding:6px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7fbff;border-radius:14px">
              <tr>
                <td style="padding:14px 14px">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:13px;color:#2b5f83;font-weight:700;padding-bottom:6px">
                        Praktische info
                      </td>
                    </tr>

                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:14px;color:#0A1B2A;line-height:1.6">
                        <strong>Datum:</strong> {event_date}<br />
                        <strong>Uur:</strong> {event_time} - {event_end_time}<br />
                        <strong>Locatie:</strong> {event_location}<br />
                        <br />
                        <strong>Aantal personen:</strong> {people_count}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Cancel -->
        <tr>
          <td style="padding:14px 22px 8px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Kan je onverwacht toch niet aanwezig zijn?
              Annuleer dan tijdig via onderstaande knop zodat iemand anders je plaats kan innemen.
            </div>
          </td>
        </tr>

        <!-- Button -->
        <tr>
          <td align="left" style="padding:6px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#306b91" style="border-radius:999px">
                  <a href="{cancel_link}" style="display:inline-block;padding:12px 18px;font-family:Arial, sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;background:linear-gradient(135deg, #306b91, #3c7ca5)">
                    Annuleer mijn inschrijving
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:14px 22px 22px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Tot binnenkort!<br />
              <strong>{site_name}</strong>
            </div>
            <div style="font-family:Arial, sans-serif;color:#6a8597;font-size:12px;line-height:1.6;margin-top:10px">
              {site_url}
            </div>
          </td>
        </tr>

      </table>

      <div style="height:18px;line-height:18px;font-size:18px">&nbsp;</div>
    </td>
  </tr>
</table>
```

### 3.3 Bevestiging na inschrijving
Subject: `Je bent ingeschreven - {event_title}`

```html
<title>Inschrijving bevestigd - {event_title}</title>

<!-- Preheader (verborgen) -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">
  Je inschrijving voor {event_title} op {event_date} is bevestigd.
</div>

<!-- Wrapper -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef3f6;width:100%;margin:0;padding:0">
  <tr>
    <td align="center" style="padding:24px 12px">
      <!-- Container -->
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden">
        <!-- Header -->
        <tr>
          <td style="padding:22px 22px 14px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A">
              <div style="font-size:14px;letter-spacing:0.02em;color:#2b5f83;font-weight:700">
                {site_name}
              </div>
              <div style="margin-top:6px;font-size:20px;line-height:1.25;font-weight:800">
                Inschrijving bevestigd
              </div>
              <div style="margin-top:6px;font-size:14px;color:#35556b;line-height:1.6">
                {event_title}
              </div>
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:18px 22px 6px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Dag {first_name},<br /><br />
              Bedankt voor je inschrijving voor <strong>{event_title}</strong> op <strong>{event_date}</strong> om <strong>{event_time}</strong>.
            </div>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7fbff;border-radius:14px">
              <tr>
                <td style="padding:14px 14px">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:13px;color:#2b5f83;font-weight:700;padding-bottom:6px">
                        Praktische info
                      </td>
                    </tr>

                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:14px;color:#0A1B2A;line-height:1.6">
                        <strong>Locatie:</strong> {event_location}<br />
                        <br />
                        <strong>Aantal personen:</strong> {people_count}
                      </td>
                    </tr>

                    <!-- Optional: address (if you use it later) -->
                    <!--
                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:14px;color:#0A1B2A;line-height:1.6;padding-top:10px">
                        <strong>Adres:</strong> {event_address}
                      </td>
                    </tr>
                    -->
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Cancel -->
        <tr>
          <td style="padding:14px 22px 8px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Kan je toch niet komen? Annuleer eenvoudig via onderstaande knop.
            </div>
          </td>
        </tr>

        <!-- Button -->
        <tr>
          <td align="left" style="padding:6px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#306b91" style="border-radius:999px">
                  <a href="{cancel_link}" style="display:inline-block;padding:12px 18px;font-family:Arial, sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;background:linear-gradient(135deg, #306b91, #3c7ca5)">
                    Annuleer mijn inschrijving
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Fallback link -->
        <tr>
          <td style="padding:10px 22px 14px 22px">
            <div style="font-family:Arial, sans-serif;color:#35556b;font-size:12px;line-height:1.6">
              Werkt de knop niet? Kopieer dan deze link in je browser:<br />
              <a href="{cancel_link}" style="color:#306b91;text-decoration:underline">{cancel_link}</a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:14px 22px 22px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Tot snel!<br />
              <strong>{site_name}</strong>
            </div>
            <div style="font-family:Arial, sans-serif;color:#6a8597;font-size:12px;line-height:1.6;margin-top:10px">
              {site_url}
            </div>
          </td>
        </tr>
      </table>

      <!-- Small spacer -->
      <div style="height:18px;line-height:18px;font-size:18px">&nbsp;</div>
    </td>
  </tr>
</table>
```

### 3.4 Herinnering voor start
Subject: `Herinnering - {event_title}`

```html
<title>Herinnering - {event_title}</title>

<!-- Preheader (verborgen) -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">
  Je inschrijving voor {event_title} op {event_date} is bevestigd.
</div>

<!-- Wrapper -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef3f6;width:100%;margin:0;padding:0">
  <tr>
    <td align="center" style="padding:24px 12px">
      <!-- Container -->
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden">
        <!-- Header -->
        <tr>
          <td style="padding:22px 22px 14px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A">
              <div style="font-size:14px;letter-spacing:0.02em;color:#2b5f83;font-weight:700">
                {site_name}
              </div>
              <div style="margin-top:6px;font-size:20px;line-height:1.25;font-weight:800">
                Herinnering
              </div>
              <div style="margin-top:6px;font-size:14px;color:#35556b;line-height:1.6">
                {event_title}
              </div>
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:18px 22px 6px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:15px;line-height:1.7">
              Dag {first_name},<br /><br />
              Dit is een korte herinnering: je bent ingeschreven voor <strong>{event_title}</strong> op <strong>{event_date}</strong> om <strong>{event_time}</strong>.
            </div>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7fbff;border-radius:14px">
              <tr>
                <td style="padding:14px 14px">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:13px;color:#2b5f83;font-weight:700;padding-bottom:6px">
                        Praktische info
                      </td>
                    </tr>

                    <tr>
                      <td style="font-family:Arial, sans-serif;font-size:14px;color:#0A1B2A;line-height:1.6">
                        <strong>Locatie:</strong> {event_location}<br />
                        <br />
                        <strong>Aantal personen:</strong> {people_count}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Cancel -->
        <tr>
          <td style="padding:14px 22px 8px 22px">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Kan je toch niet komen? Annuleer eenvoudig via onderstaande knop.
            </div>
          </td>
        </tr>

        <!-- Button -->
        <tr>
          <td align="left" style="padding:6px 22px 6px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#306b91" style="border-radius:999px">
                  <a href="{cancel_link}" style="display:inline-block;padding:12px 18px;font-family:Arial, sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;background:linear-gradient(135deg, #306b91, #3c7ca5)">
                    Annuleer mijn inschrijving
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Fallback link -->
        <tr>
          <td style="padding:10px 22px 14px 22px">
            <div style="font-family:Arial, sans-serif;color:#35556b;font-size:12px;line-height:1.6">
              Werkt de knop niet? Kopieer dan deze link in je browser:<br />
              <a href="{cancel_link}" style="color:#306b91;text-decoration:underline">{cancel_link}</a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:14px 22px 22px 22px;background:#ffffff">
            <div style="font-family:Arial, sans-serif;color:#0A1B2A;font-size:14px;line-height:1.7">
              Tot snel!<br />
              <strong>{site_name}</strong>
            </div>
            <div style="font-family:Arial, sans-serif;color:#6a8597;font-size:12px;line-height:1.6;margin-top:10px">
              {site_url}
            </div>
          </td>
        </tr>
      </table>

      <!-- Small spacer -->
      <div style="height:18px;line-height:18px;font-size:18px">&nbsp;</div>
    </td>
  </tr>
</table>
```

### 3.5 Nog aan te maken (HTML)
- Follow-up (nadien)
- Event geannuleerd (bij verwijderen uit CPT)
- Inschrijving geannuleerd (bij status cancelled)

## 4. Extra velden (standaard verbergen + sync)
Doel: standaard extra velden verbergen (aantal personen, rol, opt-in marketing)
en bij verversen de juiste defaults laten doorvoeren.

Scope-idee:
- Verberg defaults in formulierconfig of per-event extra fields.
- Op refresh herlezen van settings en updaten van veldconfig.
- Houd bestaande registraties intact.

Acceptatiecriteria:
- Nieuwe events starten met deze velden verborgen.
- Bij refresh blijven instellingen in sync.

## 5. Verplichte velden + dynamische extra personen
Doel: inschrijfformulier aanvullen met verplichte bedrijfsgegevens en dynamische
velden voor extra deelnemers (zelfde bedrijf).

Scope-idee:
- Verplicht maken: BTW-nummer, bedrijfsnaam, adresgegevens.
- Als `people_count` > 1: toon per extra persoon velden voor voornaam, naam en
  e-mailadres.
- Aantal extra personen = `people_count - 1`.
- Validatie in REST: elke extra persoon moet volledig ingevuld zijn.
- Opslag in registratie: extra personen opslaan als gestructureerde JSON.
- Weergave in admin/exports.

Acceptatiecriteria:
- Inschrijven faalt als BTW/bedrijf/adres ontbreekt.
- Extra personenvelden verschijnen dynamisch en schalen mee met `people_count`.
- Extra personen worden opgeslagen en zichtbaar in admin/export.

## 6. Annuleerpagina: teksten instelbaar (succes/fout)
Doel: tekst op de annuleerpagina kunnen aanpassen, zowel bij succesvolle
annulering als bij mislukte/ongeldige annulering.

Scope-idee:
- Instellingen in admin voor "Annulering gelukt" en "Annulering mislukt".
- Ondersteuning voor basis HTML (bijv. links, vet, korte paragrafen).
- Fallback naar standaardteksten indien leeg.
- Eventueel placeholders zoals {event_title} en {site_name}.

Acceptatiecriteria:
- Teksten zijn configureerbaar via admin.
- Annuleerpagina toont correcte variant (succes/fout).

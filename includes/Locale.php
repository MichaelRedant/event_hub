<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Locale
{
    /**
     * Cache per-request language detection.
     *
     * @var bool|null
     */
    private static ?bool $is_french = null;

    public function init(): void
    {
        add_filter('gettext', [$this, 'translate'], 10, 3);
    }

    /**
     * Runtime Flemish (nl_BE) translations for our text domain.
     * This avoids needing a compiled .mo and keeps things user-friendly.
     */
    public function translate(string $translated, string $text, string $domain): string
    {
        if ($domain !== 'event-hub') {
            return $translated;
        }

        $is_fr = $this->is_french_request();

        static $map = [
            // Admin labels
            'Event Hub' => 'Event Hub',
            'Event Hub — Info' => 'Event Hub — Info',
            'Infosessies' => 'Infosessies',
            'Registrations' => 'Inschrijvingen',
            'Email Settings' => 'E-mailinstellingen',
            'General Settings' => 'Algemene instellingen',
            'Name' => 'Naam',
            'Version' => 'Versie',
            'Author' => 'Auteur',
            'Manage Sessions' => 'Beheer events',
            'All Sessions' => 'Alle events',
            'All Statuses' => 'Alle statussen',
            'Filter' => 'Filteren',
            'No registrations found.' => 'Geen inschrijvingen gevonden.',
            'Registration not found.' => 'Inschrijving niet gevonden.',
            'Saved.' => 'Opgeslagen.',
            'Failed to save.' => 'Opslaan mislukt.',
            'Deleted.' => 'Verwijderd.',
            'Failed to delete.' => 'Verwijderen mislukt.',
            'Consent marketing' => 'Marketingtoestemming',
            'Yes' => 'Ja',

            // Settings (General/CAPTCHA)
            'General' => 'Algemeen',
            'Security (CAPTCHA)' => 'Beveiliging (CAPTCHA)',
            'Use external CPT' => 'Extern CPT gebruiken',
            'Use an existing event post type (e.g. from JetEngine, ACF, CPT UI, Pods). Event Hub will not register its own CPT.' => 'Gebruik een bestaand event post type (bv. JetEngine, ACF, CPT UI, Pods). Event Hub registreert dan geen eigen CPT.',
            'Toggle this if your site already has a custom post type for events.' => 'Zet dit aan als je site al een custom post type voor events heeft.',
            'Event CPT slug' => 'Event CPT‑slug',
            'Start typing to search available post types. Choose the CPT that stores your events.' => 'Begin te typen om beschikbare post types te zoeken. Kies het CPT waarin je events staan.',
            'Event Type taxonomy slug' => 'Eventtype‑taxonomie‑slug',
            'Start typing to search available taxonomies. If it does not exist, Event Hub will register it and attach to the CPT.' => 'Begin te typen om beschikbare taxonomieën te zoeken. Bestaat het niet, dan maakt Event Hub het aan en koppelt het aan het CPT.',
            'Security (CAPTCHA)' => 'Beveiliging (CAPTCHA)',
            'Enable CAPTCHA' => 'CAPTCHA inschakelen',
            'Require CAPTCHA on registration form' => 'CAPTCHA verplichten op het inschrijfformulier',
            'Provider' => 'Provider',
            'Google reCAPTCHA v3' => 'Google reCAPTCHA v3',
            'hCaptcha' => 'hCaptcha',
            'Site key' => 'Site key',
            'Secret key' => 'Secret key',
            'Score threshold (v3)' => 'Score‑drempel (v3)',
            'Only for Google reCAPTCHA v3. Default 0.5' => 'Enkel voor Google reCAPTCHA v3. Standaard 0,5',
            'Save Changes' => 'Wijzigingen opslaan',

            // Elementor list widget
            'Infosessie List' => 'Infosessielijst',
            'Content' => 'Inhoud',
            'Number of events' => 'Aantal events',
            'Language filter' => 'Taalfilter',
            'Session type' => 'Type',
            'Only future events' => 'Enkel toekomstige events',
            'Yes' => 'Ja',
            'No' => 'Nee',
            'Layout' => 'Lay‑out',
            'List' => 'Lijst',
            'Card' => 'Kaart',
            'Show excerpt' => 'Toon samenvatting',
            'Show View details button' => 'Toon “Meer info”-knop',
            'Show search bar' => 'Toon zoekbalk',
            'Show availability badge' => 'Toon beschikbaarheidsbadge',
            'All types' => 'Alle types',
            'Search…' => 'Zoeken…',
            'Available' => 'Beschikbaar',
            'Full' => 'Volzet',
            'Closed' => 'Gesloten',
            'No upcoming sessions found.' => 'Geen komende events gevonden.',
            'View details' => 'Meer informatie',
            '%d place available' => '%d plaats beschikbaar',
            '%d places available' => '%d plaatsen beschikbaar',

            // Elementor detail widget + form
            'Infosessie Detail + Registration' => 'Eventdetail + Inschrijving',
            'Use current post (event hub session)' => 'Gebruik huidige event',
            'Select Session' => 'Selecteer event',
            'Join link' => 'Deelnamelink',
            'Capacity: %d available' => 'Capaciteit: %d beschikbaar',
            'This event is full' => 'Dit event is volzet',
            'This event is cancelled' => 'Dit event is geannuleerd',
            'This event is full or inactive.' => 'Dit event is volzet of inactief.',
            'Booking period not open yet.' => 'De inschrijvingen zijn nog niet geopend.',
            'Booking period is closed.' => 'De inschrijvingen zijn afgesloten.',
            'No session selected.' => 'Geen event geselecteerd.',
            'First name' => 'Voornaam',
            'Last name' => 'Familienaam',
            'Email' => 'E‑mail',
            'Phone' => 'Telefoon',
            'Company' => 'Bedrijf',
            'VAT' => 'BTW‑nummer',
            'Role' => 'Rol',
            'People' => 'Aantal personen',
            'I agree to receive marketing updates' => 'Ik ga akkoord om marketingupdates te ontvangen',
            'Register' => 'Inschrijven',
            'Thank you for registering! Please check your email for confirmation.' => 'Bedankt voor je inschrijving! Kijk je e‑mail na voor bevestiging.',

            // Errors
            'CAPTCHA validatie mislukt. Probeer opnieuw.' => 'CAPTCHA‑validatie mislukt. Probeer opnieuw.',
            'Bookings are not open yet for this event' => 'Inschrijvingen zijn nog niet geopend voor dit event',
            'Bookings are closed for this event' => 'Inschrijvingen zijn afgesloten voor dit event',
            'This event is full' => 'Dit event is volzet',
        ];

        static $map_fr = [
            // Frontend + modal labels
            'Deel dit event' => 'Partager cet événement',
            'Inschrijven' => 'S’inscrire',
            'Voornaam' => 'Prénom',
            'Familienaam' => 'Nom de famille',
            'E-mail' => 'E-mail',
            'Telefoon' => 'Téléphone',
            'Bedrijf' => 'Entreprise',
            'BTW-nummer' => 'TVA',
            'Rol' => 'Rôle',
            'Aantal personen' => 'Nombre de personnes',
            'Maak een keuze' => 'Choisissez une option',
            'Ik wil relevante communicatie ontvangen.' => 'Je souhaite recevoir des communications pertinentes.',
            'Zet me op de wachtlijst indien volzet.' => 'Mettez-moi sur la liste d’attente si c’est complet.',
            'Op wachtlijst plaatsen' => 'Placer sur liste d’attente',
            'Bedankt! We hebben je inschrijving ontvangen.' => 'Merci ! Nous avons bien reçu votre inscription.',
            'Bedankt! Je staat nu op de wachtlijst.' => 'Merci ! Vous êtes maintenant sur la liste d’attente.',
            'Inschrijvingen zijn gesloten.' => 'Les inscriptions sont clôturées.',
            'Inschrijven kan vanaf %s.' => 'Les inscriptions ouvrent le %s.',
            'Dit event is volzet. Vul je gegevens in om op de wachtlijst te komen.' => 'Cet événement est complet. Laissez vos coordonnées pour la liste d’attente.',
            'Inschrijvingen voor dit event verlopen extern.' => 'Les inscriptions pour cet événement se font ailleurs.',
            'Maak een keuze' => 'Choisissez une option',
            'Prijs' => 'Prix',
            'Beschikbaarheid' => 'Disponibilité',
            'Geen wachtlijst' => 'Pas de liste d’attente',
            'Kopieer link' => 'Copier le lien',
            'Deel dit event:' => 'Partager cet événement :',
            // Buttons / placeholders
            'Meer informatie' => 'Plus d’informations',
        ];

        if ($is_fr && isset($map_fr[$text])) {
            return $map_fr[$text];
        }
        if (isset($map[$text])) {
            return $map[$text];
        }
        return $translated;
    }

    /**
     * Detecteer of de huidige request Frans is (simplistisch op /fr/ in de slug).
     */
    private function is_french_request(): bool
    {
        if (self::$is_french !== null) {
            return self::$is_french;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        self::$is_french = (strpos($uri, '/fr/') !== false || substr($uri, -3) === '/fr');
        return self::$is_french;
    }
}

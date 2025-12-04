<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Settings
{
    public const OPTION = 'event_hub_email_settings';
    public const OPTION_GENERAL = 'event_hub_general_settings';

    public function register_settings(): void
    {
        // E-mail instellingen
        register_setting(self::OPTION, self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);

        add_settings_section('octo_emails_main', __('E-mailinstellingen', 'event-hub'), function () {
            echo '<p>' . esc_html__('Stel de e-mailafzender en sjablonen in voor inschrijvingen en herinneringen.', 'event-hub') . '</p>';
        }, self::OPTION);

        $fields = [
            ['from_name', __('Afzendernaam', 'event-hub'), 'text'],
            ['from_email', __('Afzender e-mail', 'event-hub'), 'email'],
            ['mail_transport', __('Mailtransport', 'event-hub'), 'select'],
            ['reminder_offset_days', __('Herinnering (dagen voor start)', 'event-hub'), 'number'],
            ['followup_offset_hours', __('Nadien (uren na einde)', 'event-hub'), 'number'],
            ['custom_placeholders_raw', __('Eigen placeholders', 'event-hub'), 'textarea'],
        ];

        foreach ($fields as [$key, $label, $type]) {
            add_settings_field($key, $label, function () use ($key, $type) {
                $opts = get_option(self::OPTION, []);
                $val = $opts[$key] ?? ';
                $name = self::OPTION . "[{$key}]";
                if ($type === 'textarea') {
                    echo '<textarea class="large-text code" rows="6" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
                    echo '<p class="description">' . esc_html__('Één placeholder per lijn, formaat: mijn_token=Mijn waarde. Gebruik accolades { } om de tokennaam te markeren.', 'event-hub') . '</p>';
                } elseif ($type === 'number') {
                    echo '<input type="number" min="0" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
                } elseif ($type === 'select' && $key === 'mail_transport') {
                    $options = [
                        'php' => __('PHP mail (standaard)', 'event-hub'),
                        'smtp_plugin' => __('SMTP via mailgateway plugin', 'event-hub'),
                    ];
                    echo '<select name="' . esc_attr($name) . '">';
                    foreach ($options as $opt_val => $opt_label) {
                        echo '<option value="' . esc_attr($opt_val) . '"' . selected($val, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="description">' . esc_html__('Kies PHP mail (hosting) of laat een SMTP/mailgateway plugin het transport overnemen.', 'event-hub') . '</p>';
                } else {
                    echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
                }
            }, self::OPTION, 'octo_emails_main');
        }

        // Algemene instellingen
        register_setting(self::OPTION_GENERAL, self::OPTION_GENERAL, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_general_settings'],
            'default' => [
                'use_external_cpt' => 0,
                'cpt_slug' => 'eh_session',
                'tax_slug' => 'eh_session_type',
                'single_layout' => 'modern',
                'single_custom_enabled' => 0,
                'single_custom_css' => '',
                'single_custom_js' => '',
                'single_custom_html_before_hero' => '',
                'single_custom_html_after_hero' => '',
                'single_custom_html_before_form' => '',
                'single_custom_html_after_form' => '',
                'single_custom_html_after_details' => '',
                'single_builder_sections' => '[]',
                'enable_recaptcha' => 0,
                'recaptcha_provider' => 'google_recaptcha_v3',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'recaptcha_score' => 0.5,
            ],
        ]);

        add_settings_section('eh_general_main', __('Algemeen', 'event-hub'), function () {
            echo '<p>' . esc_html__('Bepaal welke CPT en taxonomie Event Hub gebruikt voor evenementen.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL);

        add_settings_field('use_external_cpt', __('Event CPT kiezen', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $use_external = !empty($opts['use_external_cpt']);
            $current_slug = $opts['cpt_slug'] ?? 'eh_session';
            $select_name = self::OPTION_GENERAL . '[cpt_slug]';
            $radio_name  = self::OPTION_GENERAL . '[use_external_cpt]';
            $post_types = get_post_types(['show_ui' => true], 'objects');
            $disallow = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];
            $choices = [];
            foreach ($post_types as $slug => $obj) {
                if (in_array($slug, $disallow, true)) {
                    continue;
                }
                $label = isset($obj->labels->singular_name) ? $obj->labels->singular_name : $slug;
                $choices[$slug] = $label;
            }
            echo '<div class="eh-cpt-choice">';
            echo '<p><label><input type="radio" name="' . esc_attr($radio_name) . '" value="0" ' . checked(!$use_external, true, false) . ' /> ' . esc_html__('Gebruik de standaard Event Hub CPT (eh_session)', 'event-hub') . '</label></p>';
            echo '<p class="description">' . esc_html__('Kies dit als je nog geen eigen CPT hebt of de ingebouwde Event Hub CPT wil gebruiken.', 'event-hub') . '</p>';
            echo '<hr />';
            echo '<p><label><input type="radio" name="' . esc_attr($radio_name) . '" value="1" ' . checked($use_external, true, false) . ' /> ' . esc_html__('Gebruik een bestaand CPT', 'event-hub') . '</label></p>';
            if ($choices) {
                echo '<select name="' . esc_attr($select_name) . '" class="regular-text">';
                echo '<option value="eh_session"' . selected($current_slug, 'eh_session', false) . '>' . esc_html__('Event Hub (standaard) - eh_session', 'event-hub') . '</option>';
                foreach ($choices as $slug => $label) {
                    echo '<option value="' . esc_attr($slug) . '"' . selected($current_slug, $slug, false) . '>' . esc_html($label . ' (' . $slug . ')') . '</option>';
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__('Selecteer een bestaand post type (JetEngine, CPT UI, Pods, ...). Event Hub registreert geen eigen CPT wanneer je dit kiest.', 'event-hub') . '</p>';
            } else {
                echo '<p class="description">' . esc_html__('Geen bestaande CPTs gevonden. Kies de standaard Event Hub CPT of maak eerst een CPT aan.', 'event-hub') . '</p>';
            }
            echo '</div>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('tax_slug', __('Eventtype-taxonomie-slug', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['tax_slug'] ?? 'eh_session_type';
            $name = self::OPTION_GENERAL . '[tax_slug]';
            $current_cpt = $opts['cpt_slug'] ?? 'eh_session';
            $tax_objects = function_exists('get_object_taxonomies') ? get_object_taxonomies($current_cpt, 'objects') : [];
            if (empty($tax_objects)) {
                $tax_objects = get_taxonomies(['show_ui' => true], 'objects');
            }
            $datalist_id = 'eh_tax_list';
            echo '<input type="text" class="regular-text" list="' . esc_attr($datalist_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" placeholder="bv. event_type, session_type" />';
            echo '<datalist id="' . esc_attr($datalist_id) . '">';
            foreach ($tax_objects as $tx) {
                $label = isset($tx->labels->singular_name) ? $tx->labels->singular_name : $tx->name;
                echo '<option value="' . esc_attr($tx->name) . '">' . esc_html($label) . '</option>';
            }
            echo '</datalist>';
            echo '<p class="description">' . esc_html__('Begin te typen om beschikbare taxonomieen te zoeken. Bestaat het niet, dan maakt Event Hub het aan en koppelt het aan het CPT.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('single_layout', __('Single event layout', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['single_layout'] ?? 'modern';
            $name = self::OPTION_GENERAL . '[single_layout]';
            $choices = [
                'modern' => __('Modern (hero + sticky formulier)', 'event-hub'),
                'compact' => __('Compact (stapel, lichtgewicht)', 'event-hub'),
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($choices as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Kies de standaard layout voor de single event pagina. Thema-overrides blijven mogelijk.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('single_custom_enabled', __('Eigen layout inschakelen', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $name = self::OPTION_GENERAL . '[single_custom_enabled]';
            $checked = !empty($opts['single_custom_enabled']) ? 'checked' : ';
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> ' . esc_html__('Gebruik eigen HTML/CSS/JS voor de single event pagina', 'event-hub') . '</label>';
            echo '<p class="description">' . esc_html__('Aan: de velden hieronder worden ingespoten op de single. Uit: standaard layout zonder custom injecties.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('single_custom_css', __('Custom CSS (single)', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['single_custom_css'] ?? ';
            $name = self::OPTION_GENERAL . '[single_custom_css]';
            echo '<textarea class="large-text code eh-custom-field" data-custom-field="css" rows="5" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
            echo '<p class="description">' . esc_html__('Wordt inline toegevoegd op de single event pagina (bovenaan in <style>). Handig voor snelle kleur/spacing tweaks.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('single_custom_js', __('Custom JS (single)', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['single_custom_js'] ?? ';
            $name = self::OPTION_GENERAL . '[single_custom_js]';
            echo '<textarea class="large-text code eh-custom-field" data-custom-field="js" rows="5" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
            echo '<p class="description">' . esc_html__('Wordt inline toegevoegd (zonder <script> tag) onderaan de single event pagina. Ideaal voor kleine DOM tweaks.', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        $html_blocks = [
            'single_custom_html_before_hero' => __('Status (boven hero)', 'event-hub'),
            'single_custom_html_after_hero' => __('Hero (onder hero)', 'event-hub'),
            'single_custom_html_after_details' => __('Praktische info (onder content)', 'event-hub'),
            'single_custom_html_before_form' => __('Sidebar formulier (boven formulier)', 'event-hub'),
            'single_custom_html_after_form' => __('Formulier (onder formulier)', 'event-hub'),
        ];
        foreach ($html_blocks as $key => $label) {
            add_settings_field($key, $label, function () use ($key) {
                $opts = get_option(self::OPTION_GENERAL, []);
                $val = $opts[$key] ?? ';
                $name = self::OPTION_GENERAL . '[' . $key . ']';
                echo '<textarea class="large-text code eh-custom-field" data-custom-field="html" rows="4" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
                echo '<p class="description">' . esc_html__('HTML/shortcodes, geen PHP. Wordt op de aangegeven plek ingevoegd.', 'event-hub') . '</p>';
            }, self::OPTION_GENERAL, 'eh_general_main');
        }

        // Drag/drop builder: order + styles per sectie (hero, info, form, etc.)
        add_settings_field('single_builder_sections', __('Layout builder', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['single_builder_sections'] ?? '[]';
            $default = [
                ['id' => 'status', 'title' => __('Status', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#f8fafc', 'heading' => '', 'cta' => '', 'fontSize' => 16, 'padding' => 16, 'paddingMobile' => 14, 'bgImage' => '', 'gradient' => '', 'variant' => 'default'],
                ['id' => 'hero', 'title' => __('Hero', 'event-hub'), 'accent' => '#1d4ed8', 'bg' => '#0f172a', 'heading' => '', 'cta' => '', 'fontSize' => 18, 'padding' => 18, 'paddingMobile' => 16, 'bgImage' => '', 'gradient' => '', 'variant' => 'default'],
                ['id' => 'info', 'title' => __('Praktische info', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#ffffff', 'heading' => '', 'cta' => '', 'fontSize' => 16, 'padding' => 16, 'paddingMobile' => 14, 'bgImage' => '', 'gradient' => '', 'variant' => 'default'],
                ['id' => 'content', 'title' => __('Content', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#ffffff', 'heading' => '', 'cta' => '', 'fontSize' => 16, 'padding' => 16, 'paddingMobile' => 14, 'bgImage' => '', 'gradient' => '', 'variant' => 'default'],
                ['id' => 'form', 'title' => __('Formulier', 'event-hub'), 'accent' => '#10b981', 'bg' => '#ffffff', 'heading' => '', 'cta' => '', 'fontSize' => 16, 'padding' => 18, 'paddingMobile' => 14, 'bgImage' => '', 'gradient' => '', 'variant' => 'default'],
            ];
            $decoded = json_decode(is_string($val) ? $val : '[]', true);
            $sections = is_array($decoded) && $decoded ? $decoded : $default;
            echo '<input type="hidden" name="event_hub_general_settings[single_builder_sections]" id="eh-builder-data" value="' . esc_attr(wp_json_encode($sections)) . '">';
            echo '<div id="eh-builder-list" class="eh-builder-list" style="border:1px solid #e5e7eb;border-radius:10px;padding:8px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.05);"></div>';
            echo '<p class="description">' . esc_html__('Sleep om volgorde te wijzigen. Accent/bg/gradient/heading per sectie. Leeg laten = standaard.', 'event-hub') . '</p>';
            echo '<div style="display:flex;gap:8px;align-items:center;margin-top:8px;">';
            echo '<select id="eh-builder-add-select" style="min-width:180px;">';
            $extra_opts = [
                'quote' => __('Quote', 'event-hub'),
                'faq' => __('FAQ', 'event-hub'),
                'agenda' => __('Agenda', 'event-hub'),
                'buttons' => __('CTA knoppen', 'event-hub'),
                'gallery' => __('Gallery', 'event-hub'),
                'card' => __('Vrij blok', 'event-hub'),
                'textblock' => __('Tekstblok', 'event-hub'),
            ];
            foreach ($extra_opts as $k => $label) {
                echo '<option value="' . esc_attr($k) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<button type="button" class="button" id="eh-builder-add-btn">' . esc_html__('Sectie toevoegen', 'event-hub') . '</button>';
            echo '</div>';
                    echo '<script>';
        echo '(function(){';
        echo 'const cssField=document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_css]"]\');';
        echo 'const jsField=document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_js]"]\');';
        echo 'const slots={';
        echo 'beforeHero: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_before_hero]"]\'),' ;
        echo 'afterHero: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_hero]"]\'),' ;
        echo 'beforeForm: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_before_form]"]\'),' ;
        echo 'afterForm: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_form]"]\'),' ;
        echo 'afterDetails: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_details]"]\')';
        echo '};';
        echo 'const targets={';
        echo 'beforeHero: document.getElementById("eh-preview-before-hero"),';
        echo 'afterHero: document.getElementById("eh-preview-after-hero"),';
        echo 'beforeForm: document.getElementById("eh-preview-before-form"),';
        echo 'afterForm: document.getElementById("eh-preview-after-form"),';
        echo 'afterDetails: document.getElementById("eh-preview-after-details")';
        echo '};';
        echo 'const styleEl=document.getElementById("eh-single-preview-css");';
        echo 'const preview=document.getElementById("eh-single-preview");';
        echo 'const previewBody=document.getElementById("eh-single-preview-body");';
        echo 'const toggle=document.querySelector(\'input[name="event_hub_general_settings[single_custom_enabled]"]\');';
        echo 'const btnDesktop=document.getElementById("eh-preview-btn-desktop");';
        echo 'const btnMobile=document.getElementById("eh-preview-btn-mobile");';
        echo 'const hideableRows=[...document.querySelectorAll("tr")].filter(function(row){ return row.querySelector(".eh-custom-field"); });';
        echo 'function renderAssets(){ if(styleEl && cssField){styleEl.textContent=cssField.value;} Object.keys(slots).forEach(function(key){ if(slots[key] && targets[key]){ targets[key].innerHTML = slots[key].value; }}); if(preview){const old=preview.querySelector(".eh-preview-js"); if(old){old.remove();} if(jsField && jsField.value){const s=document.createElement("script"); s.className="eh-preview-js"; s.textContent=jsField.value; preview.appendChild(s);}} }';
        echo '[cssField,jsField].forEach(function(el){ if(el){ el.addEventListener("input", renderAssets);} });';
        echo 'Object.values(slots).forEach(function(el){ if(el){ el.addEventListener("input", renderAssets);} });';
        echo 'function toggleFields(){ const on = toggle ? toggle.checked : false; hideableRows.forEach(function(row){ row.style.display = on ? "" : "none"; }); }';
        echo 'if(toggle){ toggle.addEventListener("change", toggleFields); toggleFields(); }';
        echo 'const builderInput=document.getElementById("eh-builder-data");';
        echo 'function readSections(){ try { return JSON.parse(builderInput ? builderInput.value : "[]") || []; } catch(e){ return []; } }';
        echo 'function setMode(mode){ if(!preview) return; preview.dataset.mode = mode; preview.style.maxWidth = mode === "mobile" ? "430px" : "100%"; preview.style.margin = mode === "mobile" ? "0 auto" : ""; if(btnDesktop){ btnDesktop.classList.toggle("button-primary", mode==="desktop"); } if(btnMobile){ btnMobile.classList.toggle("button-primary", mode==="mobile"); } }';
        echo 'if(btnDesktop){ btnDesktop.addEventListener("click", function(){ setMode("desktop"); }); }';
        echo 'if(btnMobile){ btnMobile.addEventListener("click", function(){ setMode("mobile"); }); }';
        echo 'setMode("desktop");';
        echo 'function renderSections(sections){ if(!previewBody) return; const rows = Array.isArray(sections) ? sections : readSections(); const html=rows.map(function(s){ const accent = s.accent || "#0f172a"; const bg = s.bg || ""; const heading = s.title || s.id; const fontSize = s.fontSize ? parseInt(s.fontSize,10) : 14; const pad = s.padding ? parseInt(s.padding,10) : 12; const padM = s.paddingMobile ? parseInt(s.paddingMobile,10) : pad; const grad = s.gradient || ""; const bgImg = s.bgImage || ""; const wrapStyles = []; if(grad){ if(grad==="sunset"){ wrapStyles.push("background:linear-gradient(135deg,#f97316,#ef4444);"); } else if(grad==="mint"){ wrapStyles.push("background:linear-gradient(135deg,#10b981,#34d399);"); } else if(grad==="ocean"){ wrapStyles.push("background:linear-gradient(135deg,#0ea5e9,#6366f1);"); } } else if(bg){ wrapStyles.push("background:"+bg+";"); } if(bgImg){ wrapStyles.push("background-image:url("+bgImg+");background-size:cover;background-position:center;"); } if(accent){ wrapStyles.push("--eh-accent:"+accent+";"); } if(pad){ wrapStyles.push("padding:"+pad+"px;"); } if(padM){ wrapStyles.push("--eh-pad-mobile:"+padM+"px;"); } let body = s.body || ""; if(!body){ switch(s.id){ case "status": body='<div class="eh-stat-chip"><h4>Beschikbaarheid</h4><p style="font-size:'+fontSize+'px" data-field="body" data-id="'+s.id+'">24/80</p></div><div class="eh-stat-chip"><h4>Wachtlijst</h4><p style="font-size:'+fontSize+'px" data-field="body2" data-id="'+s.id+'">3</p></div>'; break; case "hero": body='<div style="color:#fff;"><div style="font-size:11px;opacity:.9;">Status</div><div class="eh-preview-title" data-field="heading" data-id="'+s.id+'">'+heading+'</div><div style="font-size:'+(fontSize-2)+'px;opacity:.85;" data-field="sub" data-id="'+s.id+'">12 maart 10:00 - 13:00 | Online</div><div style="margin-top:6px;display:inline-block;background:'+accent+';color:#fff;padding:6px 10px;border-radius:999px;font-size:'+fontSize+'px;" class="eh-preview-cta" data-id="'+s.id+'">'+(s.cta||'CTA')+'</div></div>'; break; case "info": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Adres / link / organisator</div>'; break; case "content": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Content / beschrijving</div>'; break; case "form": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Formulier</div>'; break; case "quote": body='<blockquote style="font-size:'+fontSize+'px;opacity:.9;" data-field="body" data-id="'+s.id+'">Quote voorbeeld<br><small>- Naam</small></blockquote>'; break; case "faq": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'"><strong>Vraag 1</strong><br>Antwoord 1</div>'; break; case "agenda": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">- 10:00 Intro<br>- 10:30 Spreker</div>'; break; case "buttons": body='<div style="font-size:'+fontSize+'px;display:flex;gap:6px;flex-wrap:wrap;"><span class="eh-chip" data-field="cta" data-id="'+s.id+'" style="background:'+accent+';color:#fff;">'+(s.cta||'Knop A')+'</span><span class="eh-chip" style="background:'+accent+';color:#fff;">Knop B</span></div>'; break; case "gallery": body='<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;"><div style="background:#e2e8f0;height:40px;"></div><div style="background:#e2e8f0;height:40px;"></div><div style="background:#e2e8f0;height:40px;"></div></div>'; break; case "textblock": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Tekstblok inhoud</div>'; break; default: body='<div style="font-size:'+fontSize+'px;opacity:.7;" data-field="body" data-id="'+s.id+'">Custom blok</div>'; } } return `<section class="eh-preview-card eh-pad-mobile" data-id="${s.id}" style="${wrapStyles.join('')}"><div class="eh-preview-heading" data-id="${s.id}">${heading}</div><div class="eh-preview-body">${body}</div></section>`; }).join(""); previewBody.innerHTML = html || "<div style='padding:12px;font-size:13px;opacity:.7;'>Geen secties</div>"; attachHeadingEditors(); attachCtaEditors(); attachBodyEditors(); }';
        echo 'function attachHeadingEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-heading").forEach(function(h){ h.setAttribute("contenteditable","true"); h.addEventListener("input", function(){ const id=h.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.title = h.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'function attachCtaEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-cta").forEach(function(c){ c.setAttribute("contenteditable","true"); c.addEventListener("input", function(){ const id=c.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.cta = c.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); previewBody.querySelectorAll(".eh-preview-body [data-field=\\"cta\\"]").forEach(function(c){ c.setAttribute("contenteditable","true"); c.addEventListener("input", function(){ const id=c.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.cta = c.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'function attachBodyEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-body [data-field=\\"body\\"]").forEach(function(el){ el.setAttribute("contenteditable","true"); el.addEventListener("input", function(){ const id=el.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.body = el.innerHTML.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'window.EHPreview = { updateSections: function(payload){ renderSections(payload); } };';
        echo 'renderSections(); renderAssets();';
        echo '})();';
        echo '</script>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('enable_recaptcha', __('reCAPTCHA beveiligen', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $name = self::OPTION_GENERAL . '[enable_recaptcha]';
            $checked = !empty($opts['enable_recaptcha']) ? 'checked' : ';
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . ' /> ' . esc_html__('Activeer spamfilter op alle formulieren.', 'event-hub') . '</label>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_provider', __('reCAPTCHA aanbieder', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_provider'] ?? 'google_recaptcha_v3';
            $name = self::OPTION_GENERAL . '[recaptcha_provider]';
            $providers = [
                'google_recaptcha_v3' => 'Google reCAPTCHA v3',
                'hcaptcha' => 'hCaptcha',
            ];
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($providers as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_site_key', __('Publieke sleutel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_site_key'] ?? ';
            $name = self::OPTION_GENERAL . '[recaptcha_site_key]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_secret_key', __('Geheime sleutel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = $opts['recaptcha_secret_key'] ?? ';
            $name = self::OPTION_GENERAL . '[recaptcha_secret_key]';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('recaptcha_score', __('Scoredrempel', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $val = isset($opts['recaptcha_score']) ? (float) $opts['recaptcha_score'] : 0.5;
            $name = self::OPTION_GENERAL . '[recaptcha_score]';
            echo '<input type="number" step="0.1" min="0" max="1" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" />';
            echo '<p class="description">' . esc_html__('Hoe hoger, hoe strenger de spamfilter (standaard 0.5).', 'event-hub') . '</p>';
        }, self::OPTION_GENERAL, 'eh_general_main');

        add_settings_field('colleagues', __('Collega\'s', 'event-hub'), function () {
            $opts = get_option(self::OPTION_GENERAL, []);
            $colleagues = isset($opts['colleagues']) && is_array($opts['colleagues']) ? $opts['colleagues'] : [];
            // Bepaal per collega in hoeveel events ze geselecteerd zijn
            $usage = [];
            $events = get_posts([
                'post_type' => Settings::get_cpt_slug(),
                'numberposts' => -1,
                'fields' => 'ids',
            ]);
            foreach ($events as $eid) {
                $selected = get_post_meta((int) $eid, '_eh_colleagues', true);
                $ids = is_array($selected) ? array_map('intval', $selected) : [];
                foreach ($ids as $cid) {
                    $usage[$cid] = ($usage[$cid] ?? 0) + 1;
                }
            }
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }
            echo '<div class="eh-colleagues-list" data-template="#eh-colleague-settings-template">';
            $index = 0;
            foreach ($colleagues as $colleague) {
                $first = $colleague['first_name'] ?? ';
                $last  = $colleague['last_name'] ?? ';
                $role  = $colleague['role'] ?? ';
                $photo = (int) ($colleague['photo_id'] ?? 0);
                $count = $usage[$index] ?? 0;
                echo '<div class="eh-colleague-row" data-index="' . esc_attr((string) $index) . '">';
                echo '<div><label>' . esc_html__('Voornaam', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][first_name]" value="' . esc_attr($first) . '"></div>';
                echo '<div><label>' . esc_html__('Familienaam', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][last_name]" value="' . esc_attr($last) . '"></div>';
                echo '<div><label>' . esc_html__('Functie', 'event-hub') . '</label><input type="text" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][role]" value="' . esc_attr($role) . '"></div>';
                echo '<div class="eh-colleague-photo"><label>' . esc_html__('Foto', 'event-hub') . '</label>';
                echo '<input type="hidden" class="eh-colleague-photo-id" name="' . esc_attr(self::OPTION_GENERAL) . '[colleagues][' . esc_attr((string) $index) . '][photo_id]" value="' . esc_attr((string) $photo) . '" />';
                echo '<div class="eh-colleague-photo-preview">';
                if ($photo) {
                    echo wp_get_attachment_image($photo, [60, 60]);
                }
                echo '</div>';
                echo '<button type="button" class="button eh-colleague-photo-btn">' . esc_html__('Kies foto', 'event-hub') . '</button>';
                echo '</div>';
                echo '<div class="eh-colleague-usage">' . sprintf(esc_html__('%d events', 'event-hub'), (int) $count) . '</div>';
                echo '<button type="button" class="button-link-delete eh-colleague-remove">' . esc_html__('Verwijder', 'event-hub') . '</button>';
                echo '</div>';
                $index++;
            }
            echo '</div>';
            echo '<button type="button" class="button eh-colleague-add">' . esc_html__('Collega toevoegen', 'event-hub') . '</button>';
            ?>
            <template id="eh-colleague-settings-template">
                <div class="eh-colleague-row" data-index="__index__">
                    <div>
                        <label><?php echo esc_html__('Voornaam', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][first_name]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('Familienaam', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][last_name]" value="">
                    </div>
                    <div>
                        <label><?php echo esc_html__('Functie', 'event-hub'); ?></label>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][role]" value="">
                    </div>
                    <div class="eh-colleague-photo">
                        <label><?php echo esc_html__('Foto', 'event-hub'); ?></label>
                        <input type="hidden" class="eh-colleague-photo-id" name="<?php echo esc_attr(self::OPTION_GENERAL); ?>[colleagues][__index__][photo_id]" value="">
                        <div class="eh-colleague-photo-preview"></div>
                        <button type="button" class="button eh-colleague-photo-btn"><?php echo esc_html__('Kies foto', 'event-hub'); ?></button>
                    </div>
                    <button type="button" class="button-link-delete eh-colleague-remove"><?php echo esc_html__('Verwijder', 'event-hub'); ?></button>
                </div>
            </template>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var wrap = document.querySelector('.eh-colleagues-list');
                    var addBtn = document.querySelector('.eh-colleague-add');
                    var tpl = document.querySelector('#eh-colleague-settings-template');
                    if (!wrap || !addBtn || !tpl || typeof wp === 'undefined' || !wp.media) { return; }
                    function bindPhoto(btn) {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            var row = btn.closest('.eh-colleague-row');
                            var input = row.querySelector('.eh-colleague-photo-id');
                            var preview = row.querySelector('.eh-colleague-photo-preview');
                            var frame = wp.media({ title: '<?php echo esc_js(__('Selecteer foto', 'event-hub')); ?>', button: { text: '<?php echo esc_js(__('Gebruiken', 'event-hub')); ?>' }, multiple: false });
                            frame.on('select', function () {
                                var attachment = frame.state().get('selection').first().toJSON();
                                input.value = attachment.id;
                                var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                                preview.innerHTML = '<img src="' + url + '" alt="" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">';
                            });
                            frame.open();
                        });
                    }
                    function bindRow(row) {
                        var remove = row.querySelector('.eh-colleague-remove');
                        if (remove) {
                            remove.addEventListener('click', function (e) {
                                e.preventDefault();
                                row.remove();
                            });
                        }
                        var photoBtn = row.querySelector('.eh-colleague-photo-btn');
                        if (photoBtn) {
                            bindPhoto(photoBtn);
                        }
                    }
                    wrap.querySelectorAll('.eh-colleague-row').forEach(bindRow);
                    addBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var idx = wrap.querySelectorAll('.eh-colleague-row').length;
                        var html = tpl.innerHTML.replace(/__index__/g, idx);
                        var temp = document.createElement('div');
                        temp.innerHTML = html.trim();
                        var row = temp.firstElementChild;
                        wrap.appendChild(row);
                        bindRow(row);
                    });
                } catch (err) {
                    if (window.console) { console.warn('[EventHub] Settings colleagues init failed', err); }
                }
            });
            </script>
            <?php
        }, self::OPTION_GENERAL, 'eh_general_main');

        // Geen aparte WP-instellingenpagina; pagina's zitten onder Event Hub menu (Admin_Menus).
    }

    public function render_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Je hebt geen rechten om deze pagina te openen.', 'event-hub'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Event Hub e-mails', 'event-hub') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION);
        do_settings_sections(self::OPTION);
        submit_button(__('Wijzigingen opslaan', 'event-hub'));
        echo '</form>';
        echo '</div>';
    }

    public function render_general_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toegang tot deze pagina.', 'event-hub'));
        }
        $opts = self::get_general();
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Event Hub - Algemeen', 'event-hub') . '</h1>';
        echo '<div class="eh-settings-grid" style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start;">';
        echo '<div class="eh-settings-main">';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GENERAL);
        do_settings_sections(self::OPTION_GENERAL);
        submit_button(__('Wijzigingen opslaan', 'event-hub'));
        echo '</form>';
        echo '</div>'; // main

        // Compact live preview rechts van de velden
        echo '<div class="eh-preview-box" style="position:sticky;top:100px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 10px 25px rgba(15,23,42,.06);">';
        echo '<h2 style="margin-top:0;font-size:16px;">' . esc_html__('Live preview', 'event-hub') . '</h2>';
        echo '<p style="color:#475569;font-size:13px;margin-top:0;">' . esc_html__('Wijzig HTML/CSS/JS velden; dit voorbeeld update meteen.', 'event-hub') . '</p>';
        echo '<style id="eh-single-preview-css">' . ($opts['single_custom_css'] ?? '') . '</style>';
        echo '<div id="eh-preview-toolbar" style="display:flex;gap:6px;margin:8px 0 10px;">';
        echo '<button type="button" class="button button-small" id="eh-preview-btn-desktop">' . esc_html__('Desktop', 'event-hub') . '</button>';
        echo '<button type="button" class="button button-small" id="eh-preview-btn-mobile">' . esc_html__('Mobile', 'event-hub') . '</button>';
        echo '</div>';
        echo '<div id="eh-single-preview" class="eh-single layout-modern" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;font-family:system-ui;-webkit-font-smoothing:antialiased;min-height:240px;background:#f8fafc;transition:width .2s ease, max-width .2s ease;">';
        echo '<div id="eh-single-preview-body" style="padding:10px;display:grid;gap:10px;"></div>';
        echo '</div>';
                echo '<script>';
        echo '(function(){';
        echo 'const cssField=document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_css]"]\');';
        echo 'const jsField=document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_js]"]\');';
        echo 'const slots={';
        echo 'beforeHero: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_before_hero]"]\'),' ;
        echo 'afterHero: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_hero]"]\'),' ;
        echo 'beforeForm: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_before_form]"]\'),' ;
        echo 'afterForm: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_form]"]\'),' ;
        echo 'afterDetails: document.querySelector(\'textarea[name="event_hub_general_settings[single_custom_html_after_details]"]\')';
        echo '};';
        echo 'const targets={';
        echo 'beforeHero: document.getElementById("eh-preview-before-hero"),';
        echo 'afterHero: document.getElementById("eh-preview-after-hero"),';
        echo 'beforeForm: document.getElementById("eh-preview-before-form"),';
        echo 'afterForm: document.getElementById("eh-preview-after-form"),';
        echo 'afterDetails: document.getElementById("eh-preview-after-details")';
        echo '};';
        echo 'const styleEl=document.getElementById("eh-single-preview-css");';
        echo 'const preview=document.getElementById("eh-single-preview");';
        echo 'const previewBody=document.getElementById("eh-single-preview-body");';
        echo 'const toggle=document.querySelector(\'input[name="event_hub_general_settings[single_custom_enabled]"]\');';
        echo 'const btnDesktop=document.getElementById("eh-preview-btn-desktop");';
        echo 'const btnMobile=document.getElementById("eh-preview-btn-mobile");';
        echo 'const hideableRows=[...document.querySelectorAll("tr")].filter(function(row){ return row.querySelector(".eh-custom-field"); });';
        echo 'function renderAssets(){ if(styleEl && cssField){styleEl.textContent=cssField.value;} Object.keys(slots).forEach(function(key){ if(slots[key] && targets[key]){ targets[key].innerHTML = slots[key].value; }}); if(preview){const old=preview.querySelector(".eh-preview-js"); if(old){old.remove();} if(jsField && jsField.value){const s=document.createElement("script"); s.className="eh-preview-js"; s.textContent=jsField.value; preview.appendChild(s);}} }';
        echo '[cssField,jsField].forEach(function(el){ if(el){ el.addEventListener("input", renderAssets);} });';
        echo 'Object.values(slots).forEach(function(el){ if(el){ el.addEventListener("input", renderAssets);} });';
        echo 'function toggleFields(){ const on = toggle ? toggle.checked : false; hideableRows.forEach(function(row){ row.style.display = on ? "" : "none"; }); }';
        echo 'if(toggle){ toggle.addEventListener("change", toggleFields); toggleFields(); }';
        echo 'const builderInput=document.getElementById("eh-builder-data");';
        echo 'function readSections(){ try { return JSON.parse(builderInput ? builderInput.value : "[]") || []; } catch(e){ return []; } }';
        echo 'function setMode(mode){ if(!preview) return; preview.dataset.mode = mode; preview.style.maxWidth = mode === "mobile" ? "430px" : "100%"; preview.style.margin = mode === "mobile" ? "0 auto" : ""; if(btnDesktop){ btnDesktop.classList.toggle("button-primary", mode==="desktop"); } if(btnMobile){ btnMobile.classList.toggle("button-primary", mode==="mobile"); } }';
        echo 'if(btnDesktop){ btnDesktop.addEventListener("click", function(){ setMode("desktop"); }); }';
        echo 'if(btnMobile){ btnMobile.addEventListener("click", function(){ setMode("mobile"); }); }';
        echo 'setMode("desktop");';
        echo 'function renderSections(sections){ if(!previewBody) return; const rows = Array.isArray(sections) ? sections : readSections(); const html=rows.map(function(s){ const accent = s.accent || "#0f172a"; const bg = s.bg || ""; const heading = s.title || s.id; const fontSize = s.fontSize ? parseInt(s.fontSize,10) : 14; const pad = s.padding ? parseInt(s.padding,10) : 12; const padM = s.paddingMobile ? parseInt(s.paddingMobile,10) : pad; const grad = s.gradient || ""; const bgImg = s.bgImage || ""; const wrapStyles = []; if(grad){ if(grad==="sunset"){ wrapStyles.push("background:linear-gradient(135deg,#f97316,#ef4444);"); } else if(grad==="mint"){ wrapStyles.push("background:linear-gradient(135deg,#10b981,#34d399);"); } else if(grad==="ocean"){ wrapStyles.push("background:linear-gradient(135deg,#0ea5e9,#6366f1);"); } } else if(bg){ wrapStyles.push("background:"+bg+";"); } if(bgImg){ wrapStyles.push("background-image:url("+bgImg+");background-size:cover;background-position:center;"); } if(accent){ wrapStyles.push("--eh-accent:"+accent+";"); } if(pad){ wrapStyles.push("padding:"+pad+"px;"); } if(padM){ wrapStyles.push("--eh-pad-mobile:"+padM+"px;"); } let body = s.body || ""; if(!body){ switch(s.id){ case "status": body='<div class="eh-stat-chip"><h4>Beschikbaarheid</h4><p style="font-size:'+fontSize+'px" data-field="body" data-id="'+s.id+'">24/80</p></div><div class="eh-stat-chip"><h4>Wachtlijst</h4><p style="font-size:'+fontSize+'px" data-field="body2" data-id="'+s.id+'">3</p></div>'; break; case "hero": body='<div style="color:#fff;"><div style="font-size:11px;opacity:.9;">Status</div><div class="eh-preview-title" data-field="heading" data-id="'+s.id+'">'+heading+'</div><div style="font-size:'+(fontSize-2)+'px;opacity:.85;" data-field="sub" data-id="'+s.id+'">12 maart 10:00 - 13:00 | Online</div><div style="margin-top:6px;display:inline-block;background:'+accent+';color:#fff;padding:6px 10px;border-radius:999px;font-size:'+fontSize+'px;" class="eh-preview-cta" data-id="'+s.id+'">'+(s.cta||'CTA')+'</div></div>'; break; case "info": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Adres / link / organisator</div>'; break; case "content": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Content / beschrijving</div>'; break; case "form": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Formulier</div>'; break; case "quote": body='<blockquote style="font-size:'+fontSize+'px;opacity:.9;" data-field="body" data-id="'+s.id+'">Quote voorbeeld<br><small>- Naam</small></blockquote>'; break; case "faq": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'"><strong>Vraag 1</strong><br>Antwoord 1</div>'; break; case "agenda": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">- 10:00 Intro<br>- 10:30 Spreker</div>'; break; case "buttons": body='<div style="font-size:'+fontSize+'px;display:flex;gap:6px;flex-wrap:wrap;"><span class="eh-chip" data-field="cta" data-id="'+s.id+'" style="background:'+accent+';color:#fff;">'+(s.cta||'Knop A')+'</span><span class="eh-chip" style="background:'+accent+';color:#fff;">Knop B</span></div>'; break; case "gallery": body='<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;"><div style="background:#e2e8f0;height:40px;"></div><div style="background:#e2e8f0;height:40px;"></div><div style="background:#e2e8f0;height:40px;"></div></div>'; break; case "textblock": body='<div style="font-size:'+fontSize+'px;" data-field="body" data-id="'+s.id+'">Tekstblok inhoud</div>'; break; default: body='<div style="font-size:'+fontSize+'px;opacity:.7;" data-field="body" data-id="'+s.id+'">Custom blok</div>'; } } return `<section class="eh-preview-card eh-pad-mobile" data-id="${s.id}" style="${wrapStyles.join('')}"><div class="eh-preview-heading" data-id="${s.id}">${heading}</div><div class="eh-preview-body">${body}</div></section>`; }).join(""); previewBody.innerHTML = html || "<div style='padding:12px;font-size:13px;opacity:.7;'>Geen secties</div>"; attachHeadingEditors(); attachCtaEditors(); attachBodyEditors(); }';
        echo 'function attachHeadingEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-heading").forEach(function(h){ h.setAttribute("contenteditable","true"); h.addEventListener("input", function(){ const id=h.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.title = h.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'function attachCtaEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-cta").forEach(function(c){ c.setAttribute("contenteditable","true"); c.addEventListener("input", function(){ const id=c.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.cta = c.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); previewBody.querySelectorAll(".eh-preview-body [data-field=\\"cta\\"]").forEach(function(c){ c.setAttribute("contenteditable","true"); c.addEventListener("input", function(){ const id=c.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.cta = c.textContent.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'function attachBodyEditors(){ if(!previewBody) return; previewBody.querySelectorAll(".eh-preview-body [data-field=\\"body\\"]").forEach(function(el){ el.setAttribute("contenteditable","true"); el.addEventListener("input", function(){ const id=el.dataset.id; const sections=readSections(); sections.forEach(function(s){ if(s.id===id){ s.body = el.innerHTML.trim(); } }); builderInput.value = JSON.stringify(sections); if(window.EHBuilderSync){ window.EHBuilderSync(); } }); }); }';
        echo 'window.EHPreview = { updateSections: function(payload){ renderSections(payload); } };';
        echo 'renderSections(); renderAssets();';
        echo '})();';
        echo '</script>';
        echo '</div>'; // preview

        echo '</div>'; // grid
        echo '</div>'; // wrap
    }

    public function sanitize_settings($input): array
    {
        $out = [];
        $out['from_name'] = isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : ';
        $out['from_email'] = isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : ';
        $transport = $input['mail_transport'] ?? 'php';
        $out['mail_transport'] = in_array($transport, ['php', 'smtp_plugin'], true) ? $transport : 'php';
        $out['reminder_offset_days'] = isset($input['reminder_offset_days']) ? max(0, (int) $input['reminder_offset_days']) : 3;
        $out['followup_offset_hours'] = isset($input['followup_offset_hours']) ? max(0, (int) $input['followup_offset_hours']) : 24;
        if (isset($input['custom_placeholders_raw'])) {
            $raw = (string) $input['custom_placeholders_raw'];
            $out['custom_placeholders_raw'] = wp_kses_post($raw);
            $out['custom_placeholders'] = $this->parse_custom_placeholders($raw);
        }
        return $out;
    }

    public function sanitize_general_settings($input): array
    {
        $out = [];
        $use_external = !empty($input['use_external_cpt']);
        $out['use_external_cpt'] = $use_external ? 1 : 0;
        $raw_cpt = isset($input['cpt_slug']) ? sanitize_key((string) $input['cpt_slug']) : 'eh_session';
        $cpt_slug = $raw_cpt !== '' ? $raw_cpt : 'eh_session';
        $raw_tax = isset($input['tax_slug']) ? sanitize_key((string) $input['tax_slug']) : 'eh_session_type';

        if ($use_external) {
            if (!post_type_exists($cpt_slug)) {
                // Invalid external CPT chosen; fall back and warn.
                set_transient('event_hub_cpt_missing', $cpt_slug, HOUR_IN_SECONDS);
                $out['use_external_cpt'] = 0;
                $out['cpt_slug'] = 'eh_session';
            } else {
                $out['cpt_slug'] = $cpt_slug;
            }
        } else {
            $out['cpt_slug'] = 'eh_session';
        }

        if ($use_external && $raw_tax && !taxonomy_exists($raw_tax)) {
            set_transient('event_hub_tax_missing', $raw_tax, HOUR_IN_SECONDS);
        }
        $out['tax_slug'] = $raw_tax ?: 'eh_session_type';
        $layout = $input['single_layout'] ?? 'modern';
        $out['single_layout'] = in_array($layout, ['modern', 'compact'], true) ? $layout : 'modern';
        $out['single_custom_enabled'] = !empty($input['single_custom_enabled']) ? 1 : 0;
        $out['single_custom_css'] = isset($input['single_custom_css']) ? wp_kses_post($input['single_custom_css']) : ';
        $out['single_custom_js'] = isset($input['single_custom_js']) ? wp_kses_post($input['single_custom_js']) : ';
        $html_keys = [
            'single_custom_html_before_hero',
            'single_custom_html_after_hero',
            'single_custom_html_before_form',
            'single_custom_html_after_form',
            'single_custom_html_after_details',
        ];
        foreach ($html_keys as $key) {
            $out[$key] = isset($input[$key]) ? wp_kses_post($input[$key]) : ';
        }
        // Builder
        $allowed_sections = ['status','hero','info','content','form','custom1','custom2','quote','faq','agenda','buttons','gallery','card','textblock'];
        $builder_raw = isset($input['single_builder_sections']) ? (string) $input['single_builder_sections'] : '[]';
        $decoded = json_decode($builder_raw, true);
        $clean = [];
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $id = isset($row['id']) ? sanitize_key((string) $row['id']) : ';
                if ($id === '' || !in_array($id, $allowed_sections, true)) {
                    continue;
                }
                $clean[] = [
                    'id' => $id,
                    'title' => isset($row['title']) ? sanitize_text_field((string) $row['title']) : $id,
                    'accent' => isset($row['accent']) ? sanitize_text_field((string) $row['accent']) : '',
                    'bg' => isset($row['bg']) ? sanitize_text_field((string) $row['bg']) : '',
                    'gradient' => isset($row['gradient']) ? sanitize_text_field((string) $row['gradient']) : '',
                    'bgImage' => isset($row['bgImage']) ? esc_url_raw((string) $row['bgImage']) : '',
                    'heading' => isset($row['heading']) ? sanitize_text_field((string) $row['heading']) : '',
                    'cta' => isset($row['cta']) ? sanitize_text_field((string) $row['cta']) : '',
                    'fontSize' => isset($row['fontSize']) ? (int) $row['fontSize'] : 16,
                    'padding' => isset($row['padding']) ? (int) $row['padding'] : 16,
                    'paddingMobile' => isset($row['paddingMobile']) ? (int) $row['paddingMobile'] : (isset($row['padding']) ? (int) $row['padding'] : 14),
                    'variant' => isset($row['variant']) ? sanitize_text_field((string) $row['variant']) : 'default',
                    'body' => isset($row['body']) ? wp_kses_post((string) $row['body']) : '',
                ];
            }
        }
        if (!$clean) {
            $clean = [
                ['id' => 'status', 'title' => __('Status', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#f8fafc', 'heading' => '', 'fontSize' => 16, 'padding' => 16],
                ['id' => 'hero', 'title' => __('Hero', 'event-hub'), 'accent' => '#1d4ed8', 'bg' => '#0f172a', 'heading' => '', 'fontSize' => 18, 'padding' => 18],
                ['id' => 'info', 'title' => __('Praktische info', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#ffffff', 'heading' => '', 'fontSize' => 16, 'padding' => 16],
                ['id' => 'content', 'title' => __('Content', 'event-hub'), 'accent' => '#0f172a', 'bg' => '#ffffff', 'heading' => '', 'fontSize' => 16, 'padding' => 16],
                ['id' => 'form', 'title' => __('Formulier', 'event-hub'), 'accent' => '#10b981', 'bg' => '#ffffff', 'heading' => '', 'fontSize' => 16, 'padding' => 18],
            ];
        }
        $out['single_builder_sections'] = wp_json_encode($clean);
        $out['enable_recaptcha'] = !empty($input['enable_recaptcha']) ? 1 : 0;
        $allowed = ['google_recaptcha_v3','hcaptcha'];
        $out['recaptcha_provider'] = in_array($input['recaptcha_provider'] ?? '', $allowed, true) ? $input['recaptcha_provider'] : 'google_recaptcha_v3';
        $out['recaptcha_site_key'] = isset($input['recaptcha_site_key']) ? sanitize_text_field((string) $input['recaptcha_site_key']) : ';
        $out['recaptcha_secret_key'] = isset($input['recaptcha_secret_key']) ? sanitize_text_field((string) $input['recaptcha_secret_key']) : ';
        $score = isset($input['recaptcha_score']) ? (float) $input['recaptcha_score'] : 0.5;
        $out['recaptcha_score'] = ($score >= 0 && $score <= 1) ? $score : 0.5;
        $out['colleagues'] = [];
        if (isset($input['colleagues']) && is_array($input['colleagues'])) {
            foreach ($input['colleagues'] as $row) {
                $first = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : ';
                $last  = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : ';
                $role  = isset($row['role']) ? sanitize_text_field((string) $row['role']) : ';
                $photo = isset($row['photo_id']) ? (int) $row['photo_id'] : 0;
                if ($first || $last || $role || $photo) {
                    $out['colleagues'][] = [
                        'first_name' => $first,
                        'last_name'  => $last,
                        'role'       => $role,
                        'photo_id'   => $photo,
                    ];
                }
            }
        }
        return $out;
    }

    public static function get_general(): array
    {
        $defaults = [
            'use_external_cpt' => 0,
            'cpt_slug' => 'eh_session',
            'tax_slug' => 'eh_session_type',
            'single_layout' => 'modern',
            'enable_recaptcha' => 0,
            'recaptcha_provider' => 'google_recaptcha_v3',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'recaptcha_score' => 0.5,
        ];
        $opts = get_option(self::OPTION_GENERAL, []);
        return wp_parse_args($opts, $defaults);
    }

    public static function get_cpt_slug(): string
    {
        $g = self::get_general();
        return sanitize_key((string) ($g['cpt_slug'] ?? 'eh_session'));
    }

    public static function get_tax_slug(): string
    {
        $g = self::get_general();
        return sanitize_key((string) ($g['tax_slug'] ?? 'eh_session_type'));
    }

    public static function use_external_cpt(): bool
    {
        $g = self::get_general();
        return !empty($g['use_external_cpt']);
    }

    public static function get_email_settings(): array
    {
        return get_option(self::OPTION, []);
    }

    public static function get_custom_placeholders(): array
    {
        $opts = self::get_email_settings();
        return isset($opts['custom_placeholders']) && is_array($opts['custom_placeholders'])
            ? $opts['custom_placeholders']
            : [];
    }

    public static function fallback_to_builtin_cpt(): void
    {
        $opts = self::get_general();
        $opts['use_external_cpt'] = 0;
        $opts['cpt_slug'] = 'eh_session';
        update_option(self::OPTION_GENERAL, $opts);
        set_transient('event_hub_cpt_fallback', 1, HOUR_IN_SECONDS);
    }

    public function maybe_notice_cpt_tax_issues(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $missing_cpt = get_transient('event_hub_cpt_missing');
        if ($missing_cpt) {
            delete_transient('event_hub_cpt_missing');
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php echo esc_html(sprintf(__('Het gekozen CPT "%s" bestaat niet. We zijn teruggevallen op de standaard Event Hub CPT (eh_session). Pas dit aan in de algemene instellingen.', 'event-hub'), $missing_cpt)); ?></p>
            </div>
            <?php
        }

        $missing_tax = get_transient('event_hub_tax_missing');
        if ($missing_tax) {
            delete_transient('event_hub_tax_missing');
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php echo esc_html(sprintf(__('De gekozen taxonomie "%s" werd niet gevonden. Controleer of ze bestaat en gekoppeld is aan je evenementen-CPT.', 'event-hub'), $missing_tax)); ?></p>
            </div>
            <?php
        }
    }

    private function parse_custom_placeholders(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $map = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$token, $value] = array_map('trim', explode('=', $line, 2));
            if ($token === '') {
                continue;
            }
            $token = trim($token);
            $token = trim($token, '{}');
            if ($token === '') {
                continue;
            }
            $token = '{' . $token . '}';
            $map[$token] = wp_kses_post($value);
        }
        return $map;
    }
}






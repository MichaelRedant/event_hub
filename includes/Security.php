<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Security
{
    public static function captcha_enabled(): bool
    {
        $g = Settings::get_general();
        return !empty($g['enable_recaptcha']) && !empty($g['recaptcha_site_key']) && !empty($g['recaptcha_secret_key']);
    }

    public static function provider(): string
    {
        $g = Settings::get_general();
        return $g['recaptcha_provider'] ?? 'google_recaptcha_v3';
    }

    public static function site_key(): string
    {
        $g = Settings::get_general();
        return (string) ($g['recaptcha_site_key'] ?? '');
    }

    public static function secret_key(): string
    {
        $g = Settings::get_general();
        return (string) ($g['recaptcha_secret_key'] ?? '');
    }

    public static function score_threshold(): float
    {
        $g = Settings::get_general();
        $score = isset($g['recaptcha_score']) ? (float) $g['recaptcha_score'] : 0.5;
        return ($score >= 0 && $score <= 1) ? $score : 0.5;
    }

    public static function verify_token(?string $token, string $action = 'event_hub_register'): bool
    {
        if (!self::captcha_enabled()) {
            return true;
        }
        $token = trim((string) ($token ?? ''));
        if ($token === '') {
            return false;
        }
        $provider = self::provider();
        if ($provider === 'hcaptcha') {
            $endpoint = 'https://hcaptcha.com/siteverify';
            $resp = wp_remote_post($endpoint, [
                'timeout' => 10,
                'body' => [
                    'secret' => self::secret_key(),
                    'response' => $token,
                ],
            ]);
            if (is_wp_error($resp)) { return false; }
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            return !empty($data['success']);
        }

        // Google reCAPTCHA v3
        $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
        $resp = wp_remote_post($endpoint, [
            'timeout' => 10,
            'body' => [
                'secret' => self::secret_key(),
                'response' => $token,
            ],
        ]);
        if (is_wp_error($resp)) { return false; }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['success'])) { return false; }
        if (isset($data['action']) && $data['action'] !== $action) { return false; }
        $score = isset($data['score']) ? (float) $data['score'] : 0;
        return $score >= self::score_threshold();
    }
}


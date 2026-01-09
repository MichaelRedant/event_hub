<?php
namespace EventHub;

defined('ABSPATH') || exit;

/**
 * Lightweight in-option log with ring buffer.
 */
class Logger
{
    private const OPTION = 'event_hub_logs';
    private const LIMIT = 200;

    public function log(string $type, string $message, array $context = []): void
    {
        $entry = [
            'ts' => time(),
            'type' => sanitize_key($type),
            'message' => wp_strip_all_tags($message),
            'context' => $this->sanitize_context($context),
        ];
        $logs = get_option(self::OPTION, []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs[] = $entry;
        if (count($logs) > self::LIMIT) {
            $logs = array_slice($logs, -self::LIMIT);
        }
        update_option(self::OPTION, $logs, false);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $logs = get_option(self::OPTION, []);
        return is_array($logs) ? array_reverse($logs, true) : [];
    }

    /**
     * Delete specific log entries by key (keys from all()).
     *
     * @param array<int|string> $keys
     */
    public function delete(array $keys): void
    {
        $logs = get_option(self::OPTION, []);
        if (!is_array($logs) || !$keys) {
            return;
        }
        foreach ($keys as $key) {
            if (isset($logs[$key])) {
                unset($logs[$key]);
            }
        }
        update_option(self::OPTION, $logs, false);
    }

    /**
     * Clear all logs.
     */
    public function clear(): void
    {
        delete_option(self::OPTION);
    }

    private function sanitize_context(array $context): array
    {
        $out = [];
        foreach ($context as $key => $val) {
            $k = sanitize_key((string) $key);
            if (is_scalar($val) || $val === null) {
                $out[$k] = is_string($val) ? wp_strip_all_tags($val) : $val;
            } else {
                $out[$k] = wp_json_encode($val);
            }
        }
        return $out;
    }
}

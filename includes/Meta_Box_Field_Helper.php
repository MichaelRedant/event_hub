<?php
namespace EventHub;

defined('ABSPATH') || exit;

class Meta_Box_Field_Helper
{
    public static function checkbox(string $name, string $label, bool $checked): string
    {
        return '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . ' /> ' . esc_html($label) . '</label>';
    }
}

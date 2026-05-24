<?php
/**
 * Email-safe font presets for the Email Design typography settings.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Curated web-safe font stacks for HTML email.
 */
class SubscriberNotifications_Email_Font_Presets {

    const CHOICE_CUSTOM       = 'custom';
    const CHOICE_SAME_AS_BODY = 'same_as_body';

    /**
     * @return array<string, array{label: string, stack: string}>
     */
    public static function get_presets() {
        return array(
            'arial'           => array(
                'label' => __('Arial — clean sans-serif', 'subscriber-notifications'),
                'stack' => 'Arial, Helvetica, sans-serif',
            ),
            'helvetica'         => array(
                'label' => __('Helvetica — modern sans-serif', 'subscriber-notifications'),
                'stack' => 'Helvetica, Arial, sans-serif',
            ),
            'verdana'           => array(
                'label' => __('Verdana — easy-to-read sans-serif', 'subscriber-notifications'),
                'stack' => 'Verdana, Geneva, sans-serif',
            ),
            'tahoma'            => array(
                'label' => __('Tahoma — compact sans-serif', 'subscriber-notifications'),
                'stack' => 'Tahoma, Geneva, sans-serif',
            ),
            'trebuchet'         => array(
                'label' => __('Trebuchet MS — friendly sans-serif', 'subscriber-notifications'),
                'stack' => 'Trebuchet MS, Helvetica, sans-serif',
            ),
            'georgia'           => array(
                'label' => __('Georgia — classic serif', 'subscriber-notifications'),
                'stack' => 'Georgia, Times New Roman, serif',
            ),
            'times_new_roman'   => array(
                'label' => __('Times New Roman — traditional serif', 'subscriber-notifications'),
                'stack' => 'Times New Roman, Times, serif',
            ),
            'courier_new'       => array(
                'label' => __('Courier New — monospace', 'subscriber-notifications'),
                'stack' => 'Courier New, Courier, monospace',
            ),
        );
    }

    /**
     * @return string
     */
    public static function get_default_body_stack() {
        return 'Arial, Helvetica, sans-serif';
    }

    /**
     * @return string[]
     */
    public static function get_all_stacks() {
        $stacks = array();
        foreach (self::get_presets() as $preset) {
            $stacks[] = $preset['stack'];
        }
        return $stacks;
    }

    /**
     * @param string $stack Stored font stack.
     * @return string|null Preset slug or null.
     */
    public static function get_slug_for_stack($stack) {
        foreach (self::get_presets() as $slug => $preset) {
            if ($preset['stack'] === $stack) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * @param string $stack       Stored font stack.
     * @param bool   $allow_empty Whether empty means "same as body".
     * @return string Preset slug, CHOICE_CUSTOM, or CHOICE_SAME_AS_BODY.
     */
    public static function get_choice_for_stack($stack, $allow_empty = false) {
        if ($allow_empty && $stack === '') {
            return self::CHOICE_SAME_AS_BODY;
        }

        $slug = self::get_slug_for_stack($stack);
        return $slug ? $slug : self::CHOICE_CUSTOM;
    }

    /**
     * @param string $value   Raw submitted stack.
     * @param string $default Fallback when invalid.
     * @param bool   $allow_empty Allow empty string (heading → same as body).
     * @return string
     */
    public static function sanitize_stack($value, $default, $allow_empty = false) {
        $value = is_string($value) ? trim(wp_strip_all_tags($value)) : '';

        if ($value === '') {
            return $allow_empty ? '' : $default;
        }

        if (in_array($value, self::get_all_stacks(), true)) {
            return $value;
        }

        if (!preg_match('/^[A-Za-z0-9 ,\'"\-]+$/', $value)) {
            return $allow_empty && $value === '' ? '' : $default;
        }

        return $value;
    }

    /**
     * Preset data for admin.js (slug => stack).
     *
     * @return array<string, string>
     */
    public static function get_preset_stacks_for_js() {
        $out = array();
        foreach (self::get_presets() as $slug => $preset) {
            $out[ $slug ] = $preset['stack'];
        }
        return $out;
    }
}

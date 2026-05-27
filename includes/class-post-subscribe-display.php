<?php
/**
 * Copy overrides for [subscriber_notifications_post_subscribe].
 *
 * Visibility is controlled in Content Types (see Content_Config::is_post_eligible_for_single_item).
 *
 * @package SubscriberNotifications
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post subscribe shortcode copy overrides.
 */
class SubscriberNotifications_Post_Subscribe_Display {

    const COPY_ATTRS = array(
        'heading',
        'description',
        'button',
        'heading_subscribed',
        'description_subscribed',
        'button_manage',
    );

    /**
     * Default shortcode attributes.
     *
     * @return array<string, string>
     */
    public static function default_atts(): array {
        $defaults = array();
        foreach (self::COPY_ATTRS as $key) {
            $defaults[ $key ] = '';
        }
        return $defaults;
    }

    /**
     * Normalize shortcode attributes from shortcode_atts().
     *
     * @param array $atts Raw attributes.
     * @return array<string, string> Copy overrides (non-empty strings only).
     */
    public static function parse_atts(array $atts): array {
        $defaults = self::default_atts();
        $merged   = array_merge($defaults, $atts);

        $copy = array();
        foreach (self::COPY_ATTRS as $key) {
            $value = isset($merged[ $key ]) ? sanitize_text_field((string) $merged[ $key ]) : '';
            if ($value !== '') {
                $copy[ $key ] = $value;
            }
        }

        return $copy;
    }

    /**
     * Sanitize copy overrides submitted with the subscribe form.
     *
     * @param mixed $raw Decoded JSON from POST.
     * @return array<string, string>
     */
    public static function sanitize_copy_from_request($raw): array {
        if (!is_array($raw)) {
            return array();
        }

        $copy = array();
        foreach (self::COPY_ATTRS as $key) {
            if (empty($raw[ $key ])) {
                continue;
            }
            $copy[ $key ] = sanitize_text_field((string) $raw[ $key ]);
        }

        return $copy;
    }

    /**
     * Apply plain-text copy overrides to default widget strings.
     *
     * @param array<string, string> $strings Default strings (button_subscribe key).
     * @param array<string, string> $copy    Overrides from parse_atts().
     * @return array<string, string>
     */
    public static function apply_copy_overrides(array $strings, array $copy): array {
        $map = array(
            'heading'                => 'heading',
            'description'            => 'description',
            'button'                 => 'button_subscribe',
            'heading_subscribed'     => 'heading_subscribed',
            'description_subscribed' => 'description_subscribed',
            'button_manage'          => 'button_manage',
        );

        foreach ($map as $attr => $string_key) {
            if (!empty($copy[ $attr ])) {
                $strings[ $string_key ] = $copy[ $attr ];
            }
        }

        return $strings;
    }
}

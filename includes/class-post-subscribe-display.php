<?php
/**
 * Visibility rules and copy overrides for [subscriber_notifications_post_subscribe].
 *
 * @package SubscriberNotifications
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post subscribe shortcode display rules.
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
        return array(
            'include'                => '',
            'exclude'                => '',
            'include_terms'          => '',
            'exclude_terms'          => '',
            'heading'                => '',
            'description'            => '',
            'button'                 => '',
            'heading_subscribed'     => '',
            'description_subscribed' => '',
            'button_manage'          => '',
        );
    }

    /**
     * Normalize shortcode attributes from shortcode_atts().
     *
     * @param array $atts Raw attributes.
     * @return array{include: string[], exclude: string[], include_terms: array<int, array{taxonomy: string, term: string}>, exclude_terms: array<int, array{taxonomy: string, term: string}>, copy: array<string, string>}
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

        return array(
            'include'       => self::parse_slug_list((string) $merged['include']),
            'exclude'       => self::parse_slug_list((string) $merged['exclude']),
            'include_terms' => self::parse_term_rules((string) $merged['include_terms']),
            'exclude_terms' => self::parse_term_rules((string) $merged['exclude_terms']),
            'copy'          => $copy,
        );
    }

    /**
     * Whether the widget should render for the current post.
     *
     * @param WP_Post $post  Current post.
     * @param array   $rules Parsed rules from parse_atts().
     * @return bool
     */
    public static function is_visible(WP_Post $post, array $rules): bool {
        $slug = (string) $post->post_name;

        if (!empty($rules['include']) && !in_array($slug, $rules['include'], true)) {
            return false;
        }

        if (!empty($rules['include_terms']) && !self::post_matches_any_term($post, $rules['include_terms'])) {
            return false;
        }

        if (!empty($rules['exclude']) && in_array($slug, $rules['exclude'], true)) {
            return false;
        }

        if (!empty($rules['exclude_terms']) && self::post_matches_any_term($post, $rules['exclude_terms'])) {
            return false;
        }

        return true;
    }

    /**
     * Copy overrides safe to round-trip through AJAX (non-empty strings only).
     *
     * @param array $rules Parsed rules from parse_atts().
     * @return array<string, string>
     */
    public static function copy_for_client(array $rules): array {
        return isset($rules['copy']) && is_array($rules['copy']) ? $rules['copy'] : array();
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
     * @param array<string, string> $strings  Default strings (button_subscribe key).
     * @param array<string, string> $copy     Overrides from parse_atts copy array.
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

    /**
     * Parse a comma- or space-separated slug list.
     *
     * @param string $raw Raw attribute value.
     * @return string[]
     */
    private static function parse_slug_list(string $raw): array {
        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[\s,]+/', $raw);
        if (!is_array($parts)) {
            return array();
        }

        $out = array();
        foreach ($parts as $slug) {
            $slug = sanitize_title((string) $slug);
            if ($slug !== '') {
                $out[ $slug ] = $slug;
            }
        }

        return array_values($out);
    }

    /**
     * Parse taxonomy:term-slug pairs.
     *
     * @param string $raw Raw attribute value.
     * @return array<int, array{taxonomy: string, term: string}>
     */
    private static function parse_term_rules(string $raw): array {
        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[\s,]+/', $raw);
        if (!is_array($parts)) {
            return array();
        }

        $out = array();
        foreach ($parts as $pair) {
            $pair = trim((string) $pair);
            if ($pair === '' || strpos($pair, ':') === false) {
                continue;
            }

            list($taxonomy, $term) = explode(':', $pair, 2);
            $taxonomy = sanitize_key($taxonomy);
            $term     = sanitize_title($term);
            if ($taxonomy === '' || $term === '') {
                continue;
            }

            $out[] = array(
                'taxonomy' => $taxonomy,
                'term'     => $term,
            );
        }

        return $out;
    }

    /**
     * Whether the post has any of the configured term rules (OR).
     *
     * @param WP_Post $post  Post object.
     * @param array   $rules Term rules from parse_term_rules().
     * @return bool
     */
    private static function post_matches_any_term(WP_Post $post, array $rules): bool {
        foreach ($rules as $rule) {
            $taxonomy = $rule['taxonomy'] ?? '';
            $term     = $rule['term'] ?? '';
            if ($taxonomy === '' || $term === '') {
                continue;
            }
            if (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy($post->post_type, $taxonomy)) {
                continue;
            }

            $term_object = get_term_by('slug', $term, $taxonomy);
            if (!$term_object || is_wp_error($term_object)) {
                continue;
            }

            if (has_term((int) $term_object->term_id, $taxonomy, $post)) {
                return true;
            }
        }

        return false;
    }
}

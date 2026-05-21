<?php
/**
 * Term Resolver.
 *
 * Returns the set of terms that should appear on the subscription form for a
 * given post type / taxonomy, applying the configured term_display rule
 * (all / children_of / include / exclude). Also exposes the same "allowed
 * term IDs" set as a flat array for orphan pruning and validation.
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Term Resolver helper.
 *
 * Two display contexts are supported:
 *
 * - `admin`  (default): every configured term is returned, regardless of how
 *   many posts use it. Used by Content Types admin, notification target picker
 *   and CSV reference lists so editors can target empty terms intentionally.
 * - `public`: when the global option `hide_terms_without_published_content` is
 *   enabled, terms with zero published posts for the configured post type are
 *   filtered out before rendering on the public subscribe/preferences form.
 */
class SubscriberNotifications_Term_Resolver {

    /**
     * Get the WP_Term[] that should be shown on the form for a configured taxonomy.
     *
     * Returns the full configured set with `hide_empty => false`. This is the
     * "admin" view of the term list. Public callers should use
     * {@see self::get_terms_for_public_form()} so the global "hide empty"
     * setting is honored.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @return WP_Term[]
     */
    public static function get_terms_for_form($post_type, $taxonomy) {
        $tax_config = SubscriberNotifications_Content_Config::get_taxonomy_config($post_type, $taxonomy);
        if (empty($tax_config) || empty($tax_config['enabled_on_form'])) {
            return array();
        }

        $tax_object = get_taxonomy($taxonomy);
        if (!$tax_object) {
            return array();
        }

        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $mode = isset($tax_config['term_display']) ? $tax_config['term_display'] : 'all';

        switch ($mode) {
            case 'children_of':
                if (empty($tax_object->hierarchical)) {
                    break;
                }
                $parent_id = isset($tax_config['parent_term_id']) ? (int) $tax_config['parent_term_id'] : 0;
                if ($parent_id <= 0) {
                    return array();
                }
                $args['parent'] = $parent_id;
                break;

            case 'include':
                $include = isset($tax_config['include_term_ids']) ? (array) $tax_config['include_term_ids'] : array();
                $include = array_values(array_filter(array_map('intval', $include), function ($id) { return $id > 0; }));
                if (empty($include)) {
                    return array();
                }
                $args['include'] = $include;
                break;

            case 'exclude':
                $exclude = isset($tax_config['exclude_term_ids']) ? (array) $tax_config['exclude_term_ids'] : array();
                $exclude = array_values(array_filter(array_map('intval', $exclude), function ($id) { return $id > 0; }));
                if (!empty($exclude)) {
                    $args['exclude'] = $exclude;
                }
                break;

            case 'all':
            default:
                break;
        }

        $terms = get_terms($args);
        if (is_wp_error($terms) || !is_array($terms)) {
            return array();
        }
        return $terms;
    }

    /**
     * Get the WP_Term[] that should be shown on the public subscription form.
     *
     * Starts from {@see self::get_terms_for_form()} and, when the global option
     * `hide_terms_without_published_content` is enabled (default), removes
     * terms that have zero published posts for the given post type.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @return WP_Term[]
     */
    public static function get_terms_for_public_form($post_type, $taxonomy) {
        $terms = self::get_terms_for_form($post_type, $taxonomy);
        if (empty($terms) || !self::should_hide_empty_terms_for_public()) {
            return $terms;
        }

        $term_ids_with_posts = self::term_ids_with_published_posts($post_type, $taxonomy);
        if (empty($term_ids_with_posts)) {
            return array();
        }

        $filtered = array();
        foreach ($terms as $term) {
            if (isset($term->term_id) && in_array((int) $term->term_id, $term_ids_with_posts, true)) {
                $filtered[] = $term;
            }
        }
        return $filtered;
    }

    /**
     * Return the flat list of allowed term IDs for a configured taxonomy.
     *
     * Used for orphan pruning and admin validation. Returns an empty array if the
     * taxonomy is not enabled_on_form for that post type.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @param string $context   Either `admin` (default, full configured set) or
     *                          `public` (additionally honors the global "hide
     *                          empty" setting).
     * @return int[]
     */
    public static function get_allowed_term_ids($post_type, $taxonomy, $context = 'admin') {
        $terms = ($context === 'public')
            ? self::get_terms_for_public_form($post_type, $taxonomy)
            : self::get_terms_for_form($post_type, $taxonomy);
        $ids = array();
        foreach ($terms as $term) {
            if (isset($term->term_id)) {
                $ids[] = (int) $term->term_id;
            }
        }
        return $ids;
    }

    /**
     * Whether the global "hide terms without published content" option is on.
     *
     * Defaults to enabled. Only consulted by public-context helpers; admin code
     * paths are unaffected.
     *
     * @return bool
     */
    public static function should_hide_empty_terms_for_public() {
        $value = subscriber_notifications_get_option('hide_terms_without_published_content', 1);
        return (int) $value === 1;
    }

    /**
     * Whether a configured term is omitted from the public subscribe/preferences form.
     *
     * True when the global hide-empty option is on and the term has no published
     * posts for the configured post type.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @param int    $term_id   Term ID.
     * @return bool
     */
    public static function is_term_hidden_from_public_form($post_type, $taxonomy, $term_id) {
        if (!self::should_hide_empty_terms_for_public()) {
            return false;
        }

        $term_id = (int) $term_id;
        if ($term_id <= 0) {
            return false;
        }

        $term_ids_with_posts = self::term_ids_with_published_posts($post_type, $taxonomy);
        return !in_array($term_id, $term_ids_with_posts, true);
    }

    /**
     * Return distinct term IDs in $taxonomy that are attached to at least one
     * **published** post of $post_type. Cached per request to avoid repeating
     * the same query for every form render.
     *
     * Note: this is post-type-scoped, unlike core's `hide_empty` argument which
     * counts objects across every post type that uses the taxonomy.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @return int[]
     */
    public static function term_ids_with_published_posts($post_type, $taxonomy) {
        static $cache = array();
        $cache_key = $post_type . '|' . $taxonomy;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        global $wpdb;

        $post_type = (string) $post_type;
        $taxonomy  = (string) $taxonomy;

        if ($post_type === '' || $taxonomy === '' || !taxonomy_exists($taxonomy) || !post_type_exists($post_type)) {
            $cache[$cache_key] = array();
            return $cache[$cache_key];
        }

        $sql = $wpdb->prepare(
            "SELECT DISTINCT tt.term_id
             FROM {$wpdb->term_taxonomy} tt
             INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE tt.taxonomy = %s
               AND p.post_type = %s
               AND p.post_status = %s",
            $taxonomy,
            $post_type,
            'publish'
        );

        $results = $wpdb->get_col($sql);
        if (!is_array($results)) {
            $cache[$cache_key] = array();
            return $cache[$cache_key];
        }

        $ids = array_values(array_unique(array_map('intval', $results)));
        $cache[$cache_key] = $ids;
        return $ids;
    }

    /**
     * Return allowed term IDs scoped by post type, suitable for matching against subscriber preferences.
     *
     * Shape: `[ post_type => [ taxonomy => [ id, ... ] ] ]`.
     *
     * @return array
     */
    public static function get_allowed_map() {
        $map = array();
        foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
            foreach (SubscriberNotifications_Content_Config::get_form_taxonomies($post_type) as $taxonomy) {
                $map[$post_type][$taxonomy] = self::get_allowed_term_ids($post_type, $taxonomy);
            }
        }
        return $map;
    }

    /**
     * Build a human-readable summary of selected terms grouped by post type and taxonomy.
     *
     * @param array $prefs Preferences-shaped array.
     * @return string Comma + pipe separated summary (e.g. "News: A, B | Events: C").
     */
    public static function describe_selection(array $prefs) {
        $sections = array();
        foreach (self::get_selection_sections($prefs) as $section) {
            $lines = array();
            foreach ($section['taxonomies'] as $tax_row) {
                $lines[] = $tax_row['label'] . ': ' . implode(', ', $tax_row['term_names']);
            }
            if (!empty($lines)) {
                $sections[] = $section['post_type_label'] . "\n" . implode("\n", $lines);
            }
        }
        return implode("\n\n", $sections);
    }

    /**
     * HTML summary of preferences for email bodies (post type heading, taxonomy lines).
     *
     * @param array $prefs Preferences-shaped array.
     * @return string Safe HTML fragment (no wrapper required).
     */
    public static function describe_selection_html(array $prefs) {
        $html = '';
        foreach (self::get_selection_sections($prefs) as $section) {
            if (empty($section['taxonomies'])) {
                continue;
            }
            $html .= '<p><strong>' . esc_html($section['post_type_label']) . '</strong></p><p>';
            $lines = array();
            foreach ($section['taxonomies'] as $tax_row) {
                $lines[] = '<strong>' . esc_html($tax_row['label']) . ':</strong> '
                    . esc_html(implode(', ', $tax_row['term_names']));
            }
            $html .= implode('<br />', $lines) . '</p>';
        }
        return $html;
    }

    /**
     * Build ordered post type / taxonomy / term name rows from preferences.
     *
     * @param array $prefs Preferences-shaped array.
     * @return array<int, array{post_type_label: string, taxonomies: array<int, array{label: string, term_names: string[]}>}>
     */
    private static function get_selection_sections(array $prefs) {
        $sections = array();
        $post_types = class_exists('SubscriberNotifications_Content_Config')
            ? SubscriberNotifications_Content_Config::get_enabled_post_types()
            : array_keys($prefs);

        foreach ($post_types as $post_type) {
            if (empty($prefs[$post_type]) || !is_array($prefs[$post_type])) {
                continue;
            }

            $taxonomies = class_exists('SubscriberNotifications_Content_Config')
                ? SubscriberNotifications_Content_Config::get_form_taxonomies($post_type)
                : array_keys($prefs[$post_type]);

            $tax_rows = array();
            foreach ($taxonomies as $taxonomy) {
                if (empty($prefs[$post_type][$taxonomy]) || !is_array($prefs[$post_type][$taxonomy])) {
                    continue;
                }
                $term_names = array();
                foreach ($prefs[$post_type][$taxonomy] as $id) {
                    $term = get_term((int) $id, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $term_names[] = $term->name;
                    }
                }
                if (empty($term_names)) {
                    continue;
                }
                sort($term_names, SORT_NATURAL | SORT_FLAG_CASE);
                $tax_rows[] = array(
                    'label'      => SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy),
                    'term_names' => $term_names,
                );
            }

            if (!empty($tax_rows)) {
                $sections[] = array(
                    'post_type_label' => SubscriberNotifications_Content_Config::get_post_type_label($post_type),
                    'taxonomies'      => $tax_rows,
                );
            }
        }

        return $sections;
    }
}

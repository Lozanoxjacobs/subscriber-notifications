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
    /**
     * Whether a post passes a configured taxonomy display rule for single-item eligibility.
     *
     * Call only for restrictive modes (not `all`).
     *
     * @param WP_Post $post      Post object.
     * @param string  $post_type Post type slug.
     * @param string  $taxonomy  Taxonomy slug.
     * @return bool
     */
    public static function post_passes_taxonomy_display_rule(WP_Post $post, $post_type, $taxonomy) {
        $tax_config = SubscriberNotifications_Content_Config::get_taxonomy_config($post_type, $taxonomy);
        if (empty($tax_config)) {
            return false;
        }

        $mode = isset($tax_config['term_display']) ? (string) $tax_config['term_display'] : 'all';
        if ($mode === 'all') {
            return false;
        }

        if (!is_object_in_taxonomy($post->post_type, $taxonomy)) {
            return false;
        }

        $post_term_ids = self::get_post_term_ids($post, $taxonomy);

        switch ($mode) {
            case 'include':
                $allowed = array_map('intval', (array) ($tax_config['include_term_ids'] ?? array()));
                $allowed = array_values(array_filter($allowed, function ($id) { return $id > 0; }));
                if (empty($allowed)) {
                    return false;
                }
                return !empty(array_intersect($post_term_ids, $allowed));

            case 'exclude':
                $denied = array_map('intval', (array) ($tax_config['exclude_term_ids'] ?? array()));
                $denied = array_values(array_filter($denied, function ($id) { return $id > 0; }));
                if (empty($denied)) {
                    return true;
                }
                return empty(array_intersect($post_term_ids, $denied));

            case 'children_of':
                $parent_id = isset($tax_config['parent_term_id']) ? (int) $tax_config['parent_term_id'] : 0;
                if ($parent_id <= 0) {
                    return false;
                }
                foreach ($post_term_ids as $term_id) {
                    if (self::term_is_under_parent((int) $term_id, $parent_id, $taxonomy)) {
                        return true;
                    }
                }
                return false;
        }

        return false;
    }

    /**
     * Term IDs assigned to a post for a taxonomy.
     *
     * @param WP_Post $post     Post object.
     * @param string  $taxonomy Taxonomy slug.
     * @return int[]
     */
    private static function get_post_term_ids(WP_Post $post, $taxonomy) {
        $terms = wp_get_object_terms($post->ID, $taxonomy, array('fields' => 'ids'));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }
        return array_values(array_unique(array_map('intval', $terms)));
    }

    /**
     * Whether a term is the parent or a descendant of the configured parent term.
     *
     * @param int    $term_id   Term ID on the post.
     * @param int    $parent_id Configured parent term ID.
     * @param string $taxonomy  Taxonomy slug.
     * @return bool
     */
    private static function term_is_under_parent($term_id, $parent_id, $taxonomy) {
        $term_id   = (int) $term_id;
        $parent_id = (int) $parent_id;
        if ($term_id <= 0 || $parent_id <= 0) {
            return false;
        }

        $term = get_term($term_id, $taxonomy);
        while ($term && !is_wp_error($term)) {
            if ((int) $term->term_id === $parent_id) {
                return true;
            }
            if ((int) $term->parent <= 0) {
                break;
            }
            $term = get_term((int) $term->parent, $taxonomy);
        }

        return false;
    }

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
     * HTML summary for admin list tables (compact term rows with optional truncation).
     *
     * @param array $prefs Preferences-shaped array.
     * @return string Safe HTML fragment.
     */
    public static function describe_selection_admin_html(array $prefs) {
        $html = '';

        foreach (self::get_selection_sections($prefs) as $section) {
            if (empty($section['taxonomies'])) {
                continue;
            }

            $lines = array();
            foreach ($section['taxonomies'] as $tax_row) {
                $lines[] = self::format_admin_taxonomy_row($tax_row['label'], $tax_row['term_names']);
            }

            if (empty($lines)) {
                continue;
            }

            $html .= '<div class="sn-prefs-block sn-prefs-block--topics">';
            $html .= '<div class="sn-prefs-post-type">' . esc_html($section['post_type_label']) . '</div>';
            $html .= '<div class="sn-prefs-taxonomies">' . implode('', $lines) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Format one taxonomy row for admin list tables.
     *
     * @param string   $label      Taxonomy label.
     * @param string[] $term_names Term names.
     * @return string Safe HTML.
     */
    private static function format_admin_taxonomy_row($label, array $term_names) {
        $count   = count($term_names);
        $limit   = 5;
        $full    = implode(', ', $term_names);
        $names_html = esc_html($full);

        if ($count > $limit) {
            $shown      = array_slice($term_names, 0, $limit);
            $names_html = sprintf(
                '<span class="sn-prefs-term-list" title="%1$s">%2$s <span class="sn-prefs-more">+%3$d</span></span>',
                esc_attr($full),
                esc_html(implode(', ', $shown)),
                $count - $limit
            );
        }

        return sprintf(
            '<div class="sn-prefs-taxonomy-row"><span class="sn-prefs-taxonomy-label">%1$s</span> <span class="sn-prefs-term-count">(%2$d)</span>: %3$s</div>',
            esc_html($label),
            $count,
            $names_html
        );
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
            $html .= '<p style="margin:12px 0 4px;font-weight:bold;">' . esc_html($section['post_type_label']) . '</p>';
            foreach ($section['taxonomies'] as $tax_row) {
                $html .= '<p style="margin:0 0 6px;padding-left:12px;">'
                    . '<strong>' . esc_html($tax_row['label']) . ':</strong> '
                    . esc_html(implode(', ', $tax_row['term_names']))
                    . '</p>';
            }
        }
        return $html;
    }

    /**
     * Build ordered post type / taxonomy / term name rows from preferences.
     *
     * Uses stored preference keys (not current Content Types enablement) so admin
     * summaries reflect what the subscriber actually selected.
     *
     * @param array $prefs Preferences-shaped array.
     * @return array<int, array{post_type_label: string, taxonomies: array<int, array{label: string, term_names: string[]}>}>
     */
    private static function get_selection_sections(array $prefs) {
        $sections   = array();
        $post_types = self::ordered_post_types_from_prefs($prefs);

        foreach ($post_types as $post_type) {
            if (empty($prefs[ $post_type ]) || !is_array($prefs[ $post_type ])) {
                continue;
            }

            $tax_rows = array();
            foreach ($prefs[ $post_type ] as $taxonomy => $ids) {
                if ($taxonomy === '_items' || !is_string($taxonomy) || !is_array($ids) || empty($ids)) {
                    continue;
                }
                $term_names = array();
                foreach ($ids as $id) {
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
                    'label'      => class_exists('SubscriberNotifications_Content_Config')
                        ? SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy)
                        : $taxonomy,
                    'term_names' => $term_names,
                );
            }

            if (!empty($tax_rows)) {
                $sections[] = array(
                    'post_type_label' => class_exists('SubscriberNotifications_Content_Config')
                        ? SubscriberNotifications_Content_Config::get_post_type_label($post_type)
                        : $post_type,
                    'taxonomies'      => $tax_rows,
                );
            }
        }

        return $sections;
    }

    /**
     * Post type order for summaries: Content Types order, then any remaining keys.
     *
     * @param array $prefs Preferences-shaped array.
     * @return string[]
     */
    private static function ordered_post_types_from_prefs(array $prefs) {
        $keys  = array_filter(array_keys($prefs), 'is_string');
        $order = array();

        if (class_exists('SubscriberNotifications_Content_Config')) {
            foreach (array_keys(SubscriberNotifications_Content_Config::get_available_post_types()) as $post_type) {
                if (isset($prefs[ $post_type ]) && is_array($prefs[ $post_type ])) {
                    $order[] = $post_type;
                }
            }
        }

        foreach ($keys as $post_type) {
            if (!in_array($post_type, $order, true)) {
                $order[] = $post_type;
            }
        }

        return $order;
    }
}

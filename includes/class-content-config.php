<?php
/**
 * Content Types configuration management.
 *
 * Loads and sanitizes the `subscriber_notifications_content_config` option, which
 * defines which public post types and taxonomies appear on the subscription form
 * and how their terms are filtered.
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Types config helper.
 */
class SubscriberNotifications_Content_Config {

    const OPTION_KEY    = 'subscriber_notifications_content_config';
    const OPTION_GROUP  = 'subscriber_notifications_content_types';
    const TERM_DISPLAY_MODES = array('all', 'children_of', 'include', 'exclude');
    const SINGLE_ITEM_VISIBILITY_RULES    = 'rules';
    const SINGLE_ITEM_VISIBILITY_PICK_LIST  = 'pick_list';

    /**
     * In-process cache to avoid re-reading + re-normalizing the option on every helper call.
     *
     * @var array|null
     */
    private static $cached = null;

    /**
     * Get the raw config option, normalized.
     *
     * @return array Post type slug => post type config.
     */
    public static function get_config() {
        if (is_array(self::$cached)) {
            return self::$cached;
        }

        $raw = get_option(self::OPTION_KEY, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        self::$cached = self::normalize($raw);
        return self::$cached;
    }

    /**
     * Reset the in-memory cache. Called after the option updates.
     */
    public static function clear_cache() {
        self::$cached = null;
    }

    /**
     * Return slugs of post types with `enabled => true`.
     *
     * @return string[]
     */
    public static function get_enabled_post_types() {
        $config = self::get_config();
        $out    = array();
        foreach ($config as $post_type => $entry) {
            if (!empty($entry['enabled'])) {
                $out[] = $post_type;
            }
        }
        return $out;
    }

    /**
     * Return taxonomies (slugs) enabled on the form for a given post type.
     *
     * @param string $post_type Post type slug.
     * @return string[]
     */
    public static function get_form_taxonomies($post_type) {
        $config = self::get_config();
        if (empty($config[$post_type]['enabled'])) {
            return array();
        }
        $out = array();
        $taxonomies = isset($config[$post_type]['taxonomies']) && is_array($config[$post_type]['taxonomies'])
            ? $config[$post_type]['taxonomies']
            : array();
        foreach ($taxonomies as $tax => $entry) {
            if (!empty($entry['enabled_on_form'])) {
                $out[] = $tax;
            }
        }
        return $out;
    }

    /**
     * Return the taxonomy config entry, or null if the taxonomy/post type is not configured.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @return array|null
     */
    public static function get_taxonomy_config($post_type, $taxonomy) {
        $config = self::get_config();
        if (!isset($config[$post_type]['taxonomies'][$taxonomy])) {
            return null;
        }
        return $config[$post_type]['taxonomies'][$taxonomy];
    }

    /**
     * Get the admin-defined label for a post type, falling back to the WP label.
     *
     * @param string $post_type Post type slug.
     * @return string
     */
    public static function get_post_type_label($post_type) {
        $config = self::get_config();
        if (!empty($config[$post_type]['label'])) {
            return $config[$post_type]['label'];
        }
        $pt = get_post_type_object($post_type);
        if ($pt && !empty($pt->labels->name)) {
            return $pt->labels->name;
        }
        return $post_type;
    }

    /**
     * Singular label for a post type (on-page subscribe widget defaults).
     *
     * Uses the Content Types display label when set; otherwise the post type
     * singular_name (e.g. "Post", not "Posts").
     *
     * @param string $post_type Post type slug.
     * @return string
     */
    public static function get_post_type_singular_label($post_type) {
        $config = self::get_config();
        if (!empty($config[$post_type]['label'])) {
            return $config[$post_type]['label'];
        }
        $pt = get_post_type_object($post_type);
        if ($pt && !empty($pt->labels->singular_name)) {
            return $pt->labels->singular_name;
        }
        if ($pt && !empty($pt->labels->name)) {
            return $pt->labels->name;
        }
        return $post_type;
    }

    /**
     * Get the admin-defined label for a taxonomy under a given post type, falling back to the WP label.
     *
     * @param string $post_type Post type slug.
     * @param string $taxonomy  Taxonomy slug.
     * @return string
     */
    public static function get_taxonomy_label($post_type, $taxonomy) {
        $tax_config = self::get_taxonomy_config($post_type, $taxonomy);
        if (!empty($tax_config['label'])) {
            return $tax_config['label'];
        }
        $tx = get_taxonomy($taxonomy);
        if ($tx && !empty($tx->labels->name)) {
            return $tx->labels->name;
        }
        return $taxonomy;
    }

    /**
     * Return slugs of post types with allow_single_item_subscriptions enabled.
     *
     * @return string[]
     */
    public static function get_single_item_post_types() {
        $config = self::get_config();
        $out    = array();
        foreach ($config as $post_type => $entry) {
            if (!empty($entry['allow_single_item_subscriptions'])) {
                $out[] = $post_type;
            }
        }
        return $out;
    }

    /**
     * Post types that show the Notify Subscribers meta box (global form or single-item).
     *
     * @return string[]
     */
    public static function get_meta_box_post_types() {
        return array_values(array_unique(array_merge(
            self::get_enabled_post_types(),
            self::get_single_item_post_types()
        )));
    }

    /**
     * Whether on-page single-post subscriptions are allowed for a post type.
     *
     * @param string $post_type Post type slug.
     * @return bool
     */
    public static function is_single_item_available($post_type) {
        $post_type = sanitize_key((string) $post_type);
        if ($post_type === '') {
            return false;
        }
        $config = self::get_config();
        return !empty($config[ $post_type ]['allow_single_item_subscriptions']);
    }

    /**
     * On-page subscribe widget visibility mode for a post type.
     *
     * @param string $post_type Post type slug.
     * @return string self::SINGLE_ITEM_VISIBILITY_RULES or self::SINGLE_ITEM_VISIBILITY_PICK_LIST
     */
    public static function get_single_item_visibility_mode($post_type) {
        $post_type = sanitize_key((string) $post_type);
        if ($post_type === '') {
            return self::SINGLE_ITEM_VISIBILITY_RULES;
        }
        $config = self::get_config();
        $entry  = isset($config[ $post_type ]) && is_array($config[ $post_type ]) ? $config[ $post_type ] : array();
        return self::resolve_single_item_visibility_mode($entry);
    }

    /**
     * Resolve stored visibility mode.
     *
     * @param array $entry Post type config entry.
     * @return string
     */
    private static function resolve_single_item_visibility_mode(array $entry) {
        $mode = isset($entry['single_item_visibility_mode']) ? (string) $entry['single_item_visibility_mode'] : '';
        if ($mode === self::SINGLE_ITEM_VISIBILITY_PICK_LIST) {
            return self::SINGLE_ITEM_VISIBILITY_PICK_LIST;
        }
        return self::SINGLE_ITEM_VISIBILITY_RULES;
    }

    /**
     * Whether the on-page subscribe widget may render for this post.
     *
     * @param WP_Post $post Post object.
     * @return bool
     */
    public static function is_post_eligible_for_single_item($post) {
        if (!$post instanceof WP_Post) {
            return false;
        }

        if (!self::is_single_item_available($post->post_type)) {
            return false;
        }

        if ($post->post_status !== 'publish') {
            return false;
        }

        if (subscriber_notifications_is_frontend_page((int) $post->ID)) {
            return false;
        }

        $entry           = self::get_config()[ $post->post_type ] ?? array();
        $visibility_mode = self::resolve_single_item_visibility_mode($entry);

        if ($visibility_mode === self::SINGLE_ITEM_VISIBILITY_PICK_LIST) {
            $include_ids = isset($entry['single_item_include_post_ids']) ? (array) $entry['single_item_include_post_ids'] : array();
            return in_array((int) $post->ID, array_map('intval', $include_ids), true);
        }

        $exclude_ids = isset($entry['single_item_exclude_post_ids']) ? (array) $entry['single_item_exclude_post_ids'] : array();
        if (!empty($exclude_ids) && in_array((int) $post->ID, array_map('intval', $exclude_ids), true)) {
            return false;
        }

        return self::post_passes_single_item_taxonomy_gate($post);
    }

    /**
     * OR gate across restrictive taxonomy rules (broadcast mode only).
     *
     * @param WP_Post $post Post object.
     * @return bool
     */
    private static function post_passes_single_item_taxonomy_gate(WP_Post $post) {
        $config    = self::get_config();
        $post_type = $post->post_type;
        $taxonomies = isset($config[ $post_type ]['taxonomies']) && is_array($config[ $post_type ]['taxonomies'])
            ? $config[ $post_type ]['taxonomies']
            : array();

        $restrictive = array();
        foreach ($taxonomies as $taxonomy => $tax_entry) {
            if (!is_string($taxonomy) || !is_array($tax_entry)) {
                continue;
            }
            $mode = isset($tax_entry['term_display']) ? (string) $tax_entry['term_display'] : 'all';
            if ($mode !== 'all') {
                $restrictive[] = $taxonomy;
            }
        }

        if (empty($restrictive)) {
            return true;
        }

        foreach ($restrictive as $taxonomy) {
            if (SubscriberNotifications_Term_Resolver::post_passes_taxonomy_display_rule($post, $post_type, $taxonomy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the plugin configured enough for subscriptions?
     *
     * True when at least one post type has form taxonomies OR single-item subscriptions enabled.
     *
     * @return bool
     */
    public static function is_configured() {
        foreach (self::get_enabled_post_types() as $post_type) {
            if (!empty(self::get_form_taxonomies($post_type))) {
                return true;
            }
        }
        return !empty(self::get_single_item_post_types());
    }

    /**
     * Public post types available for selection on the Content Types admin page.
     *
     * Excludes attachment.
     *
     * @return WP_Post_Type[] Keyed by slug.
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        if (!is_array($post_types)) {
            return array();
        }
        unset($post_types['attachment']);
        return $post_types;
    }

    /**
     * Taxonomies available for a given post type on the Content Types admin page.
     *
     * Excludes internal/system taxonomies (`nav_menu`, `link_category`, `post_format`, anything starting with `wp_`).
     *
     * @param string $post_type Post type slug.
     * @return WP_Taxonomy[] Keyed by slug.
     */
    public static function get_available_taxonomies($post_type) {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        if (!is_array($taxonomies)) {
            return array();
        }
        $out = array();
        foreach ($taxonomies as $slug => $tax) {
            if (empty($tax->public) && empty($tax->show_ui)) {
                continue;
            }
            if (in_array($slug, array('nav_menu', 'link_category', 'post_format'), true)) {
                continue;
            }
            if (strpos($slug, 'wp_') === 0) {
                continue;
            }
            $out[$slug] = $tax;
        }
        return $out;
    }

    /**
     * Register the Settings API hook + sanitize callback.
     */
    public static function register() {
        add_action('admin_init', array(__CLASS__, 'register_setting'));
        add_action('update_option_' . self::OPTION_KEY, array(__CLASS__, 'on_update'), 10, 0);
        add_action('add_option_' . self::OPTION_KEY, array(__CLASS__, 'on_update'), 10, 0);
    }

    /**
     * Hook target: clear cache after option writes.
     */
    public static function on_update() {
        self::clear_cache();
    }

    /**
     * Register the option with the Settings API.
     */
    public static function register_setting() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => array(__CLASS__, 'sanitize'),
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Sanitize the nested config array submitted from the admin Content Types form.
     *
     * @param mixed $input Raw POST input.
     * @return array Sanitized config.
     */
    public static function sanitize($input) {
        $sanitized = array();
        if (!is_array($input)) {
            self::clear_cache();
            return $sanitized;
        }

        $available_post_types = self::get_available_post_types();

        foreach ($input as $post_type => $entry) {
            $post_type = sanitize_key($post_type);
            if (!isset($available_post_types[$post_type])) {
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }

            $include_post_ids = self::sanitize_post_id_list(
                isset($entry['single_item_include_post_ids']) ? $entry['single_item_include_post_ids'] : array(),
                $post_type
            );
            $exclude_post_ids = self::sanitize_post_id_list(
                isset($entry['single_item_exclude_post_ids']) ? $entry['single_item_exclude_post_ids'] : array(),
                $post_type
            );

            $visibility_mode = isset($entry['single_item_visibility_mode']) ? sanitize_key((string) $entry['single_item_visibility_mode']) : '';
            if (!in_array($visibility_mode, array(self::SINGLE_ITEM_VISIBILITY_RULES, self::SINGLE_ITEM_VISIBILITY_PICK_LIST), true)) {
                $visibility_mode = self::SINGLE_ITEM_VISIBILITY_RULES;
            }

            if ($visibility_mode === self::SINGLE_ITEM_VISIBILITY_PICK_LIST) {
                $exclude_post_ids = array();
            } else {
                $visibility_mode  = self::SINGLE_ITEM_VISIBILITY_RULES;
                $include_post_ids = array();
            }

            $sanitized[$post_type] = array(
                'enabled'                         => !empty($entry['enabled']),
                'allow_single_item_subscriptions' => !empty($entry['allow_single_item_subscriptions']),
                'label'                           => isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '',
                'single_item_visibility_mode'     => $visibility_mode,
                'single_item_include_post_ids'    => $include_post_ids,
                'single_item_exclude_post_ids'    => $exclude_post_ids,
                'taxonomies'                      => array(),
            );

            $available_taxonomies = self::get_available_taxonomies($post_type);
            $taxonomies = isset($entry['taxonomies']) && is_array($entry['taxonomies']) ? $entry['taxonomies'] : array();

            foreach ($taxonomies as $tax => $tax_entry) {
                $tax = sanitize_key($tax);
                if (!isset($available_taxonomies[$tax])) {
                    continue;
                }
                if (!is_array($tax_entry)) {
                    continue;
                }

                $term_display = isset($tax_entry['term_display']) ? (string) $tax_entry['term_display'] : 'all';
                if (!in_array($term_display, self::TERM_DISPLAY_MODES, true)) {
                    $term_display = 'all';
                }

                $tax_object = $available_taxonomies[$tax];
                if ($term_display === 'children_of' && empty($tax_object->hierarchical)) {
                    $term_display = 'all';
                }

                $sanitized[$post_type]['taxonomies'][$tax] = array(
                    'enabled_on_form'  => !empty($tax_entry['enabled_on_form']),
                    'label'            => isset($tax_entry['label']) ? sanitize_text_field((string) $tax_entry['label']) : '',
                    'term_display'     => $term_display,
                    'parent_term_id'   => isset($tax_entry['parent_term_id']) ? max(0, (int) $tax_entry['parent_term_id']) : 0,
                    'include_term_ids' => self::sanitize_term_id_list(isset($tax_entry['include_term_ids']) ? $tax_entry['include_term_ids'] : array()),
                    'exclude_term_ids' => self::sanitize_term_id_list(isset($tax_entry['exclude_term_ids']) ? $tax_entry['exclude_term_ids'] : array()),
                );
            }
        }

        self::clear_cache();
        return $sanitized;
    }

    /**
     * Normalize a stored config array on read (defensive against partial writes).
     *
     * @param array $raw Raw stored option value.
     * @return array
     */
    private static function normalize(array $raw) {
        $out = array();
        foreach ($raw as $post_type => $entry) {
            if (!is_string($post_type) || !is_array($entry)) {
                continue;
            }
            $include_post_ids = self::sanitize_post_id_list(
                isset($entry['single_item_include_post_ids']) ? $entry['single_item_include_post_ids'] : array(),
                $post_type
            );
            $exclude_post_ids = self::sanitize_post_id_list(
                isset($entry['single_item_exclude_post_ids']) ? $entry['single_item_exclude_post_ids'] : array(),
                $post_type
            );
            $visibility_mode = self::resolve_single_item_visibility_mode($entry);

            if ($visibility_mode === self::SINGLE_ITEM_VISIBILITY_PICK_LIST) {
                $exclude_post_ids = array();
            } else {
                $visibility_mode  = self::SINGLE_ITEM_VISIBILITY_RULES;
                $include_post_ids = array();
            }

            $out[$post_type] = array(
                'enabled'                         => !empty($entry['enabled']),
                'allow_single_item_subscriptions' => !empty($entry['allow_single_item_subscriptions']),
                'label'                           => isset($entry['label']) ? (string) $entry['label'] : '',
                'single_item_visibility_mode'     => $visibility_mode,
                'single_item_include_post_ids'    => $include_post_ids,
                'single_item_exclude_post_ids'    => $exclude_post_ids,
                'taxonomies'                      => array(),
            );
            $taxonomies = isset($entry['taxonomies']) && is_array($entry['taxonomies']) ? $entry['taxonomies'] : array();
            foreach ($taxonomies as $tax => $tax_entry) {
                if (!is_string($tax) || !is_array($tax_entry)) {
                    continue;
                }
                $term_display = isset($tax_entry['term_display']) ? (string) $tax_entry['term_display'] : 'all';
                if (!in_array($term_display, self::TERM_DISPLAY_MODES, true)) {
                    $term_display = 'all';
                }
                $out[$post_type]['taxonomies'][$tax] = array(
                    'enabled_on_form'  => !empty($tax_entry['enabled_on_form']),
                    'label'            => isset($tax_entry['label']) ? (string) $tax_entry['label'] : '',
                    'term_display'     => $term_display,
                    'parent_term_id'   => isset($tax_entry['parent_term_id']) ? max(0, (int) $tax_entry['parent_term_id']) : 0,
                    'include_term_ids' => self::sanitize_term_id_list(isset($tax_entry['include_term_ids']) ? $tax_entry['include_term_ids'] : array()),
                    'exclude_term_ids' => self::sanitize_term_id_list(isset($tax_entry['exclude_term_ids']) ? $tax_entry['exclude_term_ids'] : array()),
                );
            }
        }
        return $out;
    }

    /**
     * Coerce an input list (string CSV or array) into a sorted unique array of positive ints.
     *
     * @param mixed $list Raw list.
     * @return int[]
     */
    private static function sanitize_term_id_list($list) {
        if (is_string($list)) {
            $list = preg_split('/[\s,]+/', $list);
        }
        if (!is_array($list)) {
            return array();
        }
        $out = array();
        foreach ($list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        sort($out);
        return array_values($out);
    }

    /**
     * Sanitize a list of post IDs for single-item include/exclude.
     *
     * @param mixed  $list      Raw list from POST.
     * @param string $post_type Expected post type slug.
     * @return int[]
     */
    private static function sanitize_post_id_list($list, $post_type) {
        $ids = self::sanitize_term_id_list($list);
        if (empty($ids)) {
            return array();
        }

        $post_type = sanitize_key((string) $post_type);
        $out       = array();
        foreach ($ids as $id) {
            $post = get_post((int) $id);
            if (!$post || $post->post_type !== $post_type || $post->post_status !== 'publish') {
                continue;
            }
            $out[ (int) $post->ID ] = (int) $post->ID;
        }
        sort($out);
        return array_values($out);
    }
}

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

            $sanitized[$post_type] = array(
                'enabled'                         => !empty($entry['enabled']),
                'allow_single_item_subscriptions' => !empty($entry['allow_single_item_subscriptions']),
                'label'                           => isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '',
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
            $out[$post_type] = array(
                'enabled'                         => !empty($entry['enabled']),
                'allow_single_item_subscriptions' => !empty($entry['allow_single_item_subscriptions']),
                'label'                           => isset($entry['label']) ? (string) $entry['label'] : '',
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
}

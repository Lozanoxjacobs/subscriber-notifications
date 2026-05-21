<?php
/**
 * Content Types admin page.
 *
 * Registers a "Content Types" submenu under Notifications. Renders a Settings API
 * form that posts to `options.php` so the configuration persists via WordPress
 * core's option-save flow (no custom admin_post / handle_settings_save handler).
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Types admin page controller.
 */
class SubscriberNotifications_Content_Types_Admin {

    const PAGE_SLUG = 'subscriber-notifications-content-types';

    /**
     * Constructor wires admin hooks.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_notices', array($this, 'maybe_render_empty_config_notice'));
        add_action('wp_ajax_subscriber_notifications_search_terms', array($this, 'ajax_search_terms'));
    }

    /**
     * Add the Content Types submenu under the plugin top-level menu.
     */
    public function register_menu() {
        add_submenu_page(
            'subscriber-notifications',
            __('Content Types', 'subscriber-notifications'),
            __('Content Types', 'subscriber-notifications'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue assets only on the Content Types screen.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_assets($hook_suffix) {
        if (strpos((string) $hook_suffix, self::PAGE_SLUG) === false) {
            return;
        }
        wp_enqueue_script('postbox');
        wp_enqueue_script(
            'subscriber-notifications-content-types-admin',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/js/content-types-admin.js',
            array('jquery', 'postbox'),
            SUBSCRIBER_NOTIFICATIONS_VERSION,
            true
        );
    }

    /**
     * Show a dashboard-wide notice when Content Types is not yet configured.
     */
    public function maybe_render_empty_config_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (SubscriberNotifications_Content_Config::is_configured()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->id) && strpos((string) $screen->id, self::PAGE_SLUG) !== false) {
            return;
        }
        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);
        echo '<div class="notice notice-warning"><p>';
        echo wp_kses_post(sprintf(
            /* translators: %s: Settings link */
            __('Subscriber Notifications is not yet configured. <a href="%s">Set up Content Types</a> to start collecting subscriptions.', 'subscriber-notifications'),
            esc_url($url)
        ));
        echo '</p></div>';
    }

    /**
     * Render the Content Types admin page.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'subscriber-notifications'));
        }

        $config = SubscriberNotifications_Content_Config::get_config();
        $available_post_types = SubscriberNotifications_Content_Config::get_available_post_types();

        $template = SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-content-types.php';
        if (!file_exists($template)) {
            return;
        }
        include $template;
    }

    /**
     * AJAX endpoint to search terms for an admin taxonomy via name match.
     *
     * Returns `{ results: [ { id, text } ] }` shaped like Select2 expects (in case we wire it later).
     */
    public function ajax_search_terms() {
        check_ajax_referer('subscriber_notifications_search_terms', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'subscriber-notifications')), 403);
        }
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
        $search   = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(array('message' => __('Invalid taxonomy.', 'subscriber-notifications')), 400);
        }
        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => 50,
            'search'     => $search,
        ));
        if (is_wp_error($terms)) {
            wp_send_json_error(array('message' => $terms->get_error_message()), 500);
        }
        $results = array();
        foreach ($terms as $term) {
            $results[] = array(
                'id'   => (int) $term->term_id,
                'text' => $term->name,
            );
        }
        wp_send_json_success(array('results' => $results));
    }
}

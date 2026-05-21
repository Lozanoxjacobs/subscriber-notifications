<?php
/**
 * Notification meta box + post-save feed flag handler.
 *
 * In v3 the meta box is registered for every post type enabled in Content Types.
 * A single `save_post` handler sets the feed-inclusion meta when the checkbox is
 * checked; the scheduled digest pipeline still does the actual sending (no
 * immediate blast from the meta box).
 *
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notifications class for managing email notifications
 */
class SubscriberNotifications_Notifications {

    /**
     * Database instance.
     *
     * @var SubscriberNotifications_Database
     */
    private $database;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Database $database Database instance.
     */
    public function __construct($database) {
        $this->database = $database;
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('save_post', array($this, 'handle_post_update'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_notification_meta_boxes'));
    }

    /**
     * Add the "Notify Subscribers" meta box on each enabled post type.
     */
    public function add_notification_meta_boxes() {
        $post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        foreach ($post_types as $post_type) {
            add_meta_box(
                'subscriber_notifications_update',
                __('Notify Subscribers', 'subscriber-notifications'),
                array($this, 'notification_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Notification meta box markup.
     *
     * @param WP_Post $post Post object.
     */
    public function notification_meta_box($post) {
        wp_nonce_field('subscriber_notifications_meta_box', 'subscriber_notifications_nonce');
        $include_in_feed = (int) get_post_meta($post->ID, '_subscriber_notifications_include_in_feed', true);
        ?>
        <div class="subscriber-notifications-meta-box">
            <p><?php esc_html_e('Include this content in the next subscriber digest:', 'subscriber-notifications'); ?></p>
            <label>
                <input type="checkbox" id="notify_subscribers" name="notify_subscribers" value="1" <?php checked($include_in_feed, 1); ?>>
                <?php esc_html_e('Notify subscribers about this content', 'subscriber-notifications'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('Subscribers receive this content in their scheduled digest based on their frequency preference. No email is sent immediately.', 'subscriber-notifications'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Handle save_post for any enabled post type.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function handle_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        if (!in_array($post->post_type, $enabled_post_types, true)) {
            return;
        }

        $nonce = isset($_POST['subscriber_notifications_nonce']) ? sanitize_text_field(wp_unslash($_POST['subscriber_notifications_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'subscriber_notifications_meta_box')) {
            return;
        }

        if (isset($_POST['notify_subscribers']) && $_POST['notify_subscribers'] === '1') {
            update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 1);
            update_post_meta($post_id, '_subscriber_notifications_last_notification_date', current_time('mysql'));
        } else {
            update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 0);
        }
    }
}

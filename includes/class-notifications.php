<?php
/**
 * Notification meta box + post-save feed flag handler.
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
     * @var SubscriberNotifications_Item_Notifications|null
     */
    private $item_notifications;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Database $database Database instance.
     */
    public function __construct($database) {
        $this->database           = $database;
        $this->item_notifications = new SubscriberNotifications_Item_Notifications($database);
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('save_post', array($this, 'handle_post_update'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_notification_meta_boxes'));
        add_action('admin_notices', array('SubscriberNotifications_Item_Notifications', 'maybe_show_admin_notice'));
    }

    /**
     * Add the Notify Subscribers meta box on configured post types.
     */
    public function add_notification_meta_boxes() {
        $post_types = SubscriberNotifications_Content_Config::get_meta_box_post_types();
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
        $include_in_feed = (int) get_post_meta($post->ID, SubscriberNotifications_Preferences::META_INCLUDE_IN_FEED, true);
        ?>
        <div class="subscriber-notifications-meta-box">
            <p>
                <label>
                    <input type="checkbox" id="sn_include_in_feed" name="sn_include_in_feed" value="1" <?php checked($include_in_feed, 1); ?>>
                    <?php esc_html_e('Include in subscriber digests', 'subscriber-notifications'); ?>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e('Adds this content to scheduled topic digest emails. Stays checked until you remove it.', 'subscriber-notifications'); ?>
            </p>

            <p>
                <label>
                    <input type="checkbox" id="sn_notify_item_subscribers" name="sn_notify_item_subscribers" value="1">
                    <?php esc_html_e('Email item subscribers about this update', 'subscriber-notifications'); ?>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e('Sends immediately to people subscribed to this specific page. Does not stay checked after save.', 'subscriber-notifications'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Handle save_post for meta box post types.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function handle_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $meta_box_types = SubscriberNotifications_Content_Config::get_meta_box_post_types();
        if (!in_array($post->post_type, $meta_box_types, true)) {
            return;
        }

        $nonce = isset($_POST['subscriber_notifications_nonce']) ? sanitize_text_field(wp_unslash($_POST['subscriber_notifications_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'subscriber_notifications_meta_box')) {
            return;
        }

        if (!empty($_POST['sn_include_in_feed']) && $_POST['sn_include_in_feed'] === '1') {
            update_post_meta($post_id, SubscriberNotifications_Preferences::META_INCLUDE_IN_FEED, 1);
            update_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE, current_time('mysql'));
        } else {
            delete_post_meta($post_id, SubscriberNotifications_Preferences::META_INCLUDE_IN_FEED);
            delete_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE);
        }

        if (!empty($_POST['sn_notify_item_subscribers']) && $_POST['sn_notify_item_subscribers'] === '1') {
            $result = $this->item_notifications->send_item_update($post_id);
            if (!empty($result['message'])) {
                SubscriberNotifications_Item_Notifications::set_admin_notice(get_current_user_id(), $result['message']);
            }
        }
    }
}

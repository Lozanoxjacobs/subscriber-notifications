<?php
/**
 * Notification management class
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
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     * 
     * @param SubscriberNotifications_Database $database Database instance
     */
    public function __construct($database) {
        $this->database = $database;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('save_post', array($this, 'handle_post_update'), 10, 2);
        add_action('tribe_events_update_meta', array($this, 'handle_event_update'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_notification_meta_boxes'));
    }
    
    /**
     * Add notification meta boxes
     */
    public function add_notification_meta_boxes() {
        // Add to posts
        add_meta_box(
            'subscriber_notifications_update',
            __('Notify Subscribers', 'subscriber-notifications'),
            array($this, 'notification_meta_box'),
            'post',
            'side',
            'high'
        );
        
        // Add to events
        if (class_exists('Tribe__Events__Main')) {
            add_meta_box(
                'subscriber_notifications_update',
                __('Notify Subscribers', 'subscriber-notifications'),
                array($this, 'notification_meta_box'),
                'tribe_events',
                'side',
                'high'
            );
        }
    }
    
    /**
     * Notification meta box
     * 
     * @param WP_Post $post Post object
     */
    public function notification_meta_box($post) {
        wp_nonce_field('subscriber_notifications_meta_box', 'subscriber_notifications_nonce');
        
        $post_type = $post->post_type;
        $categories = array();
        
        if ($post_type === 'post') {
            $post_categories = get_the_category($post->ID);
            foreach ($post_categories as $cat) {
                $categories[] = $cat->term_id;
            }
        } elseif ($post_type === 'tribe_events') {
            $event_categories = wp_get_post_terms($post->ID, 'tribe_events_cat');
            foreach ($event_categories as $cat) {
                $categories[] = $cat->term_id;
            }
        }
        
        ?>
        <div class="subscriber-notifications-meta-box">
            <p><?php _e('Check this box to include this content in subscriber notifications:', 'subscriber-notifications'); ?></p>
            
            <label>
                <input type="checkbox" id="notify_subscribers" name="notify_subscribers" value="1">
                <?php _e('Notify subscribers about this content', 'subscriber-notifications'); ?>
            </label>
            
            <div id="notification-options" style="display: none; margin-top: 10px;">
                <label for="notification_message">
                    <?php _e('Custom message (optional):', 'subscriber-notifications'); ?>
                </label>
                <textarea id="notification_message" name="notification_message" rows="3" style="width: 100%;"></textarea>
                
                <p class="description">
                    <?php _e('Leave blank to use default notification message.', 'subscriber-notifications'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle post update
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function handle_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Additional validation: Only send notifications for published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Additional validation: Only send notifications for posts (not pages, attachments, etc.)
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Handle feed inclusion based on checkbox
        if (isset($_POST['notify_subscribers']) && $_POST['notify_subscribers'] === '1') {
            // Additional safety check - make sure this is a manual admin action
            if (isset($_POST['subscriber_notifications_nonce']) && wp_verify_nonce($_POST['subscriber_notifications_nonce'], 'subscriber_notifications_meta_box')) {
                // Mark post for inclusion in feeds
                update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 1);
                update_post_meta($post_id, '_subscriber_notifications_last_notification_date', current_time('mysql'));
                
                // Note: Immediate notification sending removed - scheduled system handles emails
            }
        } else {
            // Don't include in feeds
            update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 0);
        }
    }
    
    /**
     * Handle event update
     * 
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     */
    public function handle_event_update($post_id, $meta_key) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get the post object for validation
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Additional validation: Only send notifications for published events
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Additional validation: Only send notifications for tribe_events post type
        if ($post->post_type !== 'tribe_events') {
            return;
        }
        
        // Handle feed inclusion based on checkbox
        if (isset($_POST['notify_subscribers']) && $_POST['notify_subscribers'] === '1') {
            // Additional safety check - make sure this is a manual admin action
            if (isset($_POST['subscriber_notifications_nonce']) && wp_verify_nonce($_POST['subscriber_notifications_nonce'], 'subscriber_notifications_meta_box')) {
                // Mark event for inclusion in feeds
                update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 1);
                update_post_meta($post_id, '_subscriber_notifications_last_notification_date', current_time('mysql'));
                
                // Note: Immediate notification sending removed - scheduled system handles emails
            }
        } else {
            // Don't include in feeds
            update_post_meta($post_id, '_subscriber_notifications_include_in_feed', 0);
        }
    }
    
    /**
     * Send update notification
     * 
     * @param int $post_id Post ID
     * @param string $type Post type
     */
    private function send_update_notification($post_id, $type) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Get relevant categories
        $categories = array();
        if ($type === 'post') {
            $post_categories = get_the_category($post_id);
            foreach ($post_categories as $cat) {
                $categories[] = $cat->term_id;
            }
        } elseif ($type === 'event') {
            $event_categories = wp_get_post_terms($post_id, 'tribe_events_cat');
            foreach ($event_categories as $cat) {
                $categories[] = $cat->term_id;
            }
        }
        
        if (empty($categories)) {
            return;
        }
        
        // Get subscribers interested in these categories
        $subscribers = $this->get_subscribers_for_categories($categories, $type);
        
        if (empty($subscribers)) {
            return;
        }
        
        // Prepare notification content
        $custom_message = isset($_POST['notification_message']) ? sanitize_textarea_field($_POST['notification_message']) : '';
        
        if ($custom_message) {
            $subject = $custom_message;
            $content = $custom_message;
        } else {
            $subject = sprintf(__('Update: %s', 'subscriber-notifications'), $post->post_title);
            $content = sprintf(__('There has been an update to: %s', 'subscriber-notifications'), $post->post_title);
            $content .= '<br><br><a href="' . get_permalink($post_id) . '">' . __('View Update', 'subscriber-notifications') . '</a>';
        }
        
        // Send to each subscriber with shortcode processing
        foreach ($subscribers as $subscriber) {
            $shortcodes = new SubscriberNotifications_Shortcodes();
            $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber, null);
            $processed_content = $shortcodes->process_shortcodes($content, $subscriber, null);
            
            $this->send_individual_notification($subscriber, $processed_subject, $processed_content, 0, 0);
        }
    }
    
    /**
     * Get subscribers for categories
     * 
     * @param array $categories Category IDs
     * @param string $type Category type
     * @return array Array of subscriber objects
     */
    private function get_subscribers_for_categories($categories, $type) {
        global $wpdb;
        
        $subscribers_table = $wpdb->prefix . 'subscriber_notifications';
        $category_field = ($type === 'post') ? 'news_categories' : 'meeting_categories';
        
        $where_conditions = array("status = 'active'");
        $where_values = array();
        
        foreach ($categories as $cat_id) {
            $where_conditions[] = "FIND_IN_SET(%d, {$category_field})";
            $where_values[] = $cat_id;
        }
        
        $where_clause = implode(' OR ', $where_conditions);
        
        $sql = $wpdb->prepare("
            SELECT * FROM {$subscribers_table} 
            WHERE status = 'active' AND ({$where_clause})
        ", $where_values);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Send individual notification
     * 
     * @param object $subscriber Subscriber object
     * @param string $subject Email subject
     * @param string $content Email content
     * @param int $notification_id Notification ID
     * @param int $log_id Log ID
     * @return bool True on success, false on failure
     */
    private function send_individual_notification($subscriber, $subject, $content, $notification_id = 0, $log_id = 0) {
        // Send via SendGrid
        $sendgrid = new SubscriberNotifications_SendGrid();
        $result = $sendgrid->send_email(
            $subscriber->email,
            $subject,
            $content,
            $subscriber->id,
            $notification_id
        );
        
        if ($result) {
            // Update last notified time
            $this->database->update_subscriber($subscriber->id, array(
                'last_notified' => current_time('mysql')
            ));
        }
        
        return $result;
    }
}
<?php
/**
 * Scheduler class for handling cron jobs
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scheduler class for managing scheduled notifications
 */
class SubscriberNotifications_Scheduler {
    
    /**
     * Notification batch size for processing
     */
    const NOTIFICATION_BATCH_SIZE = 10;
    
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
        add_action('subscriber_notifications_process_queue', array($this, 'process_queue'));
        add_action('subscriber_notifications_send_daily', array($this, 'send_daily_notifications'));
        add_action('subscriber_notifications_send_weekly', array($this, 'send_weekly_notifications'));
        add_action('subscriber_notifications_send_monthly', array($this, 'send_monthly_notifications'));
        
        // Add 'every_minute' schedule if not already added by another plugin
        add_filter('cron_schedules', array($this, 'add_every_minute_schedule'));
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
    }
    
    /**
     * Process email queue
     */
    public function process_queue() {
        global $wpdb;
        
        // Get pending ONE-TIME notifications only
        // Recurring notifications are handled by frequency-specific crons (send_daily_notifications, etc.)
        $notifications = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE status = 'pending' 
            AND is_recurring = 0
            ORDER BY created_date ASC
            LIMIT " . self::NOTIFICATION_BATCH_SIZE . "
        ");
        
        foreach ($notifications as $notification) {
            // Check if it's the right time for this frequency
            if ($this->should_process_notification($notification->frequency_target)) {
                $this->send_scheduled_notification($notification->id);
            }
        }
    }
    
    /**
     * Check if it's the right time to process a notification for a given frequency
     */
    private function should_process_notification($frequency) {
        $current_time = current_time('H:i');
        $current_day = current_time('w'); // 0 = Sunday, 1 = Monday, etc.
        $current_date = current_time('j'); // Day of month (1-31)
        
        switch ($frequency) {
            case 'daily':
                $daily_time = subscriber_notifications_get_option('daily_send_time', '09:00');
                return $current_time >= $daily_time;
                
            case 'weekly':
                $weekly_day = subscriber_notifications_get_option('weekly_send_day', 'tuesday');
                $weekly_time = subscriber_notifications_get_option('weekly_send_time', '14:00');
                
                // Convert day name to number (0 = Sunday, 1 = Monday, etc.)
                $day_numbers = array(
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                );
                $target_day = $day_numbers[$weekly_day];
                
                return ($current_day == $target_day) && ($current_time >= $weekly_time);
                
            case 'monthly':
                $monthly_day = subscriber_notifications_get_option('monthly_send_day', 15);
                $monthly_time = subscriber_notifications_get_option('monthly_send_time', '14:00');
                
                // Handle months with fewer days (e.g., if set to 31st but month only has 30 days)
                // Use timezone-aware method to get days in month
                $timezone = wp_timezone();
                $now = new DateTime('now', $timezone);
                $days_in_month = $now->format('t');
                $target_day = min($monthly_day, $days_in_month);
                
                return ($current_date == $target_day) && ($current_time >= $monthly_time);
                
            default:
                return false;
        }
    }
    
    
    /**
     * Send scheduled notification
     * 
     * @param int $notification_id Notification ID
     * @return bool True on success, false on failure
     */
    public function send_scheduled_notification(int $notification_id): bool {
        $notification = $this->get_notification($notification_id);
        
        if (!$notification || $notification->status !== 'pending') {
            return false;
        }
        
        // Get target subscribers
        $subscribers = $this->get_target_subscribers($notification);
        
        if (empty($subscribers)) {
            return false;
        }
        
        // Send emails to subscribers
        $sent_count = $this->send_to_subscribers($subscribers, $notification);
        
        // Update notification status
        $this->update_notification_status($notification, $sent_count);
        
        return $sent_count > 0;
    }
    
    /**
     * Get notification by ID
     * 
     * @param int $notification_id Notification ID
     * @return object|null Notification object or null
     */
    private function get_notification(int $notification_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue WHERE id = %d",
            $notification_id
        ));
    }
    
    /**
     * Prepare notification content for a subscriber
     * 
     * @param object $notification Notification object
     * @param object $subscriber Subscriber object
     * @return array Array with 'subject' and 'content' keys
     */
    private function prepare_notification_content($notification, $subscriber): array {
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $subject = $shortcodes->process_shortcodes($notification->subject, $subscriber, $notification);
        $content = $shortcodes->process_shortcodes($notification->content, $subscriber, $notification);
        
        // Wrap content with CSS (default CSS or custom CSS)
        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $content = $formatter->wrap_content_with_css($content, $email_css, $subscriber);
        
        return array(
            'subject' => $subject,
            'content' => $content
        );
    }
    
    /**
     * Send notification to subscribers
     * 
     * @param array $subscribers Array of subscriber objects
     * @param object $notification Notification object
     * @return int Number of emails sent successfully
     */
    private function send_to_subscribers(array $subscribers, $notification): int {
        $sent_count = 0;
        $email_sender = new SubscriberNotifications_Email_Sender();
        
        foreach ($subscribers as $subscriber) {
            // Check if subscriber has relevant content
            if (!$this->has_relevant_content($subscriber, $notification->frequency_target, $notification)) {
                continue;
            }
            
            // Prepare content for this subscriber
            $prepared = $this->prepare_notification_content($notification, $subscriber);
            
            // Send email
            if ($email_sender->send_email($subscriber->email, $prepared['subject'], $prepared['content'], $subscriber->id, $notification->id)) {
                $sent_count++;
            } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Subscriber Notifications: Failed to send email to subscriber ID: " . $subscriber->id);
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Update notification status after sending
     * 
     * @param object $notification Notification object
     * @param int $sent_count Number of emails sent
     * @return void
     */
    private function update_notification_status($notification, int $sent_count): void {
        global $wpdb;
        
        if ($notification->is_recurring) {
            // For recurring notifications, update next send date and keep as pending
            $next_send_date = $this->calculate_next_recurring_date($notification->frequency_target);
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notifications_queue',
                array(
                    'last_sent_date' => current_time('mysql'),
                    'next_send_date' => $next_send_date,
                    'recurrence_count' => $notification->recurrence_count + 1
                ),
                array('id' => $notification->id),
                array('%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // For one-time notifications, mark as sent
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notifications_queue',
                array(
                    'status' => 'sent',
                    'sent_date' => current_time('mysql')
                ),
                array('id' => $notification->id),
                array('%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Get target subscribers for a notification.
     *
     * SQL pre-filters active subscribers by frequency. The fine-grained term
     * overlap test is done in PHP against the JSON preferences columns.
     *
     * @param object $notification Notification row.
     * @return array<int, object> Subscriber rows.
     */
    private function get_target_subscribers($notification) {
        global $wpdb;

        $subscribers_table = $wpdb->prefix . 'subscriber_notifications';
        $where_conditions  = array("status = 'active'");
        $where_values      = array();

        if (!empty($notification->frequency_target)) {
            $where_conditions[] = 'frequency = %s';
            $where_values[]     = $notification->frequency_target;
        }

        $where_clause = implode(' AND ', $where_conditions);

        if (!empty($where_values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$subscribers_table} WHERE {$where_clause}",
                $where_values
            );
        } else {
            $sql = "SELECT * FROM {$subscribers_table} WHERE {$where_clause}";
        }

        $subscribers = $wpdb->get_results($sql);
        if (empty($subscribers)) {
            return array();
        }

        $target_prefs = isset($notification->target_preferences) ? $notification->target_preferences : '';
        $target_prefs = SubscriberNotifications_Preferences::decode($target_prefs);

        // If a notification has no target preferences (misconfigured), block sending.
        if (empty($target_prefs)) {
            return array();
        }

        $matched = array();
        foreach ($subscribers as $subscriber) {
            $subscriber_prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');
            if (SubscriberNotifications_Preferences::terms_overlap($subscriber_prefs, $target_prefs)) {
                $matched[] = $subscriber;
            }
        }
        return $matched;
    }
    
    /**
     * Check if a subscriber has relevant feed-flagged content for this notification.
     *
     * Walks the subscriber's preferences ∩ notification target preferences, scoped to
     * the configured post types/taxonomies, and probes each post_type with a generic
     * `tax_query` for any feed-included post within the digest window.
     *
     * @param object $subscriber  Subscriber row.
     * @param string $frequency   Notification frequency (daily, weekly, monthly).
     * @param object $notification Notification row.
     * @return bool True if at least one matching post is found across all post types.
     */
    private function has_relevant_content($subscriber, $frequency, $notification) {
        switch ($frequency) {
            case 'daily':
                $cutoff_timestamp = strtotime('1 day ago');
                break;
            case 'monthly':
                $cutoff_timestamp = strtotime('1 month ago');
                break;
            case 'weekly':
            default:
                $cutoff_timestamp = strtotime('1 week ago');
                break;
        }
        $cutoff_date = date('Y-m-d H:i:s', $cutoff_timestamp);

        $subscriber_prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');
        $target_prefs     = SubscriberNotifications_Preferences::decode($notification->target_preferences ?? '');

        if (empty($subscriber_prefs) || empty($target_prefs)) {
            return false;
        }

        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        foreach ($enabled_post_types as $post_type) {
            if (empty($subscriber_prefs[$post_type]) || empty($target_prefs[$post_type])) {
                continue;
            }

            $allowed_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            $tax_query = array('relation' => 'OR');
            $has_tax_clause = false;

            foreach ($allowed_taxonomies as $taxonomy) {
                if (empty($subscriber_prefs[$post_type][$taxonomy]) || empty($target_prefs[$post_type][$taxonomy])) {
                    continue;
                }
                $allowed_ids   = SubscriberNotifications_Term_Resolver::get_allowed_term_ids($post_type, $taxonomy);
                $intersect_ids = array_values(array_intersect(
                    array_map('intval', $subscriber_prefs[$post_type][$taxonomy]),
                    array_map('intval', $target_prefs[$post_type][$taxonomy]),
                    $allowed_ids
                ));
                if (empty($intersect_ids)) {
                    continue;
                }
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $intersect_ids,
                );
                $has_tax_clause = true;
            }

            if (!$has_tax_clause) {
                continue;
            }

            $args = array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'tax_query'      => $tax_query,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_subscriber_notifications_include_in_feed',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_subscriber_notifications_last_notification_date',
                        'value'   => $cutoff_date,
                        'compare' => '>=',
                        // Keep this as a plain string comparison for cross-DB compatibility.
                        // Values are stored as Y-m-d H:i:s, which sorts lexicographically in date order.
                    ),
                ),
            );

            $found = get_posts($args);
            if (!empty($found)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Schedule daily notifications to check every minute
        // Clear existing daily cron to ensure proper rescheduling
        wp_clear_scheduled_hook('subscriber_notifications_send_daily');
        
        // Use every_minute schedule - the method will check if notifications are ready
        if (!wp_next_scheduled('subscriber_notifications_send_daily')) {
            wp_schedule_event(time(), 'every_minute', 'subscriber_notifications_send_daily');
        }
        
        // Schedule weekly notifications to check every minute
        wp_clear_scheduled_hook('subscriber_notifications_send_weekly');
        
        if (!wp_next_scheduled('subscriber_notifications_send_weekly')) {
            wp_schedule_event(time(), 'every_minute', 'subscriber_notifications_send_weekly');
        }
        
        // Schedule monthly notifications to check every minute
        wp_clear_scheduled_hook('subscriber_notifications_send_monthly');
        
        if (!wp_next_scheduled('subscriber_notifications_send_monthly')) {
            wp_schedule_event(time(), 'every_minute', 'subscriber_notifications_send_monthly');
        }
    }
    
    /**
     * Add 'every_minute' schedule if not already added by another plugin
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules
     */
    public function add_every_minute_schedule($schedules) {
        // Check if 'every_minute' already exists (added by another plugin)
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = array(
                'interval' => 60, // 60 seconds = 1 minute
                'display' => __('Every minute', 'subscriber-notifications')
            );
        }
        return $schedules;
    }
    
    /**
     * Get next weekly time
     * 
     * @param int $day_number Day of week (0-6)
     * @param string $time Time (HH:MM)
     * @return int Timestamp
     */
    private function get_next_weekly_time($day_number, $time) {
        $current_time = current_time('timestamp');
        // Use timezone-aware method to get current day
        $timezone = wp_timezone();
        $now = new DateTime('@' . $current_time);
        $now->setTimezone($timezone);
        $current_day = (int)$now->format('w'); // 0 = Sunday, 6 = Saturday
        
        // Calculate days until next occurrence
        $days_until = ($day_number - $current_day + 7) % 7;
        if ($days_until == 0) {
            // Same day - check if time has passed
            $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $time, $timezone);
            $today_time = $today_datetime->getTimestamp();
            if ($today_time <= $current_time) {
                $days_until = 7; // Next week
            }
        }
        
        $timezone = wp_timezone();
        $next_date_datetime = new DateTime('+' . $days_until . ' days', $timezone);
        $next_date_datetime->setTime(
            intval(substr($time, 0, 2)), 
            intval(substr($time, 3, 2))
        );
        return $next_date_datetime->getTimestamp();
    }
    
    /**
     * Get next monthly time
     * 
     * @param int $day Day of month (1-31)
     * @param string $time Time (HH:MM)
     * @return int Timestamp
     */
    private function get_next_monthly_time($day, $time) {
        $current_time = current_time('timestamp');
        // Use timezone-aware method to get current month/year
        $timezone = wp_timezone();
        $now = new DateTime('@' . $current_time);
        $now->setTimezone($timezone);
        $current_month = (int)$now->format('n');
        $current_year = (int)$now->format('Y');
        
        // Get the target day for current month using timezone-aware method
        $target_datetime = new DateTime($current_year . '-' . $current_month . '-01', $timezone);
        $days_in_month = (int)$target_datetime->format('t');
        $target_day = min($day, $days_in_month);
        
        // Use WordPress timezone-aware date functions
        $datetime = new DateTime($current_year . '-' . $current_month . '-' . $target_day . ' ' . $time, $timezone);
        $target_timestamp = $datetime->getTimestamp();
        
        if ($target_timestamp <= $current_time) {
            // This month has passed, go to next month
            $next_month = $current_month + 1;
            $next_year = $current_year;
            if ($next_month > 12) {
                $next_month = 1;
                $next_year++;
            }
            
            // Get the target day for next month using timezone-aware method
            $next_target_datetime = new DateTime($next_year . '-' . $next_month . '-01', $timezone);
            $days_in_next_month = (int)$next_target_datetime->format('t');
            $target_day = min($day, $days_in_next_month);
            $datetime = new DateTime($next_year . '-' . $next_month . '-' . $target_day . ' ' . $time, $timezone);
            $target_timestamp = $datetime->getTimestamp();
        }
        
        return $target_timestamp;
    }
    
    /**
     * Send weekly notifications
     */
    public function send_weekly_notifications() {
        $this->send_frequency_notifications('weekly');
    }
    
    /**
     * Send monthly notifications
     */
    public function send_monthly_notifications() {
        $this->send_frequency_notifications('monthly');
    }
    
    /**
     * Send notifications for specific frequency
     * 
     * @param string $frequency Frequency (weekly, monthly)
     */
    private function send_frequency_notifications($frequency) {
        global $wpdb;
        
        // Get pending notifications for this frequency that are ready to send
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE status = 'pending' 
            AND frequency_target = %s
            AND is_recurring = 1
            AND next_send_date IS NOT NULL 
            AND next_send_date <= %s
            ORDER BY created_date ASC
        ", $frequency, current_time('mysql')));
        
        foreach ($notifications as $notification) {
            $this->send_scheduled_notification($notification->id);
        }
    }
    
    /**
     * Get next daily time
     * 
     * @param string $time Time (HH:MM)
     * @return int Timestamp
     */
    private function get_next_daily_time($time) {
        $current_time = current_time('timestamp');
        $timezone = wp_timezone();
        // Use timezone-aware method to get today's date
        $now = new DateTime('@' . $current_time);
        $now->setTimezone($timezone);
        $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $time, $timezone);
        $today_time = $today_datetime->getTimestamp();
        
        // For daily notifications, always schedule for the next occurrence of the time
        // If time has passed today, schedule for tomorrow
        if ($today_time <= $current_time) {
            $tomorrow_datetime = clone $today_datetime;
            $tomorrow_datetime->modify('+1 day');
            return $tomorrow_datetime->getTimestamp();
        } else {
            // Time hasn't passed today, schedule for today
            return $today_time;
        }
    }
    
    /**
     * Get queue status for debugging
     */
    public function get_queue_status() {
        global $wpdb;
        
        try {
            // Check if table exists
            $table_name = $wpdb->prefix . 'subscriber_notifications_queue';
            // Use prepare() to prevent SQL injection
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            
            if (!$table_exists) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Subscriber Notifications: Queue table does not exist: $table_name");
                }
                return array(
                    'pending' => array(),
                    'recent_sent' => array(),
                    'error' => 'Queue table does not exist'
                );
            }
            
            $pending_notifications = $wpdb->get_results("
                SELECT id, title, frequency_target, created_date, status, is_recurring, next_send_date, recurrence_count
                FROM {$wpdb->prefix}subscriber_notifications_queue 
                WHERE status = 'pending'
                ORDER BY created_date ASC
            ");
            
            $sent_notifications = $wpdb->get_results("
                SELECT id, title, frequency_target, created_date, status 
                FROM {$wpdb->prefix}subscriber_notifications_queue 
                WHERE status = 'sent'
                ORDER BY created_date DESC
                LIMIT 10
            ");
            
            return array(
                'pending' => $pending_notifications ?: array(),
                'recent_sent' => $sent_notifications ?: array()
            );
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Subscriber Notifications: Error in get_queue_status: " . $e->getMessage());
            }
            return array(
                'pending' => array(),
                'recent_sent' => array(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Send daily notifications
     */
    public function send_daily_notifications() {
        $this->send_frequency_notifications('daily');
    }
    
    /**
     * Get WordPress timezone string for date calculations
     * 
     * @return string Timezone string
     */
    private function get_wordpress_timezone() {
        $timezone_string = get_option('timezone_string');
        if (empty($timezone_string)) {
            // Fallback to GMT offset if timezone string is not set
            $gmt_offset = get_option('gmt_offset');
            $timezone_string = 'UTC' . ($gmt_offset >= 0 ? '+' : '') . $gmt_offset;
        }
        return $timezone_string;
    }
    
    /**
     * Calculate next recurring date for a notification
     * 
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @return string Next send date in MySQL format
     */
    private function calculate_next_recurring_date($frequency) {
        $current_time = current_time('timestamp');
        
        switch ($frequency) {
            case 'daily':
                $daily_time = subscriber_notifications_get_option('daily_send_time', '09:00');
                $timezone = wp_timezone();
                $tomorrow = new DateTime('tomorrow', $timezone);
                $tomorrow->setTime(
                    intval(substr($daily_time, 0, 2)), 
                    intval(substr($daily_time, 3, 2))
                );
                return $tomorrow->format('Y-m-d H:i:s');
                
            case 'weekly':
                $weekly_time = subscriber_notifications_get_option('weekly_send_time', '14:00');
                $weekly_day = subscriber_notifications_get_option('weekly_send_day', 'tuesday');
                
                $day_numbers = array(
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                );
                $day_number = $day_numbers[$weekly_day];
                
                // Calculate days until next occurrence (next week)
                // Use timezone-aware method to get current day
                $timezone = wp_timezone();
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_day = (int)$now->format('w');
                $days_until = ($day_number - $current_day + 7) % 7;
                if ($days_until == 0) {
                    $days_until = 7; // Next week
                }
                
                $timezone = wp_timezone();
                $next_date_datetime = new DateTime('+' . $days_until . ' days', $timezone);
                $next_date_datetime->setTime(
                    intval(substr($weekly_time, 0, 2)), 
                    intval(substr($weekly_time, 3, 2))
                );
                return $next_date_datetime->format('Y-m-d H:i:s');
                
            case 'monthly':
                $monthly_day = subscriber_notifications_get_option('monthly_send_day', 15);
                $monthly_time = subscriber_notifications_get_option('monthly_send_time', '14:00');
                
                // Use timezone-aware method to get current month/year
                $timezone = wp_timezone();
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_month = (int)$now->format('n');
                $current_year = (int)$now->format('Y');
                
                // Go to next month
                $next_month = $current_month + 1;
                $next_year = $current_year;
                if ($next_month > 12) {
                    $next_month = 1;
                    $next_year++;
                }
                
                // Get the target day for next month using timezone-aware method
                $next_target_datetime = new DateTime($next_year . '-' . $next_month . '-01', $timezone);
                $days_in_next_month = (int)$next_target_datetime->format('t');
                $target_day = min($monthly_day, $days_in_next_month);
                
                // Use WordPress timezone-aware date functions
                $timezone = wp_timezone();
                $datetime = new DateTime($next_year . '-' . $next_month . '-' . $target_day . ' ' . $monthly_time, $timezone);
                $target_timestamp = $datetime->getTimestamp();
                
                return $datetime->format('Y-m-d H:i:s');
                
            default:
                return null;
        }
    }
}
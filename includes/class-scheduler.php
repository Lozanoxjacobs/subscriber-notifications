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
                $daily_time = get_option('daily_send_time', '09:00');
                return $current_time >= $daily_time;
                
            case 'weekly':
                $weekly_day = get_option('weekly_send_day', 'tuesday');
                $weekly_time = get_option('weekly_send_time', '14:00');
                
                // Convert day name to number (0 = Sunday, 1 = Monday, etc.)
                $day_numbers = array(
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                );
                $target_day = $day_numbers[$weekly_day];
                
                return ($current_day == $target_day) && ($current_time >= $weekly_time);
                
            case 'monthly':
                $monthly_day = get_option('monthly_send_day', 15);
                $monthly_time = get_option('monthly_send_time', '14:00');
                
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
        $email_css = get_option('email_css', '');
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
        $sendgrid = new SubscriberNotifications_SendGrid();
        
        foreach ($subscribers as $subscriber) {
            // Check if subscriber has relevant content
            if (!$this->has_relevant_content($subscriber, $notification->frequency_target, $notification)) {
                continue;
            }
            
            // Prepare content for this subscriber
            $prepared = $this->prepare_notification_content($notification, $subscriber);
            
            // Send email
            if ($sendgrid->send_email($subscriber->email, $prepared['subject'], $prepared['content'], $subscriber->id, $notification->id)) {
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
     * Wrap email content with custom CSS
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->wrap_content_with_css() instead
     * @param string $content Email content
     * @param string $css Custom CSS
     * @param object $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with CSS
     */
    private function wrap_content_with_css($content, $css, $subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->wrap_content_with_css()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->wrap_content_with_css($content, $css, $subscriber);
    }
    
    /**
     * Wrap content with proper email structure
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->wrap_with_email_structure() instead
     * @param string $content Email content
     * @param string $css CSS styles
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with email structure
     */
    private function wrap_with_email_structure($content, $css, $subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->wrap_with_email_structure()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->wrap_with_email_structure($content, $css, $subscriber);
    }
    
    /**
     * Get default CSS
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_default_css() instead
     * @return string Default CSS for emails
     */
    private function get_default_css() {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_default_css()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_default_css();
    }
    
    /**
     * Get global header content
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_global_header_content() instead
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Header HTML
     */
    private function get_global_header_content($subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_global_header_content()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_global_header_content($subscriber);
    }
    
    /**
     * Get global footer content
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_global_footer_content() instead
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Footer HTML
     */
    private function get_global_footer_content($subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_global_footer_content()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_global_footer_content($subscriber);
    }
    
    /**
     * Get target subscribers for notification
     * 
     * @param object $notification Notification object
     * @return array Array of subscriber objects
     */
        private function get_target_subscribers($notification) {
            global $wpdb;
            
            $subscribers_table = $wpdb->prefix . 'subscriber_notifications';
            $where_conditions = array("status = 'active'");
            $where_values = array();
            
            // Build conditions for news OR meeting categories (not both)
            $category_conditions = array();
            
            // Filter by news categories
            if (!empty($notification->news_categories)) {
                $news_categories = explode(',', $notification->news_categories);
                $news_categories = array_map('trim', $news_categories); // Remove whitespace
                foreach ($news_categories as $cat_id) {
                    if (empty($cat_id)) continue;
                    // Use FIND_IN_SET for better compatibility
                    $category_conditions[] = "FIND_IN_SET(%s, news_categories) > 0";
                    $where_values[] = $cat_id;
                }
            }
            
            // Filter by meeting categories
            if (!empty($notification->meeting_categories)) {
                $meeting_categories = explode(',', $notification->meeting_categories);
                $meeting_categories = array_map('trim', $meeting_categories); // Remove whitespace
                foreach ($meeting_categories as $cat_id) {
                    if (empty($cat_id)) continue;
                    // Use FIND_IN_SET for better compatibility
                    $category_conditions[] = "FIND_IN_SET(%s, meeting_categories) > 0";
                    $where_values[] = $cat_id;
                }
            }
            
            // If we have category conditions, add them with OR logic
            if (!empty($category_conditions)) {
                $where_conditions[] = "(" . implode(' OR ', $category_conditions) . ")";
            }
            
            // Filter by frequency
            if (!empty($notification->frequency_target)) {
                $where_conditions[] = "frequency = %s";
                $where_values[] = $notification->frequency_target;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Use prepare() with FIND_IN_SET instead of LIKE patterns
            $sql = $wpdb->prepare("
                SELECT * FROM {$subscribers_table} 
                WHERE {$where_clause}
            ", $where_values);
            
            $subscribers = $wpdb->get_results($sql);
            
            return $subscribers;
        }
    
    /**
     * Check if subscriber has relevant content for this notification
     * 
     * @param object $subscriber Subscriber object
     * @param string $frequency Notification frequency (daily, weekly, monthly)
     * @param object $notification Notification object
     * @return bool True if content exists, false otherwise
     */
    private function has_relevant_content($subscriber, $frequency, $notification) {
        // Determine time period based on frequency and convert to MySQL datetime format
        $cutoff_timestamp = 0;
        switch ($frequency) {
            case 'daily':
                $cutoff_timestamp = strtotime('1 day ago');
                break;
            case 'weekly':
                $cutoff_timestamp = strtotime('1 week ago');
                break;
            case 'monthly':
                $cutoff_timestamp = strtotime('1 month ago');
                break;
            default:
                $cutoff_timestamp = strtotime('1 week ago');
        }
        $cutoff_date = date('Y-m-d H:i:s', $cutoff_timestamp);
        
        // Get intersection of notification target categories and subscriber's categories
        $news_cat_ids = array();
        $meeting_cat_ids = array();
        
        // Check news categories intersection
        if (!empty($notification->news_categories) && !empty($subscriber->news_categories)) {
            $notification_news = explode(',', $notification->news_categories);
            $subscriber_news = explode(',', $subscriber->news_categories);
            $news_cat_ids = array_intersect($notification_news, $subscriber_news);
            $news_cat_ids = array_map('trim', $news_cat_ids); // Remove any whitespace
            $news_cat_ids = array_filter($news_cat_ids); // Remove empty values
        }
        
        // Check meeting categories intersection
        if (!empty($notification->meeting_categories) && !empty($subscriber->meeting_categories)) {
            $notification_meetings = explode(',', $notification->meeting_categories);
            $subscriber_meetings = explode(',', $subscriber->meeting_categories);
            $meeting_cat_ids = array_intersect($notification_meetings, $subscriber_meetings);
            $meeting_cat_ids = array_map('trim', $meeting_cat_ids); // Remove any whitespace
            $meeting_cat_ids = array_filter($meeting_cat_ids); // Remove empty values
        }
        
        // Check for news posts
        $has_news = false;
        if (!empty($news_cat_ids)) {
            $news_args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'category__in' => $news_cat_ids,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_subscriber_notifications_include_in_feed',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_subscriber_notifications_last_notification_date',
                        'value' => $cutoff_date,
                        'compare' => '>=',
                        'type' => 'DATETIME'
                    )
                ),
                'fields' => 'ids'
            );
            $news_posts = get_posts($news_args);
            $has_news = !empty($news_posts);
        }
        
        // Check for events
        $has_events = false;
        if (!empty($meeting_cat_ids) && class_exists('Tribe__Events__Main')) {
            // Filter by notification date (when checkbox was checked), not event start date
            // Suppress TEC's query filters so we can query by post metadata instead of event dates
            $events_args = array(
                'post_type' => 'tribe_events',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'tribe_suppress_query_filters' => true,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'tribe_events_cat',
                        'field' => 'term_id',
                        'terms' => $meeting_cat_ids
                    )
                ),
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_subscriber_notifications_include_in_feed',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_subscriber_notifications_last_notification_date',
                        'value' => $cutoff_date,
                        'compare' => '>=',
                        'type' => 'DATETIME'
                    )
                ),
                'fields' => 'ids'
            );
            $events = get_posts($events_args);
            $has_events = !empty($events);
        }
        
        return $has_news || $has_events;
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
                $daily_time = get_option('daily_send_time', '09:00');
                $timezone = wp_timezone();
                $tomorrow = new DateTime('tomorrow', $timezone);
                $tomorrow->setTime(
                    intval(substr($daily_time, 0, 2)), 
                    intval(substr($daily_time, 3, 2))
                );
                return $tomorrow->format('Y-m-d H:i:s');
                
            case 'weekly':
                $weekly_time = get_option('weekly_send_time', '14:00');
                $weekly_day = get_option('weekly_send_day', 'tuesday');
                
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
                $monthly_day = get_option('monthly_send_day', 15);
                $monthly_time = get_option('monthly_send_time', '14:00');
                
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
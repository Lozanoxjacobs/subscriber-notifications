<?php
/**
 * Database management class
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for managing subscriber data
 */
class SubscriberNotifications_Database {
    
    /**
     * WordPress database instance
     */
    private $wpdb;
    
    /**
     * Table names
     */
    private $subscribers_table;
    private $logs_table;
    private $notifications_table;
    
    /**
     * Constructor
     * 
     * @param wpdb $wpdb WordPress database instance
     */
    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
        
        if (!$this->wpdb) {
            throw new Exception('WordPress database not available');
        }
        
        $this->subscribers_table = $this->wpdb->prefix . 'subscriber_notifications';
        $this->logs_table = $this->wpdb->prefix . 'subscriber_notification_logs';
        $this->notifications_table = $this->wpdb->prefix . 'subscriber_notifications_queue';
    }
    
    /**
     * Create database tables
     * 
     * @return bool True on success, false on failure
     */
    public function create_tables() {
        // Ensure WordPress is ready
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Subscribers table
        $subscribers_sql = "CREATE TABLE {$this->subscribers_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            news_categories text,
            meeting_categories text,
            frequency enum('daily','weekly','monthly') NOT NULL,
            status enum('active','inactive') DEFAULT 'active',
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            last_notified datetime,
            management_token varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY frequency (frequency),
            KEY management_token (management_token)
        ) $charset_collate;";
        
        // Logs table
        $logs_sql = "CREATE TABLE {$this->logs_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            subscriber_id int(11) NOT NULL,
            notification_id int(11),
            email_type varchar(50) NOT NULL,
            sent_date datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('sent','failed','pending') DEFAULT 'pending',
            error_message text,
            open_count int(11) DEFAULT 0,
            click_count int(11) DEFAULT 0,
            last_opened datetime,
            last_clicked datetime,
            unsubscribe_reason text,
            tracking_id varchar(255),
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY notification_id (notification_id),
            KEY status (status),
            KEY tracking_id (tracking_id)
        ) $charset_collate;";
        
        // Notifications queue table
        $notifications_sql = "CREATE TABLE {$this->notifications_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            news_categories text,
            meeting_categories text,
            frequency_target varchar(50),
            status enum('pending','sent','cancelled') DEFAULT 'pending',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            sent_date datetime,
            created_by int(11),
            is_recurring tinyint(1) DEFAULT 0,
            next_send_date datetime,
            last_sent_date datetime,
            recurrence_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by),
            KEY is_recurring (is_recurring),
            KEY next_send_date (next_send_date)
        ) $charset_collate;";
        
        // Create tables
        $result1 = dbDelta($subscribers_sql);
        $result2 = dbDelta($logs_sql);
        $result3 = dbDelta($notifications_sql);
        
        // Check if subject column exists, if not add it
        $this->add_subject_column_if_missing();
        
        // Check if recurring columns exist, if not add them
        $this->add_recurring_columns_if_missing();
        
        // Update database version
        update_option('subscriber_notifications_db_version', SUBSCRIBER_NOTIFICATIONS_VERSION);
        
        // Return success if at least one table was created
        return !empty($result1) || !empty($result2) || !empty($result3);
    }
    
    /**
     * Add subject column if it doesn't exist
     */
    private function add_subject_column_if_missing() {
        global $wpdb;
        
        // Validate table name - it comes from $wpdb->prefix so it's safe, but we validate format
        $table_name = $this->notifications_table;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', $table_name))) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Invalid table name format');
            }
            return;
        }
        
        // Check if subject column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'subject'
        ));
        
        if (empty($column_exists)) {
            // Add subject column - table name is validated above, column name is hardcoded so safe
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN subject varchar(255) NOT NULL DEFAULT '' AFTER title");
        }
    }
    
    /**
     * Add recurring notification columns if they don't exist
     */
    private function add_recurring_columns_if_missing() {
        global $wpdb;
        
        // Validate table name - it comes from $wpdb->prefix so it's safe, but we validate format
        $table_name = $this->notifications_table;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', $table_name))) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Invalid table name format');
            }
            return;
        }
        
        // Whitelist of allowed column names to prevent injection
        $allowed_columns = array(
            'is_recurring' => 'tinyint(1) DEFAULT 0',
            'next_send_date' => 'datetime',
            'last_sent_date' => 'datetime', 
            'recurrence_count' => 'int(11) DEFAULT 0'
        );
        
        foreach ($allowed_columns as $column => $definition) {
            // Validate column name format
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                continue;
            }
            
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $column
            ));
            
            if (empty($column_exists)) {
                // Table and column names are validated above, definition is from whitelist
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$column} {$definition}");
            }
        }
    }
    
    /**
     * Run database migrations
     * This can be called manually to update existing installations
     */
    public function run_migrations() {
        // IMPORTANT: Token migration must run FIRST before create_tables() to avoid duplicate columns
        $this->migrate_unsubscribe_token_to_management_token();
        
        // Ensure all subscribers have management tokens
        $this->migrate_generate_missing_tokens();
        
        // Auto-populate global footer if empty
        $this->migrate_auto_populate_global_footer();
        
        // Add subject column if it doesn't exist
        $this->add_subject_column_if_missing();
        
        // Add recurring columns if they don't exist
        $this->add_recurring_columns_if_missing();
        
        // Convert existing weekly/monthly notifications to recurring if they haven't been sent
        $this->convert_existing_notifications_to_recurring();
        
        // Run other existing migrations
        $this->migrate_frequency_enum();
        $this->migrate_feed_inclusion_meta();
        
        // Remove phone column if it exists
        $this->remove_phone_column_if_exists();
    }
    
    /**
     * Auto-populate global footer if empty
     */
    private function migrate_auto_populate_global_footer() {
        $global_footer = get_option('global_footer', '');
        
        if (empty($global_footer)) {
            $default_footer = '[site_title] | [manage_preferences_link]';
            update_option('global_footer', $default_footer);
        }
    }
    
    /**
     * Migrate unsubscribe_token column to management_token
     * This migration must run BEFORE create_tables() to avoid duplicate columns
     * 
     * @return bool True on success, false on failure
     */
    private function migrate_unsubscribe_token_to_management_token() {
        // Check if table exists first (for fresh installs)
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->subscribers_table
        ));
        
        if (!$table_exists) {
            // Table doesn't exist yet - this is a fresh install, skip migration
            return true;
        }
        
        // Get current columns
        $columns = $this->wpdb->get_col("DESCRIBE {$this->subscribers_table}");
        
        // Check if management_token already exists
        if (in_array('management_token', $columns)) {
            // Already has management_token, check if we need to drop unsubscribe_token
            if (in_array('unsubscribe_token', $columns)) {
                // Both exist - drop the old one
                $sql = "ALTER TABLE {$this->subscribers_table} DROP COLUMN unsubscribe_token";
                $result = $this->wpdb->query($sql);
                
                if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Subscriber Notifications: Failed to drop unsubscribe_token column: ' . $this->wpdb->last_error);
                }
            }
            return true;
        }
        
        // Check if unsubscribe_token column exists (old column name)
        if (in_array('unsubscribe_token', $columns)) {
            // Rename column and index
            // First, drop the old index if it exists
            $index_exists = $this->wpdb->get_results($this->wpdb->prepare(
                "SHOW INDEX FROM {$this->subscribers_table} WHERE Key_name = %s",
                'unsubscribe_token'
            ));
            
            if (!empty($index_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->subscribers_table} DROP INDEX unsubscribe_token");
            }
            
            // Rename the column
            $sql = "ALTER TABLE {$this->subscribers_table} CHANGE COLUMN unsubscribe_token management_token varchar(255)";
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Subscriber Notifications: Failed to rename unsubscribe_token to management_token: ' . $this->wpdb->last_error);
                }
                return false;
            }
            
            // Add the new index
            $this->wpdb->query("ALTER TABLE {$this->subscribers_table} ADD INDEX management_token (management_token)");
            
            return true;
        }
        
        // Neither column exists - add management_token (for tables missing the column)
        $sql = "ALTER TABLE {$this->subscribers_table} ADD COLUMN management_token varchar(255) NULL";
        $result = $this->wpdb->query($sql);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Failed to add management_token column: ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        // Add index
        $this->wpdb->query("ALTER TABLE {$this->subscribers_table} ADD INDEX management_token (management_token)");
        
        return true;
    }
    
    /**
     * Generate management tokens for subscribers that don't have them
     * 
     * @return bool True on success, false on failure
     */
    private function migrate_generate_missing_tokens() {
        // Check if table exists first (for fresh installs)
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->subscribers_table
        ));
        
        if (!$table_exists) {
            // Table doesn't exist yet - this is a fresh install, skip migration
            return true;
        }
        
        // Get all subscribers without tokens
        $subscribers_without_tokens = $this->wpdb->get_results(
            "SELECT id FROM {$this->subscribers_table} WHERE management_token IS NULL OR management_token = ''"
        );
        
        if (empty($subscribers_without_tokens)) {
            return true;
        }
        
        // Generate tokens for each subscriber
        foreach ($subscribers_without_tokens as $subscriber) {
            $new_token = wp_generate_password(32, false);
            $this->wpdb->update(
                $this->subscribers_table,
                array('management_token' => $new_token),
                array('id' => $subscriber->id),
                array('%s'),
                array('%d')
            );
        }
        
        return true;
    }
    
    /**
     * Convert existing notifications to recurring format
     */
    private function convert_existing_notifications_to_recurring() {
        global $wpdb;
        
        // Get notifications that are pending and have frequency_target but are not recurring
        $notifications = $wpdb->get_results("
            SELECT id, frequency_target, created_date 
            FROM {$this->notifications_table} 
            WHERE status = 'pending' 
            AND frequency_target IN ('daily', 'weekly', 'monthly')
            AND (is_recurring = 0 OR is_recurring IS NULL)
        ");
        
        foreach ($notifications as $notification) {
            // Calculate next send date
            $next_send_date = $this->calculate_next_send_date_for_existing($notification->frequency_target, $notification->created_date);
            
            // Update the notification to be recurring
            $wpdb->update(
                $this->notifications_table,
                array(
                    'is_recurring' => 1,
                    'next_send_date' => $next_send_date,
                    'recurrence_count' => 0
                ),
                array('id' => $notification->id),
                array('%d', '%s', '%d'),
                array('%d')
            );
        }
    }
    
    /**
     * Calculate next send date for existing notifications
     */
    private function calculate_next_send_date_for_existing($frequency, $created_date) {
        $current_time = current_time('timestamp');
        $timezone = wp_timezone();
        
        switch ($frequency) {
            case 'daily':
                $daily_time = get_option('daily_send_time', '09:00');
                // Use timezone-aware method to get today's date
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $daily_time, $timezone);
                $today_time = $today_datetime->getTimestamp();
                
                if ($today_time <= $current_time) {
                    $tomorrow_datetime = clone $today_datetime;
                    $tomorrow_datetime->modify('+1 day');
                    return $tomorrow_datetime->format('Y-m-d H:i:s');
                } else {
                    return $today_datetime->format('Y-m-d H:i:s');
                }
                
            case 'weekly':
                $weekly_time = get_option('weekly_send_time', '14:00');
                $weekly_day = get_option('weekly_send_day', 'tuesday');
                
                $day_numbers = array(
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                );
                $day_number = $day_numbers[$weekly_day];
                
                // Use timezone-aware method to get current day
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_day = (int)$now->format('w');
                $days_until = ($day_number - $current_day + 7) % 7;
                
                if ($days_until == 0) {
                    $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $weekly_time, $timezone);
                    $today_time = $today_datetime->getTimestamp();
                    if ($today_time <= $current_time) {
                        $days_until = 7;
                    }
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
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_month = (int)$now->format('n');
                $current_year = (int)$now->format('Y');
                
                // Get the target day for current month using timezone-aware method
                $target_datetime = new DateTime($current_year . '-' . $current_month . '-01', $timezone);
                $days_in_month = (int)$target_datetime->format('t');
                $target_day = min($monthly_day, $days_in_month);
                
                // Use WordPress timezone-aware date functions
                $datetime = new DateTime($current_year . '-' . $current_month . '-' . $target_day . ' ' . $monthly_time, $timezone);
                $target_timestamp = $datetime->getTimestamp();
                
                if ($target_timestamp <= $current_time) {
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
                    $datetime = new DateTime($next_year . '-' . $next_month . '-' . $target_day . ' ' . $monthly_time, $timezone);
                    $target_timestamp = $datetime->getTimestamp();
                }
                
                return $datetime->format('Y-m-d H:i:s');
                
            default:
                return null;
        }
    }
    
    /**
     * Migrate frequency enum to include daily and remove as_available
     */
    private function migrate_frequency_enum() {
        // Check current enum values
        $current_enum = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->subscribers_table} LIKE 'frequency'");
        
        // Check if we need to migrate (either has as_available or missing daily)
        if (strpos($current_enum, 'as_available') !== false || strpos($current_enum, 'daily') === false) {
            // Update any existing as_available subscribers to daily
            $updated = $this->wpdb->update(
                $this->subscribers_table,
                array('frequency' => 'daily'),
                array('frequency' => 'as_available'),
                array('%s'),
                array('%s')
            );
            
            // Alter the enum to remove as_available and add daily
            $sql = "ALTER TABLE {$this->subscribers_table} MODIFY COLUMN frequency enum('daily','weekly','monthly') NOT NULL";
            $result = $this->wpdb->query($sql);
            
            if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Subscriber Notifications: Failed to update frequency enum. Error: " . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Migrate feed inclusion meta for existing posts
     */
    private function migrate_feed_inclusion_meta() {
        $migration_version = get_option('subscriber_notifications_feed_migration_version', '0');
        
        if (version_compare($migration_version, '1.0.0', '<')) {
            // Get all published posts without the meta field
            $published_posts = get_posts(array(
                'post_status' => 'publish',
                'post_type' => array('post', 'tribe_events'),
                'meta_query' => array(
                    array(
                        'key' => '_subscriber_notifications_include_in_feed',
                        'compare' => 'NOT EXISTS'
                    )
                ),
                'numberposts' => -1
            ));
            
            foreach ($published_posts as $post) {
                // Default existing posts to NOT included in feeds
                update_post_meta($post->ID, '_subscriber_notifications_include_in_feed', 0);
            }
            
            update_option('subscriber_notifications_feed_migration_version', '1.0.0');
        }
    }
    
    /**
     * Remove phone column if it exists
     */
    private function remove_phone_column_if_exists() {
        $migration_version = get_option('subscriber_notifications_phone_removal_version', '0');
        
        if (version_compare($migration_version, '1.0.0', '<')) {
            // Check if phone column exists
            $columns = $this->wpdb->get_col("DESCRIBE {$this->subscribers_table}");
            
            if (in_array('phone', $columns)) {
                // Remove phone column
                $sql = "ALTER TABLE {$this->subscribers_table} DROP COLUMN phone";
                $result = $this->wpdb->query($sql);
                
                if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Subscriber Notifications: Failed to remove phone column: ' . $this->wpdb->last_error);
                }
            }
            
            update_option('subscriber_notifications_phone_removal_version', '1.0.0');
        }
    }
    
    /**
     * Get subscribers with pagination and filtering
     * 
     * @param array $args Query arguments
     * @return array Array of subscriber objects
     */
    public function get_subscribers(array $args = array()): array {
        $defaults = array(
            'status' => '', // Changed to empty to show all by default
            'limit' => 20,
            'offset' => 0,
            'search' => '',
            'orderby' => 'date_added',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(name LIKE %s OR email LIKE %s)";
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = $this->wpdb->prepare("
            SELECT * FROM {$this->subscribers_table} 
            WHERE {$where_clause} 
            ORDER BY {$args['orderby']} {$args['order']} 
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get single subscriber by ID
     * 
     * @param int $id Subscriber ID
     * @return object|null Subscriber object or null
     */
    public function get_subscriber(int $id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribers_table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get subscriber by email
     * 
     * @param string $email Email address
     * @return object|null Subscriber object or null
     */
    public function get_subscriber_by_email(string $email) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribers_table} WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Get subscriber by management token
     * 
     * @param string $token Management token
     * @return object|null Subscriber object or null
     */
    public function get_subscriber_by_management_token(string $token) {
        $token = trim($token);
        
        if (empty($token)) {
            return null;
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribers_table} WHERE management_token = %s",
            $token
        ));
    }
    
    /**
     * Add new subscriber
     * 
     * @param array $data Subscriber data
     * @return int|false Subscriber ID on success, false on failure
     */
    public function add_subscriber(array $data) {
        $defaults = array(
            'name' => '',
            'email' => '',
            'news_categories' => '',
            'meeting_categories' => '',
            'frequency' => 'daily',
            'status' => 'active',
            'management_token' => wp_generate_password(32, false)
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data['name'] = sanitize_text_field($data['name']);
        $data['email'] = sanitize_email($data['email']);
        $data['news_categories'] = sanitize_text_field($data['news_categories']);
        $data['meeting_categories'] = sanitize_text_field($data['meeting_categories']);
        $data['frequency'] = sanitize_text_field($data['frequency']);
        $data['status'] = sanitize_text_field($data['status']);
        
        $result = $this->wpdb->insert($this->subscribers_table, $data);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Database insert failed. Error: ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update subscriber
     * 
     * @param int $id Subscriber ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update_subscriber(int $id, array $data): bool {
        // Sanitize data appropriately based on field type
        $sanitized_data = array();
        $format = array();
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'name':
                case 'email':
                case 'news_categories':
                case 'meeting_categories':
                case 'frequency':
                case 'status':
                case 'management_token':
                    $sanitized_data[$key] = sanitize_text_field($value);
                    $format[] = '%s';
                    break;
                case 'date_verified':
                case 'date_added':
                case 'last_notified':
                    $sanitized_data[$key] = sanitize_text_field($value); // Keep as string for datetime
                    $format[] = '%s';
                    break;
                default:
                    $sanitized_data[$key] = sanitize_text_field($value);
                    $format[] = '%s';
                    break;
            }
        }
        
        $result = $this->wpdb->update(
            $this->subscribers_table,
            $sanitized_data,
            array('id' => $id),
            $format,
            array('%d')
        );
        
        if ($result === false && !empty($this->wpdb->last_error)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Database update failed. Error: ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        return $result;
    }
    
    /**
     * Delete subscriber
     * 
     * @param int $id Subscriber ID
     * @return bool True on success, false on failure
     */
    public function delete_subscriber(int $id): bool {
        return $this->wpdb->delete(
            $this->subscribers_table,
            array('id' => $id),
            array('%d')
        );
    }
    
    
    /**
     * Log email
     * 
     * @param array $data Log data
     * @return int|false Log ID on success, false on failure
     */
    public function log_email(array $data) {
        $defaults = array(
            'subscriber_id' => 0,
            'notification_id' => 0,
            'email_type' => '',
            'status' => 'pending',
            'tracking_id' => wp_generate_password(32, false)
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $this->wpdb->insert($this->logs_table, $data);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG && !empty($this->wpdb->last_error)) {
                error_log('Subscriber Notifications: Database log insert failed. Error: ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update log
     * 
     * @param int $id Log ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update_log(int $id, array $data): bool {
        $result = $this->wpdb->update(
            $this->logs_table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false && !empty($this->wpdb->last_error)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Database log update failed. Error: ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Get logs
     * 
     * @param array $args Query arguments
     * @return array Array of log objects
     */
    public function get_logs(array $args = array()): array {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'subscriber_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['subscriber_id'])) {
            $where_conditions[] = "l.subscriber_id = %d";
            $where_values[] = $args['subscriber_id'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = "l.status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = "l.sent_date >= %s";
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = "l.sent_date <= %s";
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build base SQL query
        $sql = "
            SELECT l.*, s.name, s.email 
            FROM {$this->logs_table} l 
            LEFT JOIN {$this->subscribers_table} s ON l.subscriber_id = s.id 
            WHERE {$where_clause} 
            ORDER BY l.sent_date DESC
        ";
        
        // Add limit and offset if limit is set and greater than 0
        if (!empty($args['limit']) && $args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $where_values[] = $args['limit'];
            $where_values[] = $args['offset'];
            $sql = $this->wpdb->prepare($sql, $where_values);
        } else {
            // No limit - prepare without LIMIT clause
            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get logs count
     * 
     * @param array $args Query arguments
     * @return int Logs count
     */
    public function get_logs_count(array $args = array()): int {
        $defaults = array(
            'subscriber_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['subscriber_id'])) {
            $where_conditions[] = "subscriber_id = %d";
            $where_values[] = $args['subscriber_id'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = "sent_date >= %s";
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = "sent_date <= %s";
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (empty($where_values)) {
            $sql = "SELECT COUNT(*) FROM {$this->logs_table} WHERE {$where_clause}";
            return $this->wpdb->get_var($sql);
        }
        
        $sql = $this->wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$this->logs_table} 
            WHERE {$where_clause}
        ", $where_values);
        
        return $this->wpdb->get_var($sql);
    }
    
    /**
     * Get subscriber count
     * 
     * @param array $args Query arguments
     * @return int Subscriber count
     */
    public function get_subscriber_count(array $args = array()): int {
        $defaults = array(
            'status' => '',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(name LIKE %s OR email LIKE %s)";
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (empty($where_values)) {
            $sql = "SELECT COUNT(*) FROM {$this->subscribers_table} WHERE {$where_clause}";
            return $this->wpdb->get_var($sql);
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subscribers_table} WHERE {$where_clause}",
            $where_values
        );
        
        return $this->wpdb->get_var($sql);
    }
    
    /**
     * Get analytics data
     * 
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return object Analytics data
     */
    public function get_analytics_data(string $date_from = '', string $date_to = '') {
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($date_from)) {
            $where_conditions[] = "sent_date >= %s";
            $where_values[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "sent_date <= %s";
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = $this->wpdb->prepare("
            SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
                SUM(open_count) as total_opens,
                SUM(click_count) as total_clicks,
                SUM(CASE WHEN open_count > 0 THEN 1 ELSE 0 END) as unique_opens,
                SUM(CASE WHEN click_count > 0 THEN 1 ELSE 0 END) as unique_clicks
            FROM {$this->logs_table} 
            WHERE {$where_clause}
        ", $where_values);
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Update subscriber last notified time
     * 
     * @param int $subscriber_id Subscriber ID
     * @return bool True on success, false on failure
     */
    public function update_subscriber_last_notified(int $subscriber_id): bool {
        return $this->wpdb->update(
            $this->subscribers_table,
            array('last_notified' => current_time('mysql')),
            array('id' => $subscriber_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Migrate database to remove verification system
     * 
     * @return array Migration results
     */
    public function migrate_remove_verification() {
        $results = array(
            'success' => true,
            'messages' => array(),
            'errors' => array()
        );
        
        // Check if verification columns exist
        $columns = $this->wpdb->get_col("DESCRIBE {$this->subscribers_table}");
        
        // Remove verification_token column if it exists
        if (in_array('verification_token', $columns)) {
            $sql = "ALTER TABLE {$this->subscribers_table} DROP COLUMN verification_token";
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                $results['success'] = false;
                $results['errors'][] = 'Failed to remove verification_token column: ' . $this->wpdb->last_error;
            } else {
                $results['messages'][] = 'Removed verification_token column';
            }
        }
        
        // Remove date_verified column if it exists
        if (in_array('date_verified', $columns)) {
            $sql = "ALTER TABLE {$this->subscribers_table} DROP COLUMN date_verified";
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                $results['success'] = false;
                $results['errors'][] = 'Failed to remove date_verified column: ' . $this->wpdb->last_error;
            } else {
                $results['messages'][] = 'Removed date_verified column';
            }
        }
        
        // Update any pending subscribers to active
        $updated = $this->wpdb->update(
            $this->subscribers_table,
            array('status' => 'active'),
            array('status' => 'pending'),
            array('%s'),
            array('%s')
        );
        
        if ($updated !== false) {
            $results['messages'][] = "Updated {$updated} pending subscribers to active status";
        } else {
            $results['errors'][] = 'Failed to update pending subscribers: ' . $this->wpdb->last_error;
        }
        
        return $results;
    }
}
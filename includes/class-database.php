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
    private $send_queue_table;
    
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
        $this->send_queue_table = $this->wpdb->prefix . 'subscriber_notifications_send_queue';
    }

    /**
     * Get the per-recipient send queue table name.
     *
     * @return string
     */
    public function get_send_queue_table() {
        return $this->send_queue_table;
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
            user_id bigint(20) unsigned DEFAULT NULL,
            subscription_preferences longtext,
            frequency enum('daily','weekly','monthly') NOT NULL,
            status enum('active','inactive') DEFAULT 'active',
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            last_notified datetime,
            management_token varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY user_id (user_id),
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
            target_preferences longtext,
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

        // Per-recipient send queue. One row per (notification, subscriber) pair.
        // The UNIQUE KEY makes enqueue idempotent so an interrupted enqueue can
        // be retried safely with INSERT IGNORE.
        $send_queue_sql = "CREATE TABLE {$this->send_queue_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id int(11) NOT NULL,
            subscriber_id int(11) NOT NULL,
            status enum('pending','sent','failed','skipped') DEFAULT 'pending',
            attempts tinyint(3) unsigned DEFAULT 0,
            last_error text,
            enqueued_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY notification_subscriber (notification_id, subscriber_id),
            KEY status (status),
            KEY notification_status (notification_id, status)
        ) $charset_collate;";
        
        // Create tables
        $result1 = dbDelta($subscribers_sql);
        $result2 = dbDelta($logs_sql);
        $result3 = dbDelta($notifications_sql);
        $result4 = dbDelta($send_queue_sql);
        
        // Check if subject column exists, if not add it
        $this->add_subject_column_if_missing();
        
        // Check if recurring columns exist, if not add them
        $this->add_recurring_columns_if_missing();
        
        // Update database version
        update_option('subscriber_notifications_db_version', SUBSCRIBER_NOTIFICATIONS_VERSION);
        
        // Return success if at least one table was created
        return !empty($result1) || !empty($result2) || !empty($result3) || !empty($result4);
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
     * Run database migrations.
     *
     * v3 is greenfield. Most 2.x migration paths are unsafe to re-run against the
     * new schema (e.g. they reference removed columns). They are intentionally
     * skipped here. The retained calls are safe on both fresh v3 installs and
     * partially-wiped dev installs.
     */
    public function run_migrations() {
        // Token columns are needed by both v2 and v3; safe to keep.
        $this->migrate_unsubscribe_token_to_management_token();
        $this->migrate_generate_missing_tokens();

        // Optional cosmetic default; safe.
        $this->migrate_auto_populate_global_footer();

        // Defensive guards in case dbDelta hasn't run yet.
        $this->add_subject_column_if_missing();
        $this->add_recurring_columns_if_missing();
        $this->add_user_id_column_if_missing();
    }
    
    /**
     * Add user_id column and unique index on subscribers table if missing.
     */
    private function add_user_id_column_if_missing() {
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $this->subscribers_table
        ));

        if (!$table_exists) {
            return;
        }

        $column_exists = $this->wpdb->get_results($this->wpdb->prepare(
            "SHOW COLUMNS FROM {$this->subscribers_table} LIKE %s",
            'user_id'
        ));

        if (empty($column_exists)) {
            $this->wpdb->query(
                "ALTER TABLE {$this->subscribers_table} ADD COLUMN user_id bigint(20) unsigned DEFAULT NULL AFTER email"
            );
        }

        $index_exists = $this->wpdb->get_results($this->wpdb->prepare(
            "SHOW INDEX FROM {$this->subscribers_table} WHERE Key_name = %s",
            'user_id'
        ));

        if (empty($index_exists)) {
            $this->wpdb->query(
                "ALTER TABLE {$this->subscribers_table} ADD UNIQUE KEY user_id (user_id)"
            );
        }
    }
    
    /**
     * Auto-populate global footer if empty
     */
    private function migrate_auto_populate_global_footer() {
        $global_footer = subscriber_notifications_get_option('global_footer', '');
        
        if (empty($global_footer)) {
            $default_footer = '[site_title] | [manage_preferences_link]';
            subscriber_notifications_update_option('global_footer', $default_footer);
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
     * Build WHERE clause fragment for subscriber list search.
     *
     * @param string $search Search string from admin UI.
     * @return array{0: string, 1: array<int, mixed>} SQL fragment (with leading AND) and values.
     */
    private function get_subscriber_search_where(string $search): array {
        $search = trim($search);
        if ('' === $search) {
            return array('', array());
        }

        $like_term = '%' . $this->wpdb->esc_like($search) . '%';

        if (ctype_digit($search)) {
            return array(
                ' AND (name LIKE %s OR email LIKE %s OR user_id = %d)',
                array($like_term, $like_term, absint($search)),
            );
        }

        return array(
            ' AND (name LIKE %s OR email LIKE %s)',
            array($like_term, $like_term),
        );
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

        $orderby_whitelist = array(
            'date_added' => 'date_added',
            'email'      => 'email',
            'name'       => 'name',
            'status'     => 'status',
            'id'         => 'id',
        );
        $orderby_sql = isset($orderby_whitelist[ $args['orderby'] ]) ? $orderby_whitelist[ $args['orderby'] ] : 'date_added';
        $order_sql   = 'ASC' === strtoupper((string) $args['order']) ? 'ASC' : 'DESC';
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        list($search_sql, $search_values) = $this->get_subscriber_search_where((string) $args['search']);
        if ('' !== $search_sql) {
            $where_conditions[] = ltrim($search_sql, ' AND ');
            $where_values     = array_merge($where_values, $search_values);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = $this->wpdb->prepare(
            "
            SELECT * FROM {$this->subscribers_table} 
            WHERE {$where_clause} 
            ORDER BY {$orderby_sql} {$order_sql} 
            LIMIT %d OFFSET %d
            ",
            array_merge($where_values, array($args['limit'], $args['offset']))
        );
        
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
     * Get subscriber by linked WordPress user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return object|null Subscriber object or null.
     */
    public function get_subscriber_by_user_id(int $user_id) {
        $user_id = absint($user_id);
        if ($user_id < 1) {
            return null;
        }

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribers_table} WHERE user_id = %d",
            $user_id
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
     * @param array $data Subscriber data. Accepts `subscription_preferences` as either an
     *                    array (encoded to JSON here) or a JSON string.
     * @return int|false Subscriber ID on success, false on failure
     */
    public function add_subscriber(array $data) {
        $defaults = array(
            'name'                    => '',
            'email'                   => '',
            'subscription_preferences' => '{}',
            'frequency'               => 'daily',
            'status'                  => 'active',
            'management_token'        => wp_generate_password(32, false),
        );

        $data = wp_parse_args($data, $defaults);

        $data['name']                    = sanitize_text_field($data['name']);
        $data['email']                   = sanitize_email($data['email']);
        $data['subscription_preferences'] = $this->encode_preferences($data['subscription_preferences']);
        $data['frequency']               = sanitize_text_field($data['frequency']);
        $data['status']                  = sanitize_text_field($data['status']);

        if (isset($data['user_id'])) {
            $data['user_id'] = absint($data['user_id']);
            if ($data['user_id'] < 1) {
                unset($data['user_id']);
            }
        }

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
     * @param array $data Data to update. `subscription_preferences` may be an array or JSON.
     * @return bool True on success, false on failure
     */
    public function update_subscriber(int $id, array $data): bool {
        $sanitized_data = array();
        $format = array();

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'email':
                    $sanitized_data[$key] = sanitize_email((string) $value);
                    $format[] = '%s';
                    break;
                case 'user_id':
                    $user_id = absint($value);
                    $sanitized_data[$key] = $user_id > 0 ? $user_id : null;
                    $format[] = '%d';
                    break;
                case 'subscription_preferences':
                    $sanitized_data[$key] = $this->encode_preferences($value);
                    $format[] = '%s';
                    break;
                case 'name':
                case 'frequency':
                case 'status':
                case 'management_token':
                    $sanitized_data[$key] = sanitize_text_field($value);
                    $format[] = '%s';
                    break;
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
     * Encode a preferences value for storage. Accepts arrays or JSON strings.
     *
     * @param mixed $value Preferences as array or JSON string.
     * @return string JSON-encoded preferences.
     */
    private function encode_preferences($value): string {
        if (class_exists('SubscriberNotifications_Preferences')) {
            if (is_array($value)) {
                return SubscriberNotifications_Preferences::encode($value);
            }
            $decoded = SubscriberNotifications_Preferences::decode((string) $value);
            return SubscriberNotifications_Preferences::encode($decoded);
        }
        if (is_array($value)) {
            return wp_json_encode($value);
        }
        return (string) $value;
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
        
        list($search_sql, $search_values) = $this->get_subscriber_search_where((string) $args['search']);
        if ('' !== $search_sql) {
            $where_conditions[] = ltrim($search_sql, ' AND ');
            $where_values     = array_merge($where_values, $search_values);
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
    
}
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
            date_added datetime,
            last_notified datetime,
            management_token varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY frequency (frequency),
            UNIQUE KEY management_token (management_token)
        ) $charset_collate;";
        
        // Logs table
        $logs_sql = "CREATE TABLE {$this->logs_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            subscriber_id int(11) NOT NULL,
            notification_id int(11),
            email_type varchar(50) NOT NULL,
            sent_date datetime,
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
            KEY tracking_id (tracking_id),
            KEY sent_date (sent_date)
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
            created_date datetime,
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
            enqueued_at datetime,
            sent_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY notification_subscriber (notification_id, subscriber_id),
            KEY status (status),
            KEY notification_status (notification_id, status)
        ) $charset_collate;";
        
        dbDelta($subscribers_sql);
        dbDelta($logs_sql);
        dbDelta($notifications_sql);
        dbDelta($send_queue_sql);
        
        update_option('subscriber_notifications_db_version', SUBSCRIBER_NOTIFICATIONS_DB_VERSION);
        
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
            'status'  => '', // Changed to empty to show all by default
            'wp_user' => '',
            'limit'   => 20,
            'offset'  => 0,
            'search'  => '',
            'orderby' => 'date_added',
            'order'   => 'DESC',
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

        if ('linked' === $args['wp_user']) {
            $where_conditions[] = 'user_id IS NOT NULL AND user_id > 0';
        } elseif ('none' === $args['wp_user']) {
            $where_conditions[] = '(user_id IS NULL OR user_id = 0)';
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

        if (empty($data['date_added'])) {
            $data['date_added'] = current_time('mysql');
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
        $id = absint($id);
        if ($id < 1) {
            return false;
        }

        $this->wpdb->delete(
            $this->logs_table,
            array('subscriber_id' => $id),
            array('%d')
        );

        $this->wpdb->delete(
            $this->send_queue_table,
            array('subscriber_id' => $id),
            array('%d')
        );

        $deleted = $this->wpdb->delete(
            $this->subscribers_table,
            array('id' => $id),
            array('%d')
        );

        return $deleted !== false && $deleted > 0;
    }

    /**
     * Remove terminal send-queue rows for a notification.
     *
     * @param int          $notification_id Notification ID.
     * @param array<int,string> $statuses   Statuses to delete.
     * @return int Rows deleted.
     */
    public function purge_send_queue_for_notification(int $notification_id, array $statuses = array('sent', 'skipped')): int {
        $notification_id = absint($notification_id);
        if ($notification_id < 1) {
            return 0;
        }

        $allowed = array('pending', 'sent', 'failed', 'skipped');
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (empty($statuses)) {
            return 0;
        }

        $total = 0;
        foreach ($statuses as $status) {
            $deleted = $this->wpdb->delete(
                $this->send_queue_table,
                array(
                    'notification_id' => $notification_id,
                    'status'          => $status,
                ),
                array('%d', '%s')
            );
            if ($deleted !== false) {
                $total += (int) $deleted;
            }
        }

        return $total;
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

        if (empty($data['sent_date'])) {
            $data['sent_date'] = current_time('mysql');
        }
        
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
     * Validate an admin log filter date (HTML date input, Y-m-d).
     *
     * @param string $date Raw date string.
     * @return string|null Normalized Y-m-d or null when invalid.
     */
    private function parse_log_filter_date(string $date): ?string {
        $date = sanitize_text_field($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date, wp_timezone());
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }

    /**
     * Convert site-calendar log filter dates to sent_date bounds (site timezone).
     *
     * @param string $date_from Inclusive start date (Y-m-d) or empty.
     * @param string $date_to   Inclusive end date (Y-m-d) or empty.
     * @return array{from: string|null, to_exclusive: string|null}
     */
    private function get_log_sent_date_bounds(string $date_from, string $date_to): array {
        $bounds = array(
            'from'         => null,
            'to_exclusive' => null,
        );

        $from = $this->parse_log_filter_date($date_from);
        if ($from) {
            $bounds['from'] = $from . ' 00:00:00';
        }

        $to = $this->parse_log_filter_date($date_to);
        if ($to) {
            $bounds['to_exclusive'] = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $to . ' 00:00:00', wp_timezone())
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');
        }

        return $bounds;
    }

    /**
     * Compute a sent_date cutoff for log age queries (site timezone).
     *
     * @param int $days Age threshold in days (minimum 1).
     * @return string
     */
    private function get_log_age_cutoff(int $days): string {
        $days = max(1, $days);

        return (new DateTimeImmutable('now', wp_timezone()))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');
    }

    /**
     * Append sent_date WHERE clauses for log queries.
     *
     * @param array  $where_conditions Existing conditions.
     * @param array  $where_values     Existing values.
     * @param string $date_from        Filter start date.
     * @param string $date_to          Filter end date.
     * @param string $column           Qualified column name (e.g. l.sent_date).
     * @return array{0: array, 1: array}
     */
    private function append_log_sent_date_filters(array $where_conditions, array $where_values, string $date_from, string $date_to, string $column): array {
        $bounds = $this->get_log_sent_date_bounds($date_from, $date_to);

        if ($bounds['from']) {
            $where_conditions[] = "{$column} >= %s";
            $where_values[]     = $bounds['from'];
        }

        if ($bounds['to_exclusive']) {
            $where_conditions[] = "{$column} < %s";
            $where_values[]     = $bounds['to_exclusive'];
        }

        return array($where_conditions, $where_values);
    }
    

    /**
     * Build WHERE clause fragments for email log queries.
     *
     * @param array  $args            Query arguments.
     * @param string $sent_date_column Qualified sent_date column (e.g. l.sent_date or sent_date).
     * @return array{0: array, 1: array}
     */
    private function build_log_where(array $args, string $sent_date_column): array {
        $where_conditions = array('1=1');
        $where_values     = array();

        $subscriber_id = isset($args['subscriber_id']) ? (int) $args['subscriber_id'] : 0;
        if ($subscriber_id > 0) {
            $prefix = (strpos($sent_date_column, '.') !== false) ? substr($sent_date_column, 0, strpos($sent_date_column, '.')) . '.' : '';
            $where_conditions[] = $prefix . 'subscriber_id = %d';
            $where_values[]     = $subscriber_id;
        }

        $status = isset($args['status']) ? sanitize_text_field((string) $args['status']) : '';
        if ($status !== '') {
            $prefix = (strpos($sent_date_column, '.') !== false) ? substr($sent_date_column, 0, strpos($sent_date_column, '.')) . '.' : '';
            $where_conditions[] = $prefix . 'status = %s';
            $where_values[]     = $status;
        }

        $email_type = isset($args['email_type']) ? sanitize_key((string) $args['email_type']) : '';
        if ($email_type !== '' && isset(sn_get_email_log_types()[ $email_type ])) {
            $prefix = (strpos($sent_date_column, '.') !== false) ? substr($sent_date_column, 0, strpos($sent_date_column, '.')) . '.' : '';
            $where_conditions[] = $prefix . 'email_type = %s';
            $where_values[]     = $email_type;
        }

        list($where_conditions, $where_values) = $this->append_log_sent_date_filters(
            $where_conditions,
            $where_values,
            (string) ($args['date_from'] ?? ''),
            (string) ($args['date_to'] ?? ''),
            $sent_date_column
        );

        return array($where_conditions, $where_values);
    }

    /**
     * Count email logs with sent_date older than the given number of days.
     *
     * @param int $days Age threshold in days (minimum 1).
     * @return int Matching row count.
     */
    public function count_logs_older_than(int $days): int {
        $cutoff = $this->get_log_age_cutoff($days);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE sent_date < %s",
            $cutoff
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Delete email logs with sent_date older than the given number of days.
     *
     * @param int $days Age threshold in days (minimum 1).
     * @return int Rows deleted.
     */
    public function delete_logs_older_than(int $days): int {
        $cutoff = $this->get_log_age_cutoff($days);

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->logs_table} WHERE sent_date < %s",
            $cutoff
        );

        $result = $this->wpdb->query($sql);
        return ($result === false) ? 0 : (int) $result;
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array Array of log objects
     */
    public function get_logs(array $args = array()): array {
        $defaults = array(
            'limit'         => 20,
            'offset'        => 0,
            'subscriber_id' => 0,
            'status'        => '',
            'email_type'    => '',
            'date_from'     => '',
            'date_to'       => '',
            'orderby'       => 'sent_date',
            'order'         => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $orderby_whitelist = array(
            'sent_date' => 'l.sent_date',
            'status'    => 'l.status',
            'email_type'=> 'l.email_type',
        );
        $orderby_sql = isset($orderby_whitelist[ $args['orderby'] ]) ? $orderby_whitelist[ $args['orderby'] ] : 'l.sent_date';
        $order_sql   = 'ASC' === strtoupper((string) $args['order']) ? 'ASC' : 'DESC';

        list($where_conditions, $where_values) = $this->build_log_where($args, 'l.sent_date');
        $where_clause = implode(' AND ', $where_conditions);

        $sql = "
            SELECT l.*, s.name, s.email 
            FROM {$this->logs_table} l 
            LEFT JOIN {$this->subscribers_table} s ON l.subscriber_id = s.id 
            WHERE {$where_clause} 
            ORDER BY {$orderby_sql} {$order_sql}
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
            'status'        => '',
            'email_type'    => '',
            'date_from'     => '',
            'date_to'       => '',
        );

        $args = wp_parse_args($args, $defaults);

        list($where_conditions, $where_values) = $this->build_log_where($args, 'sent_date');
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
            'status'  => '',
            'wp_user' => '',
            'search'  => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if ('linked' === $args['wp_user']) {
            $where_conditions[] = 'user_id IS NOT NULL AND user_id > 0';
        } elseif ('none' === $args['wp_user']) {
            $where_conditions[] = '(user_id IS NULL OR user_id = 0)';
        }
        
        list($search_sql, $search_values) = $this->get_subscriber_search_where((string) $args['search']);
        if ('' !== $search_sql) {
            $where_conditions[] = ltrim($search_sql, ' AND ');
            $where_values     = array_merge($where_values, $search_values);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (empty($where_values)) {
            $sql = "SELECT COUNT(*) FROM {$this->subscribers_table} WHERE {$where_clause}";
            return (int) $this->wpdb->get_var($sql);
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
     * Aggregate subscriber counts for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function get_subscriber_stats(): array {
        $row = $this->wpdb->get_row(
            "SELECT
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN status = 'active' AND frequency = 'daily' THEN 1 ELSE 0 END) AS daily,
                SUM(CASE WHEN status = 'active' AND frequency = 'weekly' THEN 1 ELSE 0 END) AS weekly,
                SUM(CASE WHEN status = 'active' AND frequency = 'monthly' THEN 1 ELSE 0 END) AS monthly,
                SUM(CASE WHEN status = 'active' AND user_id IS NOT NULL AND user_id > 0 THEN 1 ELSE 0 END) AS linked_wp_user,
                SUM(CASE WHEN status = 'active' AND last_notified IS NULL THEN 1 ELSE 0 END) AS never_notified
            FROM {$this->subscribers_table}",
            ARRAY_A
        );

        if (!is_array($row)) {
            $row = array();
        }

        return array(
            'active'           => (int) ($row['active'] ?? 0),
            'inactive'         => (int) ($row['inactive'] ?? 0),
            'daily'            => (int) ($row['daily'] ?? 0),
            'weekly'           => (int) ($row['weekly'] ?? 0),
            'monthly'          => (int) ($row['monthly'] ?? 0),
            'linked_wp_user'   => (int) ($row['linked_wp_user'] ?? 0),
            'never_notified'   => (int) ($row['never_notified'] ?? 0),
        );
    }

    /**
     * Aggregate notification queue counts for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function get_notification_stats(): array {
        $row = $this->wpdb->get_row(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'pending' AND is_recurring = 1 AND recurrence_count > 0 THEN 1 ELSE 0 END) AS active_recurring,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM {$this->notifications_table}",
            ARRAY_A
        );

        if (!is_array($row)) {
            $row = array();
        }

        return array(
            'pending'          => (int) ($row['pending'] ?? 0),
            'active_recurring' => (int) ($row['active_recurring'] ?? 0),
            'sent'             => (int) ($row['sent'] ?? 0),
            'cancelled'        => (int) ($row['cancelled'] ?? 0),
        );
    }

    /**
     * Per-recipient send queue counts and recent failures for the dashboard.
     *
     * @param int $failed_limit Max failed rows to return.
     * @return array{counts: array<string, int>, recent_failed: array<int, object>}
     */
    public function get_send_queue_stats(int $failed_limit = 5): array {
        $counts_row = $this->wpdb->get_row(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped
            FROM {$this->send_queue_table}",
            ARRAY_A
        );

        $counts = array(
            'pending' => (int) ($counts_row['pending'] ?? 0),
            'sent'    => (int) ($counts_row['sent'] ?? 0),
            'failed'  => (int) ($counts_row['failed'] ?? 0),
            'skipped' => (int) ($counts_row['skipped'] ?? 0),
        );

        $failed_limit = max(1, min(20, $failed_limit));
        $recent_failed = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT sq.id, sq.notification_id, sq.subscriber_id, sq.attempts, sq.last_error, sq.sent_at,
                        n.title AS notification_title, s.email AS subscriber_email
                 FROM {$this->send_queue_table} sq
                 LEFT JOIN {$this->notifications_table} n ON n.id = sq.notification_id
                 LEFT JOIN {$this->subscribers_table} s ON s.id = sq.subscriber_id
                 WHERE sq.status = 'failed'
                 ORDER BY sq.id DESC
                 LIMIT %d",
                $failed_limit
            )
        );

        return array(
            'counts'        => $counts,
            'recent_failed' => is_array($recent_failed) ? $recent_failed : array(),
        );
    }

    /**
     * Pending notifications with a scheduled next send, soonest first.
     *
     * @param int $limit Max rows.
     * @return array<int, object>
     */
    public function get_upcoming_notifications(int $limit = 10): array {
        $limit = max(1, min(50, $limit));
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, title, frequency_target, is_recurring, next_send_date, status
                 FROM {$this->notifications_table}
                 WHERE status = 'pending' AND next_send_date IS NOT NULL
                 ORDER BY next_send_date ASC
                 LIMIT %d",
                $limit
            )
        );

        return is_array($results) ? $results : array();
    }

    /**
     * Recent email log rows for the dashboard activity feed.
     *
     * @param int $limit Max rows.
     * @return array<int, object>
     */
    public function get_recent_logs(int $limit = 10): array {
        return $this->get_logs(array(
            'limit'  => max(1, min(50, $limit)),
            'offset' => 0,
        ));
    }

    /**
     * Recently added subscribers for the dashboard activity feed.
     *
     * @param int $limit Max rows.
     * @return array<int, object>
     */
    public function get_recent_subscribers(int $limit = 10): array {
        $limit = max(1, min(50, $limit));
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, name, email, frequency, status, date_added, last_notified
                 FROM {$this->subscribers_table}
                 ORDER BY date_added DESC
                 LIMIT %d",
                $limit
            )
        );

        return is_array($results) ? $results : array();
    }
    
}
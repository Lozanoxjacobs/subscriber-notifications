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
     * Default number of send-queue rows to drain per cron tick. Override with
     * the `subscriber_notifications_queue_batch_size` filter.
     */
    const SEND_QUEUE_BATCH_SIZE = 50;
    
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
        add_action('subscriber_notifications_drain_queue', array($this, 'drain_send_queue'));
        
        // Add 'every_minute' schedule if not already added by another plugin
        add_filter('cron_schedules', array($this, 'add_every_minute_schedule'));
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
    }
    
    /**
     * Process pending one-time notifications whose `next_send_date` has elapsed.
     *
     * Recurring notifications are handled by the frequency-specific crons
     * (`send_daily_notifications` / `send_weekly_notifications` /
     * `send_monthly_notifications`), which also filter on `next_send_date`.
     */
    public function process_queue() {
        global $wpdb;
        
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue
            WHERE status = 'pending'
              AND is_recurring = 0
              AND next_send_date IS NOT NULL
              AND next_send_date <= %s
            ORDER BY next_send_date ASC
            LIMIT %d
        ", current_time('mysql'), self::NOTIFICATION_BATCH_SIZE));
        
        foreach ($notifications as $notification) {
            $this->send_scheduled_notification($notification->id);
        }
    }
    
    /**
     * Enqueue a notification's eligible subscribers into the per-recipient send queue.
     *
     * Replaces the old "loop + send_email() inline" path. The notification's
     * overall status stays `pending` until the queue is fully drained by the
     * `subscriber_notifications_drain_queue` cron handler.
     *
     * @param int $notification_id Notification ID.
     * @return bool True if at least one row was enqueued, false if nothing to do.
     */
    public function send_scheduled_notification(int $notification_id): bool {
        $notification = $this->get_notification($notification_id);
        
        if (!$notification || $notification->status !== 'pending') {
            return false;
        }
        
        $subscribers = $this->get_target_subscribers($notification);
        
        if (empty($subscribers)) {
            // No eligible subscribers — finalize immediately so this notification is
            // not re-evaluated on every cron tick.
            $this->finalize_notification($notification);
            return false;
        }
        
        return $this->enqueue_recipients((int) $notification->id, $subscribers) > 0;
    }
    
    /**
     * Insert one pending row per eligible subscriber into the send queue.
     *
     * Uses INSERT IGNORE so a retried enqueue (e.g., after a PHP timeout mid-loop)
     * does not produce duplicates — the UNIQUE KEY on (notification_id, subscriber_id)
     * blocks them.
     *
     * @param int   $notification_id Notification ID.
     * @param array $subscribers     Subscriber rows.
     * @return int Number of new rows inserted.
     */
    private function enqueue_recipients(int $notification_id, array $subscribers): int {
        global $wpdb;
        
        $send_queue_table = $wpdb->prefix . 'subscriber_notifications_send_queue';
        $inserted         = 0;
        
        foreach ($subscribers as $subscriber) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$send_queue_table} (notification_id, subscriber_id, status, enqueued_at) VALUES (%d, %d, %s, %s)",
                $notification_id,
                (int) $subscriber->id,
                'pending',
                current_time('mysql')
            ));
            
            if ($result) {
                $inserted++;
            }
        }
        
        return $inserted;
    }
    
    /**
     * Cron handler: drain a batch of pending send-queue rows.
     *
     * Per row, fetches the notification + subscriber, runs the per-recipient
     * `has_relevant_content()` check (deferred from enqueue time so the work is
     * spread across cron ticks), and sends through the email sender. Each row's
     * status moves to `sent`, `failed`, or `skipped`.
     *
     * After draining the batch, every notification touched is checked for
     * "fully drained" — if no pending rows remain, the notification is finalized
     * (recurring → recompute next_send_date; one-time → status='sent').
     */
    public function drain_send_queue(): void {
        global $wpdb;
        
        $send_queue_table = $wpdb->prefix . 'subscriber_notifications_send_queue';
        $batch_size       = (int) apply_filters('subscriber_notifications_queue_batch_size', self::SEND_QUEUE_BATCH_SIZE);
        if ($batch_size < 1) {
            $batch_size = 1;
        }
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$send_queue_table} WHERE status = %s ORDER BY id ASC LIMIT %d",
            'pending',
            $batch_size
        ));
        
        if (empty($rows)) {
            return;
        }
        
        $email_sender                = new SubscriberNotifications_Email_Sender();
        $touched_notification_ids    = array();
        
        foreach ($rows as $row) {
            $touched_notification_ids[(int) $row->notification_id] = true;
            
            $notification = $this->get_notification((int) $row->notification_id);
            $subscriber   = $this->database->get_subscriber((int) $row->subscriber_id);
            
            // Cancelled notification or inactive/missing subscriber → skip.
            if (!$notification || $notification->status !== 'pending' ||
                !$subscriber || $subscriber->status !== 'active') {
                $this->mark_queue_row($row, 'skipped');
                continue;
            }
            
            // Per-recipient content check — deferred from enqueue time so the
            // expensive WP_Query work is spread across cron ticks.
            if (!$this->has_relevant_content($subscriber, $notification->frequency_target, $notification)) {
                $this->mark_queue_row($row, 'skipped');
                continue;
            }
            
            $prepared = $this->prepare_notification_content($notification, $subscriber);
            $sent     = $email_sender->send_email(
                $subscriber->email,
                $prepared['subject'],
                $prepared['content'],
                (int) $subscriber->id,
                (int) $notification->id
            );
            
            if ($sent) {
                $wpdb->update(
                    $send_queue_table,
                    array(
                        'status'   => 'sent',
                        'sent_at'  => current_time('mysql'),
                        'attempts' => (int) $row->attempts + 1,
                    ),
                    array('id' => (int) $row->id),
                    array('%s', '%s', '%d'),
                    array('%d')
                );

                // Stamp the recipient's last_notified so admin lists, CSV export, and
                // any rate-limit checks reflect that this subscriber has been notified.
                $this->database->update_subscriber_last_notified((int) $row->subscriber_id);
            } else {
                $wpdb->update(
                    $send_queue_table,
                    array(
                        'status'     => 'failed',
                        'attempts'   => (int) $row->attempts + 1,
                        'last_error' => 'wp_mail returned false',
                    ),
                    array('id' => (int) $row->id),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
            }
        }
        
        // After this batch, finalize any notifications whose queue is fully drained.
        foreach (array_keys($touched_notification_ids) as $notification_id) {
            $remaining = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$send_queue_table} WHERE notification_id = %d AND status = %s",
                $notification_id,
                'pending'
            ));
            
            if ($remaining === 0) {
                $notification = $this->get_notification($notification_id);
                if ($notification && $notification->status === 'pending') {
                    $this->finalize_notification($notification);
                }
            }
        }
    }
    
    /**
     * Mark a queue row's terminal state without delivery.
     *
     * @param object $row    Queue row.
     * @param string $status One of 'sent' | 'failed' | 'skipped'.
     */
    private function mark_queue_row($row, string $status): void {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'subscriber_notifications_send_queue',
            array(
                'status'   => $status,
                'attempts' => (int) $row->attempts + 1,
            ),
            array('id' => (int) $row->id),
            array('%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Finalize a notification once its send queue is fully drained.
     *
     * Recurring notifications stay `pending` with a recomputed `next_send_date`
     * and incremented `recurrence_count`. One-time notifications move to `sent`.
     *
     * @param object $notification Notification row.
     */
    private function finalize_notification($notification): void {
        global $wpdb;
        
        if ($notification->is_recurring) {
            $last_sent_date = current_time('mysql');
            $next_send_date = (new SubscriberNotifications_Schedule_Calculator())
                ->next_recurring($notification->frequency_target, $last_sent_date);
            
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notifications_queue',
                array(
                    'last_sent_date'   => $last_sent_date,
                    'next_send_date'   => $next_send_date,
                    'recurrence_count' => (int) $notification->recurrence_count + 1,
                ),
                array('id' => (int) $notification->id),
                array('%s', '%s', '%d'),
                array('%d')
            );
        } else {
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notifications_queue',
                array(
                    'status'    => 'sent',
                    'sent_date' => current_time('mysql'),
                ),
                array('id' => (int) $notification->id),
                array('%s', '%s'),
                array('%d')
            );
        }

        $this->database->purge_send_queue_for_notification((int) $notification->id);
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
        $tz = wp_timezone();
        switch ($frequency) {
            case 'daily':
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 day');
                break;
            case 'monthly':
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 month');
                break;
            case 'weekly':
            default:
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 week');
                break;
        }
        $cutoff_date = $cutoff->format('Y-m-d H:i:s');

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
     * Schedule cron jobs.
     *
     * All hooks run on the `every_minute` schedule; each handler decides
     * whether a notification is actually due. Misscheduled events (e.g. the
     * legacy `hourly` `process_queue` from pre-3.3 installs) are rewritten on
     * load so installs self-heal without requiring a reactivation.
     */
    private function schedule_cron_jobs() {
        $hooks = array(
            'subscriber_notifications_process_queue',
            'subscriber_notifications_send_daily',
            'subscriber_notifications_send_weekly',
            'subscriber_notifications_send_monthly',
            'subscriber_notifications_drain_queue',
        );

        foreach ($hooks as $hook) {
            $scheduled = wp_get_scheduled_event($hook);
            if ($scheduled && $scheduled->schedule === 'every_minute') {
                continue;
            }

            if ($scheduled) {
                wp_clear_scheduled_hook($hook);
            }

            wp_schedule_event(time(), 'every_minute', $hook);
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
    
}
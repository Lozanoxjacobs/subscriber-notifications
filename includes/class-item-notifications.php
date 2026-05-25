<?php
/**
 * Per-post item subscription emails (subscribe confirmation + immediate updates).
 *
 * @package SubscriberNotifications
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Item notification sender.
 */
class SubscriberNotifications_Item_Notifications {

    /**
     * Inline send threshold; above this uses background queue.
     */
    const INLINE_THRESHOLD = 10;

    /**
     * Batch size for background item update sends.
     */
    const QUEUE_BATCH_SIZE = 50;

    /**
     * Option key for pending item update queue.
     */
    const QUEUE_OPTION = 'subscriber_notifications_item_update_queue';

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
    }

    /**
     * Register cron hook for draining item update queue.
     */
    public static function register_hooks() {
        add_action('subscriber_notifications_send_item_updates', array(__CLASS__, 'cron_send_item_updates'));
    }

    /**
     * Cron callback: drain item update queue.
     */
    public static function cron_send_item_updates() {
        $database = new SubscriberNotifications_Database();
        $handler  = new self($database);
        $handler->drain_queue(self::QUEUE_BATCH_SIZE);
    }

    /**
     * Active subscribers with this post in _items.
     *
     * @param int $post_id Post ID.
     * @return array<int, object>
     */
    public function get_subscribers_for_post($post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post) {
            return array();
        }

        $all = $this->database->get_subscribers(array(
            'status' => 'active',
            'limit'  => 100000,
            'offset' => 0,
        ));

        $matched = array();
        foreach ($all as $subscriber) {
            $prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');
            if (SubscriberNotifications_Preferences::has_item($prefs, $post_id)) {
                $matched[] = $subscriber;
            }
        }
        return $matched;
    }

    /**
     * Send item update emails for a post. Returns admin notice message parts.
     *
     * @param int $post_id Post ID.
     * @return array{sent: int, queued: int, message: string}
     */
    public function send_item_update($post_id) {
        $post_id = (int) $post_id;
        if (!SubscriberNotifications_Preferences::can_send_item_notifications($post_id)) {
            return array(
                'sent'    => 0,
                'queued'  => 0,
                'message' => '',
            );
        }

        update_post_meta($post_id, SubscriberNotifications_Preferences::META_LAST_NOTIFICATION_DATE, current_time('mysql'));

        $subscribers = $this->get_subscribers_for_post($post_id);
        $count       = count($subscribers);

        if ($count === 0) {
            return array(
                'sent'    => 0,
                'queued'  => 0,
                'message' => __('No item subscribers to notify for this content.', 'subscriber-notifications'),
            );
        }

        if ($count <= self::INLINE_THRESHOLD) {
            $sent = $this->send_to_subscribers($post_id, $subscribers);
            return array(
                'sent'    => $sent,
                'queued'  => 0,
                'message' => sprintf(
                    /* translators: %d: number of subscribers */
                    _n(
                        'Item update email sent to %d subscriber.',
                        'Item update emails sent to %d subscribers.',
                        $sent,
                        'subscriber-notifications'
                    ),
                    $sent
                ),
            );
        }

        $this->enqueue_item_update($post_id, wp_list_pluck($subscribers, 'id'));
        $this->schedule_queue_drain();

        return array(
            'sent'    => 0,
            'queued'  => $count,
            'message' => sprintf(
                /* translators: %d: number of subscribers */
                __('Sending item update emails to %d subscribers in the background.', 'subscriber-notifications'),
                $count
            ),
        );
    }

    /**
     * Send item_subscribe confirmation email.
     *
     * @param object $subscriber Subscriber row.
     * @param int    $post_id    Post ID subscribed to.
     * @return bool
     */
    public function send_item_subscribe_email($subscriber, $post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$subscriber || !$post) {
            return false;
        }

        $subject = subscriber_notifications_get_option(
            'item_subscribe_email_subject',
            __('[site_title] You\'re subscribed to updates for [post_title]', 'subscriber-notifications')
        );
        $content = subscriber_notifications_get_option(
            'item_subscribe_email_content',
            __("Hello [subscriber_name],\n\nYou're subscribed to receive email when [post_title] is updated.\n\nView page: [post_link]\n\n[manage_preferences_link]", 'subscriber-notifications')
        );

        return $this->send_item_email($subscriber, $post, $subject, $content, 'item_subscribe');
    }

    /**
     * @param int           $post_id     Post ID.
     * @param array<int, object> $subscribers Subscriber rows.
     * @return int Sent count.
     */
    private function send_to_subscribers($post_id, array $subscribers) {
        $post = get_post((int) $post_id);
        if (!$post) {
            return 0;
        }

        $subject = subscriber_notifications_get_option(
            'item_update_email_subject',
            __('[site_title] Update: [post_title]', 'subscriber-notifications')
        );
        $content = subscriber_notifications_get_option(
            'item_update_email_content',
            __("Hello [subscriber_name],\n\n[post_title] has been updated.\n\n[post_link]\n\n[manage_preferences_link]", 'subscriber-notifications')
        );

        $sent = 0;
        foreach ($subscribers as $subscriber) {
            if ($this->send_item_email($subscriber, $post, $subject, $content, 'item_update')) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * @param object $subscriber Subscriber row.
     * @param WP_Post $post       Post object.
     * @param string $subject    Raw subject template.
     * @param string $content    Raw body template.
     * @param string $email_type Log type slug.
     * @return bool
     */
    private function send_item_email($subscriber, $post, $subject, $content, $email_type) {
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber, null, $post);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber, null, $post);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $result = $mailer->send_email(
            $subscriber->email,
            $processed_subject,
            $processed_content,
            (int) $subscriber->id,
            0,
            $email_type
        );

        if ($result) {
            $this->database->update_subscriber_last_notified((int) $subscriber->id);
        }

        return $result;
    }

    /**
     * @param int   $post_id        Post ID.
     * @param int[] $subscriber_ids Subscriber IDs.
     */
    private function enqueue_item_update($post_id, array $subscriber_ids) {
        $queue = get_option(self::QUEUE_OPTION, array());
        if (!is_array($queue)) {
            $queue = array();
        }
        $subscriber_ids = array_values(array_unique(array_map('intval', $subscriber_ids)));
        $subscriber_ids = array_filter($subscriber_ids, function ($id) {
            return $id > 0;
        });
        if (empty($subscriber_ids)) {
            return;
        }
        $queue[] = array(
            'post_id'        => (int) $post_id,
            'subscriber_ids' => $subscriber_ids,
            'enqueued_at'    => current_time('mysql'),
        );
        update_option(self::QUEUE_OPTION, $queue, false);
    }

    /**
     * Schedule cron drain if not already scheduled.
     */
    private function schedule_queue_drain() {
        if (!wp_next_scheduled('subscriber_notifications_send_item_updates')) {
            wp_schedule_single_event(time() + 10, 'subscriber_notifications_send_item_updates');
        }
    }

    /**
     * Drain queued item updates.
     *
     * @param int $batch_size Max subscribers to process this run.
     * @return int Processed send count.
     */
    public function drain_queue($batch_size = self::QUEUE_BATCH_SIZE) {
        $queue = get_option(self::QUEUE_OPTION, array());
        if (!is_array($queue) || empty($queue)) {
            return 0;
        }

        $processed = 0;
        $remaining = array();
        $budget    = max(1, (int) $batch_size);

        foreach ($queue as $job) {
            if ($budget <= 0) {
                $remaining[] = $job;
                continue;
            }
            if (empty($job['post_id']) || empty($job['subscriber_ids']) || !is_array($job['subscriber_ids'])) {
                continue;
            }

            $post_id = (int) $job['post_id'];
            $ids     = array_map('intval', $job['subscriber_ids']);
            $chunk   = array_splice($ids, 0, $budget);
            $budget -= count($chunk);

            $subscribers = array();
            foreach ($chunk as $sid) {
                $row = $this->database->get_subscriber($sid);
                if ($row && $row->status === 'active') {
                    $subscribers[] = $row;
                }
            }

            if (!empty($subscribers) && SubscriberNotifications_Preferences::can_send_item_notifications($post_id)) {
                $processed += $this->send_to_subscribers($post_id, $subscribers);
            }

            if (!empty($ids)) {
                $remaining[] = array(
                    'post_id'        => $post_id,
                    'subscriber_ids' => $ids,
                    'enqueued_at'    => $job['enqueued_at'] ?? current_time('mysql'),
                );
            }
        }

        if (!empty($remaining)) {
            update_option(self::QUEUE_OPTION, $remaining, false);
            $this->schedule_queue_drain();
        } else {
            delete_option(self::QUEUE_OPTION);
        }

        return $processed;
    }

    /**
     * Store admin notice after post save.
     *
     * @param int    $user_id User ID.
     * @param string $message Notice message.
     */
    public static function set_admin_notice($user_id, $message) {
        if ($message === '' || $user_id < 1) {
            return;
        }
        set_transient('sn_item_update_notice_' . $user_id, $message, 60);
    }

    /**
     * Show and clear admin notice.
     */
    public static function maybe_show_admin_notice() {
        $user_id = get_current_user_id();
        if ($user_id < 1) {
            return;
        }
        $message = get_transient('sn_item_update_notice_' . $user_id);
        if ($message === false || $message === '') {
            return;
        }
        delete_transient('sn_item_update_notice_' . $user_id);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

<?php
/**
 * Admin dashboard data aggregation.
 *
 * @package SubscriberNotifications
 * @since 3.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a structured snapshot for the admin dashboard template.
 */
class SubscriberNotifications_Dashboard {

    /**
     * @var SubscriberNotifications_Database
     */
    private $database;

    /**
     * @var SubscriberNotifications_Analytics
     */
    private $analytics;

    /**
     * Cron hooks the scheduler registers on `every_minute`.
     *
     * @var string[]
     */
    private const CRON_HOOKS = array(
        'subscriber_notifications_process_queue',
        'subscriber_notifications_send_daily',
        'subscriber_notifications_send_weekly',
        'subscriber_notifications_send_monthly',
        'subscriber_notifications_drain_queue',
    );

    /**
     * @param SubscriberNotifications_Database $database Database layer.
     * @param SubscriberNotifications_Analytics  $analytics Analytics helper.
     */
    public function __construct(
        SubscriberNotifications_Database $database,
        SubscriberNotifications_Analytics $analytics
    ) {
        $this->database  = $database;
        $this->analytics = $analytics;
    }

    /**
     * Allowed analytics period keys.
     *
     * @return string[]
     */
    public static function get_analytics_periods(): array {
        return array('7days', '30days', '90days', 'all');
    }

    /**
     * Human label for an analytics period key.
     *
     * @param string $period Period key.
     * @return string
     */
    public static function get_analytics_period_label(string $period): string {
        $labels = array(
            '7days'  => __('Last 7 days', 'subscriber-notifications'),
            '30days' => __('Last 30 days', 'subscriber-notifications'),
            '90days' => __('Last 90 days', 'subscriber-notifications'),
            'all'    => __('All time', 'subscriber-notifications'),
        );

        return $labels[ $period ] ?? $labels['30days'];
    }

    /**
     * Sanitize and normalize an analytics period query arg.
     *
     * @param string $period Raw period.
     * @return string
     */
    public static function sanitize_analytics_period(string $period): string {
        $period = sanitize_key($period);
        return in_array($period, self::get_analytics_periods(), true) ? $period : '30days';
    }

    /**
     * Build the full dashboard snapshot for rendering.
     *
     * @param string $analytics_period Period key (7days, 30days, 90days, all).
     * @return array<string, mixed>
     */
    public function get_snapshot(string $analytics_period = '30days'): array {
        $analytics_period = self::sanitize_analytics_period($analytics_period);
        list($date_from, $date_to) = $this->get_analytics_date_range($analytics_period);

        $analytics_summary = $this->analytics->get_analytics_summary($date_from, $date_to);
        $schedule          = $this->get_schedule_summary();
        $cron              = $this->get_cron_health();
        $health            = $this->get_health_checklist($cron);

        return array(
            'analytics_period'       => $analytics_period,
            'analytics_period_label' => self::get_analytics_period_label($analytics_period),
            'analytics'              => $analytics_summary,
            'subscribers'            => $this->database->get_subscriber_stats(),
            'notifications'          => $this->database->get_notification_stats(),
            'send_queue'             => $this->database->get_send_queue_stats(5),
            'upcoming'               => $this->database->get_upcoming_notifications(10),
            'recent_logs'            => $this->database->get_recent_logs(8),
            'recent_subscribers'     => $this->database->get_recent_subscribers(8),
            'schedule'               => $schedule,
            'cron'                   => $cron,
            'health'                 => $health,
            'content_types'          => $this->get_content_types_summary(),
            'urls'                   => $this->get_admin_urls(),
            'test_email'             => subscriber_notifications_get_option('test_email', get_option('admin_email')),
            'plugin_version'         => defined('SUBSCRIBER_NOTIFICATIONS_VERSION') ? SUBSCRIBER_NOTIFICATIONS_VERSION : '',
            'site_timezone'          => wp_timezone_string(),
            'wp_cron_disabled'       => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        );
    }

    /**
     * Map analytics period to log query date bounds (site timezone).
     *
     * @param string $period Period key.
     * @return array{0: string, 1: string} date_from, date_to.
     */
    private function get_analytics_date_range(string $period): array {
        if ($period === 'all') {
            return array('', '');
        }

        $days = 30;
        if ($period === '7days') {
            $days = 7;
        } elseif ($period === '90days') {
            $days = 90;
        }

        $date_from = wp_date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return array($date_from, '');
    }

    /**
     * Global send-time options formatted for display.
     *
     * @return array<string, string>
     */
    private function get_schedule_summary(): array {
        $daily_time   = subscriber_notifications_get_option('daily_send_time', '09:00');
        $weekly_day   = subscriber_notifications_get_option('weekly_send_day', 'tuesday');
        $weekly_time  = subscriber_notifications_get_option('weekly_send_time', '14:00');
        $monthly_day  = (int) subscriber_notifications_get_option('monthly_send_day', 15);
        $monthly_time = subscriber_notifications_get_option('monthly_send_time', '14:00');

        $monthly_suffix = wp_date('S', strtotime('2000-01-' . max(1, min(31, $monthly_day))));

        return array(
            'daily'   => wp_date('g:i A', strtotime($daily_time)),
            'weekly'  => sprintf(
                /* translators: 1: weekday name, 2: time */
                __('%1$s at %2$s', 'subscriber-notifications'),
                ucfirst($weekly_day),
                wp_date('g:i A', strtotime($weekly_time))
            ),
            'monthly' => sprintf(
                /* translators: 1: day of month with ordinal, 2: time */
                __('%1$s at %2$s', 'subscriber-notifications'),
                $monthly_day . $monthly_suffix,
                wp_date('g:i A', strtotime($monthly_time))
            ),
        );
    }

    /**
     * Inspect WP-Cron registration for plugin hooks.
     *
     * @return array<string, mixed>
     */
    private function get_cron_health(): array {
        $hooks = array();

        foreach (self::CRON_HOOKS as $hook) {
            $timestamp = wp_next_scheduled($hook);
            $schedule  = null;

            if ($timestamp) {
                $cron = _get_cron_array();
                if (is_array($cron)) {
                    foreach ($cron as $ts => $jobs) {
                        if (!is_array($jobs) || !isset($jobs[ $hook ])) {
                            continue;
                        }
                        foreach ($jobs[ $hook ] as $event) {
                            if (isset($event['schedule'])) {
                                $schedule = $event['schedule'];
                                break 2;
                            }
                        }
                    }
                }
            }

            $hooks[ $hook ] = array(
                'scheduled'  => (bool) $timestamp,
                'next_run'   => $timestamp ? wp_date('M j, Y g:i A', $timestamp) : '',
                'schedule'   => $schedule ?: '',
                'is_minute'  => $schedule === 'every_minute',
            );
        }

        $all_ok = true;
        foreach ($hooks as $info) {
            if (!$info['scheduled'] || !$info['is_minute']) {
                $all_ok = false;
                break;
            }
        }

        return array(
            'hooks'  => $hooks,
            'all_ok' => $all_ok,
        );
    }

    /**
     * Setup checklist items for the dashboard health postbox.
     *
     * @param array<string, mixed> $cron Cron health from get_cron_health().
     * @return array<int, array<string, mixed>>
     */
    private function get_health_checklist(array $cron): array {
        $subscriber_stats     = $this->database->get_subscriber_stats();
        $notification_stats   = $this->database->get_notification_stats();
        $content_configured   = SubscriberNotifications_Content_Config::is_configured();
        $captcha_site_key     = subscriber_notifications_get_option('captcha_site_key', '');
        $captcha_secret_key   = subscriber_notifications_get_option('captcha_secret_key', '');
        $captcha_configured   = $captcha_site_key !== '' && $captcha_secret_key !== '';
        $frontend_configured  = subscriber_notifications_frontend_pages_are_configured();

        $items = array(
            array(
                'id'      => 'content_types',
                'label'   => __('Content Types configured', 'subscriber-notifications'),
                'ok'      => $content_configured,
                'url'     => admin_url('admin.php?page=subscriber-notifications-content-types'),
                'message' => $content_configured
                    ? ''
                    : __('Enable at least one post type and form taxonomy.', 'subscriber-notifications'),
            ),
            array(
                'id'      => 'frontend_pages',
                'label'   => __('Frontend pages configured', 'subscriber-notifications'),
                'ok'      => $frontend_configured,
                'url'     => admin_url('admin.php?page=subscriber-notifications-settings&tab=general'),
                'message' => $this->get_frontend_pages_health_message($frontend_configured),
            ),
            array(
                'id'      => 'subscribers',
                'label'   => __('Active subscribers', 'subscriber-notifications'),
                'ok'      => $subscriber_stats['active'] > 0,
                'url'     => admin_url('admin.php?page=subscriber-notifications-subscribers'),
                'message' => $subscriber_stats['active'] > 0
                    ? sprintf(
                        /* translators: %d: subscriber count */
                        _n('%d active subscriber', '%d active subscribers', $subscriber_stats['active'], 'subscriber-notifications'),
                        $subscriber_stats['active']
                    )
                    : __('No active subscribers yet.', 'subscriber-notifications'),
            ),
            array(
                'id'      => 'notifications',
                'label'   => __('Pending notifications', 'subscriber-notifications'),
                'ok'      => $notification_stats['pending'] > 0,
                'url'     => admin_url('admin.php?page=subscriber-notifications-notifications&status=pending'),
                'message' => $notification_stats['pending'] > 0
                    ? sprintf(
                        /* translators: %d: notification count */
                        _n('%d notification queued', '%d notifications queued', $notification_stats['pending'], 'subscriber-notifications'),
                        $notification_stats['pending']
                    )
                    : __('No pending notifications (optional).', 'subscriber-notifications'),
                'soft'    => true,
            ),
            array(
                'id'      => 'captcha',
                'label'   => __('reCAPTCHA keys (optional)', 'subscriber-notifications'),
                'ok'      => $captcha_configured,
                'url'     => admin_url('admin.php?page=subscriber-notifications-settings&tab=security'),
                'message' => $captcha_configured
                    ? __('Keys are set.', 'subscriber-notifications')
                    : __('Not configured — form works without CAPTCHA until keys are added.', 'subscriber-notifications'),
                'soft'    => true,
            ),
            array(
                'id'      => 'cron',
                'label'   => __('Cron hooks scheduled (every minute)', 'subscriber-notifications'),
                'ok'      => !empty($cron['all_ok']),
                'url'     => admin_url('admin.php?page=subscriber-notifications-settings&tab=scheduling'),
                'message' => !empty($cron['all_ok'])
                    ? __('All send hooks are scheduled.', 'subscriber-notifications')
                    : __('One or more hooks are missing or not on the every_minute schedule.', 'subscriber-notifications'),
            ),
        );

        $all_required_ok = true;
        foreach ($items as $item) {
            if (!empty($item['soft'])) {
                continue;
            }
            if (empty($item['ok'])) {
                $all_required_ok = false;
                break;
            }
        }

        return array(
            'items'           => $items,
            'all_required_ok' => $all_required_ok,
        );
    }

    /**
     * Health checklist message for subscribe and preferences page settings.
     *
     * @param bool $configured Whether both frontend pages are set.
     * @return string
     */
    private function get_frontend_pages_health_message(bool $configured): string {
        if ($configured) {
            $subscribe_id    = subscriber_notifications_get_subscribe_page_id();
            $preferences_id  = subscriber_notifications_get_preferences_page_id();
            $subscribe_title = get_the_title($subscribe_id);
            $preferences_title = get_the_title($preferences_id);

            return sprintf(
                /* translators: 1: subscribe page title, 2: preferences page title */
                __('%1$s and %2$s selected.', 'subscriber-notifications'),
                $subscribe_title !== '' ? $subscribe_title : __('Subscribe page', 'subscriber-notifications'),
                $preferences_title !== '' ? $preferences_title : __('Preferences page', 'subscriber-notifications')
            );
        }

        $missing = array();
        if (!subscriber_notifications_subscribe_page_is_configured()) {
            $missing[] = __('Subscribe page', 'subscriber-notifications');
        }
        if (!subscriber_notifications_preferences_page_is_configured()) {
            $missing[] = __('Preferences page', 'subscriber-notifications');
        }

        return sprintf(
            /* translators: %s: comma-separated missing page labels */
            __('Select %s under Settings → General.', 'subscriber-notifications'),
            implode(', ', $missing)
        );
    }

    /**
     * Summary of enabled Content Types for the dashboard.
     *
     * @return array<string, mixed>
     */
    private function get_content_types_summary(): array {
        $configured = SubscriberNotifications_Content_Config::is_configured();
        $lines      = array();

        if ($configured) {
            foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
                $taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
                if (empty($taxonomies)) {
                    continue;
                }
                $tax_labels = array();
                foreach ($taxonomies as $taxonomy) {
                    $tax_labels[] = SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy);
                }
                $lines[] = sprintf(
                    '%s (%s)',
                    SubscriberNotifications_Content_Config::get_post_type_label($post_type),
                    implode(', ', $tax_labels)
                );
            }
        }

        return array(
            'configured' => $configured,
            'lines'      => $lines,
        );
    }

    /**
     * Common admin URLs for dashboard links.
     *
     * @return array<string, string>
     */
    private function get_admin_urls(): array {
        return array(
            'dashboard'        => admin_url('admin.php?page=subscriber-notifications'),
            'create'           => admin_url('admin.php?page=subscriber-notifications-create'),
            'notifications'      => admin_url('admin.php?page=subscriber-notifications-notifications'),
            'notifications_pending' => admin_url('admin.php?page=subscriber-notifications-notifications&status=pending'),
            'notifications_recurring' => admin_url('admin.php?page=subscriber-notifications-notifications&status=pending&recurring=1&series=active'),
            'subscribers'      => admin_url('admin.php?page=subscriber-notifications-subscribers'),
            'logs'             => admin_url('admin.php?page=subscriber-notifications-logs'),
            'logs_failed'      => admin_url('admin.php?page=subscriber-notifications-logs&status=failed'),
            'import_export'    => admin_url('admin.php?page=subscriber-notifications-import-export'),
            'content_types'    => admin_url('admin.php?page=subscriber-notifications-content-types'),
            'settings'         => admin_url('admin.php?page=subscriber-notifications-settings'),
            'settings_general' => admin_url('admin.php?page=subscriber-notifications-settings&tab=general'),
            'settings_scheduling' => admin_url('admin.php?page=subscriber-notifications-settings&tab=scheduling'),
            'settings_design'  => admin_url('admin.php?page=subscriber-notifications-settings&tab=email-design'),
            'settings_shortcodes' => admin_url('admin.php?page=subscriber-notifications-settings&tab=shortcodes'),
        );
    }
}

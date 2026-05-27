<?php
/**
 * Shared email log display helpers.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Known email log type slugs and labels.
 *
 * @return array<string, string> slug => translated label
 */
function sn_get_email_log_types(): array {
    return array(
        'notification'       => __('Topic notification', 'subscriber-notifications'),
        'welcome'            => __('Welcome', 'subscriber-notifications'),
        'welcome_back'       => __('Welcome back', 'subscriber-notifications'),
        'preferences_update' => __('Preferences update', 'subscriber-notifications'),
        'test'               => __('Test', 'subscriber-notifications'),
        'item_subscribe'     => __('On-page subscription', 'subscriber-notifications'),
        'item_update'        => __('On-page update', 'subscriber-notifications'),
    );
}

/**
 * Format an email log type slug for display.
 *
 * @param string|null $email_type Raw slug from the database.
 * @return string
 */
function sn_format_email_log_type($email_type): string {
    $email_type = sanitize_key((string) $email_type);
    $types      = sn_get_email_log_types();

    if ($email_type !== '' && isset($types[ $email_type ])) {
        return $types[ $email_type ];
    }

    if ($email_type === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $email_type));
}

/**
 * Format a datetime stored in the site timezone for admin display.
 *
 * @param string|null $date_value Raw datetime.
 * @return string
 */
function sn_format_log_date_local($date_value): string {
    if (empty($date_value)) {
        return '-';
    }

    try {
        $datetime = new DateTimeImmutable($date_value, wp_timezone());
        return $datetime->format('M j, Y g:i A');
    } catch (Exception $e) {
        return mysql2date('M j, Y g:i A', $date_value) ?: '-';
    }
}

<?php
/**
 * Prefixed plugin options helpers.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build full option name with plugin prefix.
 *
 * @param string $short_key Key without prefix (e.g. welcome_email_subject).
 */
function subscriber_notifications_option_name($short_key) {
    return 'subscriber_notifications_' . $short_key;
}

/**
 * Get a plugin option using the subscriber_notifications_ prefix.
 *
 * @param string $short_key Short option key without prefix.
 * @param mixed  $default   Default if not set.
 * @return mixed
 */
function subscriber_notifications_get_option($short_key, $default = false) {
    return get_option(subscriber_notifications_option_name($short_key), $default);
}

/**
 * Update a prefixed plugin option.
 *
 * @param string $short_key Short option key without prefix.
 * @param mixed  $value     Value.
 * @return bool
 */
function subscriber_notifications_update_option($short_key, $value) {
    return update_option(subscriber_notifications_option_name($short_key), $value);
}

<?php
/**
 * Prefixed plugin options helpers and migration from legacy unprefixed keys.
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

/**
 * Legacy option keys that were stored without prefix (pre-2.7).
 *
 * @return array<string, string> Map of short_key => legacy option name (same as short_key for most).
 */
function subscriber_notifications_legacy_option_map() {
    return array(
        'welcome_email_subject'            => 'welcome_email_subject',
        'welcome_email_content'              => 'welcome_email_content',
        'welcome_back_email_subject'        => 'welcome_back_email_subject',
        'welcome_back_email_content'        => 'welcome_back_email_content',
        'preferences_update_email_subject'  => 'preferences_update_email_subject',
        'preferences_update_email_content'   => 'preferences_update_email_content',
        'captcha_site_key'                  => 'captcha_site_key',
        'captcha_secret_key'                => 'captcha_secret_key',
        'global_header_logo'                => 'global_header_logo',
        'global_header_content'             => 'global_header_content',
        'global_footer'                     => 'global_footer',
        'email_css'                         => 'email_css',
        'daily_send_time'                   => 'daily_send_time',
        'weekly_send_time'                  => 'weekly_send_time',
        'weekly_send_day'                   => 'weekly_send_day',
        'monthly_send_time'                 => 'monthly_send_time',
        'monthly_send_day'                  => 'monthly_send_day',
        'test_email'                        => 'test_email',
        'delete_data_on_uninstall'          => 'delete_data_on_uninstall',
    );
}

/**
 * One-time migration: copy legacy options into subscriber_notifications_* keys and remove legacy keys.
 */
function subscriber_notifications_migrate_prefixed_options() {
    if (get_option('subscriber_notifications_prefixed_options_migrated', '')) {
        return;
    }

    $map = subscriber_notifications_legacy_option_map();
    foreach ($map as $short => $legacy_name) {
        $pref = subscriber_notifications_option_name($short);
        $legacy_val = get_option($legacy_name, null);
        if ($legacy_val !== null && get_option($pref, null) === null) {
            add_option($pref, $legacy_val);
        } elseif ($legacy_val !== null && get_option($pref, null) !== null) {
            // Prefixed already set; still delete legacy to avoid drift.
        }
        if (get_option($legacy_name, null) !== null) {
            delete_option($legacy_name);
        }
    }

    // Remove obsolete transport/settings keys (no longer used).
    delete_option('mail_method');
    delete_option('sendgrid_api_key');
    delete_option('sendgrid_from_email');
    delete_option('sendgrid_from_name');
    delete_option('subscriber_notifications_settings');

    update_option('subscriber_notifications_prefixed_options_migrated', SUBSCRIBER_NOTIFICATIONS_VERSION);
}

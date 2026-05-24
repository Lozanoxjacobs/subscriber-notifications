<?php
/**
 * Frontend page URL helpers.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate a stored page ID (published page).
 *
 * @param int $page_id Page ID.
 * @return int Valid page ID or 0.
 */
function subscriber_notifications_validate_page_id(int $page_id): int {
    $page_id = absint($page_id);
    if ($page_id < 1) {
        return 0;
    }

    $status = get_post_status($page_id);
    if ($status !== 'publish') {
        return 0;
    }

    if (get_post_type($page_id) !== 'page') {
        return 0;
    }

    return $page_id;
}

/**
 * Subscribe page ID from settings.
 *
 * @return int
 */
function subscriber_notifications_get_subscribe_page_id(): int {
    return subscriber_notifications_validate_page_id(
        (int) subscriber_notifications_get_option('subscribe_page_id', 0)
    );
}

/**
 * Preferences page ID from settings.
 *
 * @return int
 */
function subscriber_notifications_get_preferences_page_id(): int {
    return subscriber_notifications_validate_page_id(
        (int) subscriber_notifications_get_option('preferences_page_id', 0)
    );
}

/**
 * Whether a preferences page is configured and published.
 *
 * @return bool
 */
function subscriber_notifications_preferences_page_is_configured(): bool {
    return subscriber_notifications_get_preferences_page_id() > 0;
}

/**
 * Whether a subscribe page is configured and published.
 *
 * @return bool
 */
function subscriber_notifications_subscribe_page_is_configured(): bool {
    return subscriber_notifications_get_subscribe_page_id() > 0;
}

/**
 * Whether both frontend pages are configured.
 *
 * @return bool
 */
function subscriber_notifications_frontend_pages_are_configured(): bool {
    return subscriber_notifications_subscribe_page_is_configured()
        && subscriber_notifications_preferences_page_is_configured();
}

/**
 * Permalink for the subscribe page, or empty string.
 *
 * @return string
 */
function subscriber_notifications_get_subscribe_page_url(): string {
    $page_id = subscriber_notifications_get_subscribe_page_id();
    if ($page_id < 1) {
        return '';
    }

    $url = get_permalink($page_id);
    return is_string($url) ? $url : '';
}

/**
 * Permalink for the preferences page with optional query args.
 *
 * @param array<string, string> $args Query arguments (e.g. token).
 * @return string Empty when preferences page is not configured.
 */
function subscriber_notifications_get_preferences_page_url(array $args = array()): string {
    $page_id = subscriber_notifications_get_preferences_page_id();
    if ($page_id < 1) {
        return '';
    }

    $url = get_permalink($page_id);
    if (!is_string($url) || $url === '') {
        return '';
    }

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

<?php
/**
 * Preferences helpers.
 *
 * Subscriber and notification target preferences are stored as JSON in the
 * subscribers.subscription_preferences and queue.target_preferences columns.
 *
 * Shape: `{ "post_type": { "taxonomy": [ term_id, ... ], "_items": [ post_id, ... ] } }`
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Preferences helper.
 */
class SubscriberNotifications_Preferences {

    /**
     * Key under each post type for single-post subscriptions.
     */
    const ITEMS_KEY = '_items';

    /**
     * Post meta: include in digest feed pool.
     */
    const META_INCLUDE_IN_FEED = '_subscriber_notifications_include_in_feed';

    /**
     * Post meta: when the post entered the digest feed pool.
     */
    const META_FEED_SINCE = '_subscriber_notifications_feed_since';

    /**
     * Post meta: last item-notify / "updated on" stamp.
     */
    const META_LAST_NOTIFICATION_DATE = '_subscriber_notifications_last_notification_date';

    /**
     * Decode a stored JSON preferences string into a normalized array.
     *
     * @param mixed $json JSON string, array, or null.
     * @return array
     */
    public static function decode($json) {
        if (is_array($json)) {
            return self::normalize($json);
        }
        if (!is_string($json) || $json === '') {
            return array();
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array();
        }
        return self::normalize($decoded);
    }

    /**
     * Encode preferences to JSON for storage.
     *
     * @param array $prefs Preferences array.
     * @return string
     */
    public static function encode($prefs) {
        if (!is_array($prefs)) {
            return wp_json_encode((object) array());
        }
        $normalized = self::normalize($prefs);
        if (empty($normalized)) {
            return wp_json_encode((object) array());
        }
        return wp_json_encode($normalized);
    }

    /**
     * Sanitize a raw POST preferences array (terms and item IDs).
     *
     * @param mixed $raw Raw POST input.
     * @return array
     */
    public static function sanitize_from_post($raw) {
        $out = array();
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $post_type => $taxonomies) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type === '' || !is_array($taxonomies)) {
                continue;
            }

            $item_ids = array();
            if (isset($taxonomies[ self::ITEMS_KEY ])) {
                $item_ids = self::sanitize_item_id_list($taxonomies[ self::ITEMS_KEY ]);
            }

            $clean_tax = array();
            foreach ($taxonomies as $tax => $term_ids) {
                if ($tax === self::ITEMS_KEY) {
                    continue;
                }
                $tax = sanitize_key((string) $tax);
                if ($tax === '') {
                    continue;
                }
                if (is_string($term_ids)) {
                    $term_ids = preg_split('/[\s,]+/', $term_ids);
                }
                if (!is_array($term_ids)) {
                    continue;
                }
                $ids = array();
                foreach ($term_ids as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[ $id ] = $id;
                    }
                }
                if (!empty($ids)) {
                    sort($ids);
                    $clean_tax[ $tax ] = array_values($ids);
                }
            }

            if (!empty($item_ids)) {
                $clean_tax[ self::ITEMS_KEY ] = $item_ids;
            }
            if (!empty($clean_tax)) {
                $out[ $post_type ] = $clean_tax;
            }
        }
        return $out;
    }

    /**
     * Sanitize a list of post IDs.
     *
     * @param mixed $list Raw list.
     * @return int[]
     */
    public static function sanitize_item_id_list($list) {
        if (is_string($list)) {
            $list = preg_split('/[\s,]+/', $list);
        }
        if (!is_array($list)) {
            return array();
        }
        $out = array();
        foreach ($list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[ $id ] = $id;
            }
        }
        sort($out);
        return array_values($out);
    }

    /**
     * Drop term IDs that are no longer in the configured allowed set, then prune items.
     *
     * @param array  $prefs   Preferences array.
     * @param string $context Either `admin` or `public`.
     * @return array Pruned preferences.
     */
    public static function prune_to_allowed_terms(array $prefs, $context = 'admin') {
        $out = array();
        foreach ($prefs as $post_type => $taxonomies) {
            if (!in_array($post_type, SubscriberNotifications_Content_Config::get_enabled_post_types(), true)) {
                continue;
            }
            $allowed_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            if (!is_array($taxonomies)) {
                continue;
            }
            $clean_tax = array();
            foreach ($taxonomies as $tax => $term_ids) {
                if ($tax === self::ITEMS_KEY) {
                    continue;
                }
                if (!in_array($tax, $allowed_taxonomies, true)) {
                    continue;
                }
                $allowed_ids = SubscriberNotifications_Term_Resolver::get_allowed_term_ids($post_type, $tax, $context);
                if (empty($allowed_ids) || !is_array($term_ids)) {
                    continue;
                }
                $kept = array_values(array_intersect(array_map('intval', $term_ids), $allowed_ids));
                if (!empty($kept)) {
                    sort($kept);
                    $clean_tax[ $tax ] = $kept;
                }
            }
            $items = self::get_items($prefs, $post_type);
            if (!empty($items)) {
                $clean_tax[ self::ITEMS_KEY ] = $items;
            }
            if (!empty($clean_tax)) {
                $out[ $post_type ] = $clean_tax;
            }
        }
        return self::prune_items($out);
    }

    /**
     * Prune item IDs to allowed post types with single-item enabled. Keeps unpublished posts.
     *
     * @param array $prefs Preferences array.
     * @return array
     */
    public static function prune_items(array $prefs) {
        $out = array();
        foreach ($prefs as $post_type => $entry) {
            if (!is_string($post_type) || !is_array($entry)) {
                continue;
            }
            if (!SubscriberNotifications_Content_Config::is_single_item_available($post_type)) {
                continue;
            }
            $items = self::get_items($prefs, $post_type);
            $clean_tax = array();
            foreach ($entry as $tax => $ids) {
                if ($tax === self::ITEMS_KEY) {
                    continue;
                }
                if (is_array($ids) && !empty($ids)) {
                    $clean_tax[ $tax ] = $ids;
                }
            }
            if (!empty($items)) {
                $kept = array();
                foreach ($items as $post_id) {
                    $post = get_post((int) $post_id);
                    if (!$post || $post->post_type !== $post_type) {
                        continue;
                    }
                    $kept[ (int) $post_id ] = (int) $post_id;
                }
                if (!empty($kept)) {
                    sort($kept);
                    $clean_tax[ self::ITEMS_KEY ] = array_values($kept);
                }
            }
            if (!empty($clean_tax)) {
                $out[ $post_type ] = $clean_tax;
            }
        }
        return $out;
    }

    /**
     * Merge term pruning and item pruning for a full preferences save.
     *
     * @param array  $prefs   Preferences array.
     * @param string $context admin or public.
     * @return array
     */
    public static function prune_for_save(array $prefs, $context = 'public') {
        $term_prefs = self::prune_to_allowed_terms($prefs, $context);
        $item_only_types = array_diff(
            SubscriberNotifications_Content_Config::get_single_item_post_types(),
            SubscriberNotifications_Content_Config::get_enabled_post_types()
        );
        foreach ($item_only_types as $post_type) {
            $items = self::get_items($prefs, $post_type);
            if (empty($items)) {
                continue;
            }
            if (!isset($term_prefs[ $post_type ])) {
                $term_prefs[ $post_type ] = array();
            }
            $term_prefs[ $post_type ][ self::ITEMS_KEY ] = $items;
        }
        return self::prune_items($term_prefs);
    }

    /**
     * Item post IDs for a post type.
     *
     * @param array  $prefs     Preferences array.
     * @param string $post_type Post type slug.
     * @return int[]
     */
    public static function get_items(array $prefs, $post_type) {
        if (empty($prefs[ $post_type ][ self::ITEMS_KEY ]) || !is_array($prefs[ $post_type ][ self::ITEMS_KEY ])) {
            return array();
        }
        return array_values(array_unique(array_map('intval', $prefs[ $post_type ][ self::ITEMS_KEY ])));
    }

    /**
     * Add a post ID to preferences for its post type.
     *
     * @param array $prefs   Preferences array (modified).
     * @param int   $post_id Post ID.
     * @return array Updated preferences.
     */
    public static function add_item(array $prefs, $post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post || $post_id < 1) {
            return $prefs;
        }
        $post_type = $post->post_type;
        if (!isset($prefs[ $post_type ]) || !is_array($prefs[ $post_type ])) {
            $prefs[ $post_type ] = array();
        }
        $items = self::get_items($prefs, $post_type);
        $items[ $post_id ] = $post_id;
        sort($items);
        $prefs[ $post_type ][ self::ITEMS_KEY ] = array_values($items);
        return self::normalize($prefs);
    }

    /**
     * Whether the subscriber has the given post in _items.
     *
     * @param array $prefs   Preferences array.
     * @param int   $post_id Post ID.
     * @return bool
     */
    public static function has_item(array $prefs, $post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post) {
            return false;
        }
        $items = self::get_items($prefs, $post->post_type);
        return in_array($post_id, $items, true);
    }

    /**
     * Whether item update emails may be sent for this post.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function can_send_item_notifications($post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return false;
        }
        return SubscriberNotifications_Content_Config::is_single_item_available($post->post_type);
    }

    /**
     * Does this prefs array contain at least one term ID?
     *
     * @param array $prefs Preferences array.
     * @return bool
     */
    public static function has_at_least_one_term(array $prefs) {
        foreach ($prefs as $taxonomies) {
            if (!is_array($taxonomies)) {
                continue;
            }
            foreach ($taxonomies as $tax => $term_ids) {
                if ($tax === self::ITEMS_KEY) {
                    continue;
                }
                if (is_array($term_ids) && !empty($term_ids)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Does this prefs array contain at least one term or item subscription?
     *
     * @param array $prefs Preferences array.
     * @return bool
     */
    public static function has_any_subscription(array $prefs) {
        if (self::has_at_least_one_term($prefs)) {
            return true;
        }
        foreach ($prefs as $post_type => $entry) {
            if (!empty(self::get_items($prefs, $post_type))) {
                return true;
            }
        }
        return false;
    }

    /**
     * OR-overlap matching for digests and notifications.
     *
     * @param array|string $subscriber_prefs Subscriber prefs (array or JSON).
     * @param array|string $target_prefs     Target prefs (array or JSON).
     * @return bool
     */
    public static function terms_overlap($subscriber_prefs, $target_prefs) {
        $subscriber_prefs = is_array($subscriber_prefs) ? self::normalize($subscriber_prefs) : self::decode($subscriber_prefs);
        $target_prefs     = is_array($target_prefs) ? self::normalize($target_prefs) : self::decode($target_prefs);

        if (empty($subscriber_prefs) || empty($target_prefs)) {
            return false;
        }

        foreach ($target_prefs as $post_type => $taxonomies) {
            if (empty($subscriber_prefs[ $post_type ]) || !is_array($taxonomies)) {
                continue;
            }
            $allowed_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            foreach ($taxonomies as $tax => $target_ids) {
                if ($tax === self::ITEMS_KEY || !is_array($target_ids) || empty($target_ids)) {
                    continue;
                }
                if (!in_array($tax, $allowed_taxonomies, true)) {
                    continue;
                }
                if (empty($subscriber_prefs[ $post_type ][ $tax ]) || !is_array($subscriber_prefs[ $post_type ][ $tax ])) {
                    continue;
                }
                $allowed_ids  = SubscriberNotifications_Term_Resolver::get_allowed_term_ids($post_type, $tax);
                $target_valid = array_intersect(array_map('intval', $target_ids), $allowed_ids);
                $sub_valid    = array_intersect(array_map('intval', $subscriber_prefs[ $post_type ][ $tax ]), $allowed_ids);
                $overlap      = array_intersect($target_valid, $sub_valid);
                if (!empty($overlap)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Resolve a single post's term IDs into a preferences-shaped array.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_term_ids_for_post($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return array();
        }
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        $post_type  = $post->post_type;
        $taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
        if (empty($taxonomies)) {
            return array();
        }
        $entry = array();
        foreach ($taxonomies as $tax) {
            $term_ids = wp_get_post_terms($post_id, $tax, array('fields' => 'ids'));
            if (is_wp_error($term_ids) || !is_array($term_ids) || empty($term_ids)) {
                continue;
            }
            $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
            sort($term_ids);
            $entry[ $tax ] = $term_ids;
        }
        if (empty($entry)) {
            return array();
        }
        return array( $post_type => $entry );
    }

    /**
     * Flatten preferences into `post_type:taxonomy => [ term_id, ... ]` (excludes _items).
     *
     * @param array $prefs Preferences array.
     * @return array
     */
    public static function flatten(array $prefs) {
        $out = array();
        foreach ($prefs as $post_type => $taxonomies) {
            if (!is_array($taxonomies)) {
                continue;
            }
            foreach ($taxonomies as $tax => $ids) {
                if ($tax === self::ITEMS_KEY || !is_array($ids) || empty($ids)) {
                    continue;
                }
                $key         = $post_type . ':' . $tax;
                $out[ $key ] = array_values(array_unique(array_map('intval', $ids)));
            }
        }
        return $out;
    }

    /**
     * Plain-text label for a subscribed post (email/admin).
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public static function get_item_label($post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post) {
            return sprintf(
                /* translators: %d: post ID */
                __('Unknown page (#%d)', 'subscriber-notifications'),
                $post_id
            );
        }
        $title = get_the_title($post);
        if ($title === '') {
            $title = sprintf('#%d', $post_id);
        }
        if ($post->post_status !== 'publish') {
            $title .= ' ' . __('(currently unavailable)', 'subscriber-notifications');
        }
        return $title;
    }

    /**
     * Human-readable description of terms and items.
     *
     * @param array|string $prefs Preferences array or JSON.
     * @return string
     */
    public static function human_readable($prefs) {
        if (!is_array($prefs)) {
            $prefs = self::decode($prefs);
        }
        $parts = array();
        $term_text = SubscriberNotifications_Term_Resolver::describe_selection($prefs);
        if ($term_text !== '') {
            $parts[] = $term_text;
        }
        $item_text = self::describe_items_plain($prefs);
        if ($item_text !== '') {
            $parts[] = $item_text;
        }
        return implode("\n\n", $parts);
    }

    /**
     * HTML summary for admin list tables.
     *
     * @param array|string $prefs Preferences array or JSON.
     * @return string
     */
    public static function human_readable_admin_html($prefs) {
        if (!is_array($prefs)) {
            $prefs = self::decode($prefs);
        }
        $html = SubscriberNotifications_Term_Resolver::describe_selection_admin_html($prefs);
        $item_html = self::describe_items_html($prefs, false);
        return $html . $item_html;
    }

    /**
     * HTML summary for email shortcodes.
     *
     * @param array|string $prefs Preferences array or JSON.
     * @return string
     */
    public static function human_readable_html($prefs) {
        if (!is_array($prefs)) {
            $prefs = self::decode($prefs);
        }
        $html = SubscriberNotifications_Term_Resolver::describe_selection_html($prefs);
        $item_html = self::describe_items_html($prefs, true);
        return $html . $item_html;
    }

    /**
     * Plain-text list of item subscriptions grouped by post type.
     *
     * @param array $prefs Preferences array.
     * @return string
     */
    public static function describe_items_plain(array $prefs) {
        $sections = array();
        foreach (self::get_item_sections($prefs) as $section) {
            if (empty($section['titles'])) {
                continue;
            }
            $sections[] = $section['post_type_label'] . "\n" . implode(', ', $section['titles']);
        }
        return implode("\n\n", $sections);
    }

    /**
     * HTML list of item subscriptions.
     *
     * @param array $prefs      Preferences array.
     * @param bool  $email_style Use paragraph tags for email bodies.
     * @return string
     */
    public static function describe_items_html(array $prefs, $email_style = true) {
        $html = '';
        foreach (self::get_item_sections($prefs) as $section) {
            if (empty($section['titles'])) {
                continue;
            }
            $lines = array_map('esc_html', $section['titles']);
            if ($email_style) {
                $html .= '<p><strong>' . esc_html($section['post_type_label']) . '</strong></p><p>' . implode('<br />', $lines) . '</p>';
            } else {
                $html .= '<div class="sn-prefs-block">';
                $html .= '<div class="sn-prefs-post-type">' . esc_html($section['post_type_label']) . '</div>';
                $html .= '<div class="sn-prefs-taxonomies">' . implode('<br />', $lines) . '</div>';
                $html .= '</div>';
            }
        }
        return $html;
    }

    /**
     * Item subscription rows for summaries.
     *
     * @param array $prefs Preferences array.
     * @return array<int, array{post_type_label: string, titles: string[]}>
     */
    public static function get_item_sections(array $prefs) {
        $sections = array();
        $types    = array_unique(array_merge(
            SubscriberNotifications_Content_Config::get_single_item_post_types(),
            array_keys($prefs)
        ));
        foreach ($types as $post_type) {
            $items = self::get_items($prefs, $post_type);
            if (empty($items)) {
                continue;
            }
            $titles = array();
            foreach ($items as $post_id) {
                $titles[] = self::get_item_label($post_id);
            }
            $sections[] = array(
                'post_type_label' => SubscriberNotifications_Content_Config::get_post_type_label($post_type),
                'titles'          => $titles,
            );
        }
        return $sections;
    }

    /**
     * Defensive normalization of preferences (terms + _items per post type).
     *
     * @param array $prefs Raw prefs.
     * @return array
     */
    public static function normalize(array $prefs) {
        $out = array();
        foreach ($prefs as $post_type => $taxonomies) {
            if (!is_string($post_type) || !is_array($taxonomies)) {
                continue;
            }
            $clean_tax = array();
            foreach ($taxonomies as $tax => $ids) {
                if ($tax === self::ITEMS_KEY) {
                    if (is_array($ids)) {
                        $item_clean = self::sanitize_item_id_list($ids);
                        if (!empty($item_clean)) {
                            $clean_tax[ self::ITEMS_KEY ] = $item_clean;
                        }
                    }
                    continue;
                }
                if (!is_string($tax) || !is_array($ids)) {
                    continue;
                }
                $cleaned = array();
                foreach ($ids as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $cleaned[ $id ] = $id;
                    }
                }
                if (!empty($cleaned)) {
                    sort($cleaned);
                    $clean_tax[ $tax ] = array_values($cleaned);
                }
            }
            if (!empty($clean_tax)) {
                $out[ $post_type ] = $clean_tax;
            }
        }
        return $out;
    }
}

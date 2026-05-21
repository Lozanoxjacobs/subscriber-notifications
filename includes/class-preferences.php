<?php
/**
 * Preferences helpers.
 *
 * Subscriber and notification target preferences are stored as JSON in the
 * subscribers.subscription_preferences and queue.target_preferences columns.
 *
 * Shape: `{ "post_type": { "taxonomy": [ term_id, ... ] } }`
 *
 * This class handles encoding, decoding, POST sanitization, overlap matching,
 * orphan pruning, and post-to-preferences resolution.
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
     * Sanitize a raw POST preferences array.
     *
     * Accepts the nested shape `preferences[post_type][taxonomy][] = term_id`.
     * Cleans keys with sanitize_key(), casts term IDs to positive ints, drops
     * empty taxonomy slots and empty post type entries.
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
            $clean_tax = array();
            foreach ($taxonomies as $tax => $term_ids) {
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
                        $ids[$id] = $id;
                    }
                }
                if (!empty($ids)) {
                    sort($ids);
                    $clean_tax[$tax] = array_values($ids);
                }
            }
            if (!empty($clean_tax)) {
                $out[$post_type] = $clean_tax;
            }
        }
        return $out;
    }

    /**
     * Drop term IDs that are no longer in the configured allowed set.
     *
     * @param array  $prefs   Preferences array.
     * @param string $context Either `admin` (default, full configured set) or
     *                        `public` (additionally honors the global "hide
     *                        empty" setting). Public callers should pass
     *                        `public` so submitted IDs that are hidden on the
     *                        form are dropped server-side too.
     * @return array Pruned preferences.
     */
    public static function prune_to_allowed_terms(array $prefs, $context = 'admin') {
        $out = array();
        foreach ($prefs as $post_type => $taxonomies) {
            if (!in_array($post_type, SubscriberNotifications_Content_Config::get_enabled_post_types(), true)) {
                continue;
            }
            $allowed_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            if (empty($allowed_taxonomies) || !is_array($taxonomies)) {
                continue;
            }
            $clean_tax = array();
            foreach ($taxonomies as $tax => $term_ids) {
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
                    $clean_tax[$tax] = $kept;
                }
            }
            if (!empty($clean_tax)) {
                $out[$post_type] = $clean_tax;
            }
        }
        return $out;
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
            foreach ($taxonomies as $term_ids) {
                if (is_array($term_ids) && !empty($term_ids)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * OR-overlap matching for digests and notifications.
     *
     * Returns true if the subscriber and target preferences share at least one
     * (post_type, taxonomy, term_id) triple. Orphan IDs (not in the current
     * allowed set) are ignored.
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
            if (empty($subscriber_prefs[$post_type]) || !is_array($taxonomies)) {
                continue;
            }
            $allowed_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            foreach ($taxonomies as $tax => $target_ids) {
                if (!is_array($target_ids) || empty($target_ids)) {
                    continue;
                }
                if (!in_array($tax, $allowed_taxonomies, true)) {
                    continue;
                }
                if (empty($subscriber_prefs[$post_type][$tax]) || !is_array($subscriber_prefs[$post_type][$tax])) {
                    continue;
                }
                $allowed_ids = SubscriberNotifications_Term_Resolver::get_allowed_term_ids($post_type, $tax);
                $target_valid = array_intersect(array_map('intval', $target_ids), $allowed_ids);
                $sub_valid    = array_intersect(array_map('intval', $subscriber_prefs[$post_type][$tax]), $allowed_ids);
                $overlap      = array_intersect($target_valid, $sub_valid);
                if (!empty($overlap)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Resolve a single post's term IDs into a preferences-shaped array, scoped to its post type
     * and the taxonomies enabled on the form for that post type.
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
        $post_type = $post->post_type;
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
            $entry[$tax] = $term_ids;
        }
        if (empty($entry)) {
            return array();
        }
        return array($post_type => $entry);
    }

    /**
     * Flatten preferences into `post_type:taxonomy => [ term_id, ... ]`.
     *
     * Useful for CSV export and human-readable summaries.
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
                if (!is_array($ids) || empty($ids)) {
                    continue;
                }
                $key = $post_type . ':' . $tax;
                $out[$key] = array_values(array_unique(array_map('intval', $ids)));
            }
        }
        return $out;
    }

    /**
     * Human-readable description of selected terms. Delegates to Term_Resolver.
     *
     * @param array|string $prefs Preferences array or JSON.
     * @return string
     */
    public static function human_readable($prefs) {
        if (!is_array($prefs)) {
            $prefs = self::decode($prefs);
        }
        return SubscriberNotifications_Term_Resolver::describe_selection($prefs);
    }

    /**
     * HTML summary of preferences for email shortcodes.
     *
     * @param array|string $prefs Preferences array or JSON.
     * @return string
     */
    public static function human_readable_html($prefs) {
        if (!is_array($prefs)) {
            $prefs = self::decode($prefs);
        }
        return SubscriberNotifications_Term_Resolver::describe_selection_html($prefs);
    }

    /**
     * Defensive normalization (idempotent) of a preferences-shaped array.
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
                if (!is_string($tax) || !is_array($ids)) {
                    continue;
                }
                $cleaned = array();
                foreach ($ids as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $cleaned[$id] = $id;
                    }
                }
                if (!empty($cleaned)) {
                    sort($cleaned);
                    $clean_tax[$tax] = array_values($cleaned);
                }
            }
            if (!empty($clean_tax)) {
                $out[$post_type] = $clean_tax;
            }
        }
        return $out;
    }
}

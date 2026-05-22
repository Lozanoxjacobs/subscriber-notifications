<?php
/**
 * CSV import/export handler
 *
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSV handler class for importing and exporting subscribers.
 *
 * Dynamic columns of the form "post_type:taxonomy" carry comma-separated term
 * names. The first row is always: name, email, frequency, followed by one
 * dynamic column per configured post_type/taxonomy pairing.
 */
class SubscriberNotifications_CSV_Handler {

    /** @var SubscriberNotifications_Database */
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    /**
     * Import subscribers from CSV.
     *
     * @param string $csv_file_path Path to CSV file.
     * @return array Import result.
     */
    public function import_subscribers($csv_file_path) {
        if (!file_exists($csv_file_path)) {
            return array(
                'success' => false,
                'message' => __('CSV file not found.', 'subscriber-notifications'),
            );
        }

        if (!class_exists('SubscriberNotifications_Content_Config') || !SubscriberNotifications_Content_Config::is_configured()) {
            return array(
                'success' => false,
                'message' => __('No content types are configured. Configure content types before importing.', 'subscriber-notifications'),
            );
        }

        $handle = fopen($csv_file_path, 'r');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Could not open CSV file.', 'subscriber-notifications'),
            );
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return array(
                'success' => false,
                'message' => __('Invalid CSV format.', 'subscriber-notifications'),
            );
        }

        $missing = array_diff(array('name', 'email'), $headers);
        if (!empty($missing)) {
            fclose($handle);
            return array(
                'success' => false,
                'message' => sprintf(__('Missing required columns: %s', 'subscriber-notifications'), implode(', ', $missing)),
            );
        }

        $term_columns = $this->get_term_columns($headers);
        $imported_count = 0;
        $errors = array();
        $row_number = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;

            if (empty(array_filter($row, function($v) { return $v !== '' && $v !== null; }))) {
                continue;
            }

            $row = $this->normalize_csv_row($row, $headers);

            $data = array_combine($headers, $row);

            if (empty($data['name']) || empty($data['email'])) {
                $errors[] = sprintf(__('Row %d: Missing required fields', 'subscriber-notifications'), $row_number);
                continue;
            }

            if (!is_email($data['email'])) {
                $errors[] = sprintf(__('Row %d: Invalid email address', 'subscriber-notifications'), $row_number);
                continue;
            }

            if ($this->database->get_subscriber_by_email($data['email'])) {
                $errors[] = sprintf(__('Row %d: Email already exists', 'subscriber-notifications'), $row_number);
                continue;
            }

            $preferences = $this->build_preferences_from_row($data, $term_columns);
            $preferences = SubscriberNotifications_Preferences::sanitize_from_post($preferences);
            $pruned = SubscriberNotifications_Preferences::prune_to_allowed_terms($preferences);

            if (!SubscriberNotifications_Preferences::has_at_least_one_term($pruned)) {
                $errors[] = sprintf(__('Row %d: At least one valid term must be selected across configured taxonomies', 'subscriber-notifications'), $row_number);
                continue;
            }

            $frequency = !empty($data['frequency']) ? sanitize_text_field($data['frequency']) : 'weekly';
            if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true)) {
                $frequency = 'weekly';
            }

            $subscriber_data = array(
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'subscription_preferences' => $pruned,
                'frequency' => $frequency,
                'status' => 'active',
                'management_token' => wp_generate_password(32, false),
            );

            $result = $this->database->add_subscriber($subscriber_data);
            if ($result) {
                $imported_count++;
            } else {
                $errors[] = sprintf(__('Row %d: Failed to import', 'subscriber-notifications'), $row_number);
            }
        }

        fclose($handle);

        return array(
            'success' => true,
            'count' => $imported_count,
            'errors' => $errors,
        );
    }

    /**
     * Export subscribers to CSV.
     *
     * @param array $args Export arguments.
     * @return array|false Export result or false on failure.
     */
    public function export_subscribers($args = array()) {
        $defaults = array(
            'status' => 'active',
            'format' => 'csv',
        );

        $args = wp_parse_args($args, $defaults);

        $subscribers = $this->database->get_subscribers(array(
            'status' => $args['status'],
            'limit' => 1000,
        ));

        if ($args['format'] === 'csv') {
            return $this->export_csv($subscribers);
        }

        return false;
    }

    /**
     * Write subscribers to a CSV file with dynamic columns.
     */
    private function export_csv($subscribers) {
        $filename = 'subscribers_' . wp_date('Y-m-d_H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/' . $filename;

        if (!wp_mkdir_p($upload_dir['basedir'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Could not create upload directory');
            }
            return false;
        }

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications: Could not create CSV file');
            }
            return false;
        }

        $term_columns = $this->get_configured_term_columns();

        $headers = array('id', 'name', 'email', 'user_id', 'frequency', 'status', 'date_added', 'last_notified');
        foreach ($term_columns as $col) {
            $headers[] = $col['post_type'] . ':' . $col['taxonomy'];
        }
        fputcsv($handle, $headers);

        foreach ($subscribers as $subscriber) {
            $prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');

            $row = array(
                $subscriber->id,
                $subscriber->name,
                $subscriber->email,
                isset($subscriber->user_id) ? absint($subscriber->user_id) : '',
                $subscriber->frequency,
                $subscriber->status,
                $subscriber->date_added,
                $subscriber->last_notified,
            );

            foreach ($term_columns as $col) {
                $term_ids = isset($prefs[$col['post_type']][$col['taxonomy']]) ? (array) $prefs[$col['post_type']][$col['taxonomy']] : array();
                $row[] = $this->term_ids_to_names($term_ids, $col['taxonomy']);
            }

            fputcsv($handle, $row);
        }

        fclose($handle);

        return array(
            'filepath' => $filepath,
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename,
        );
    }

    /**
     * Pad or trim a CSV data row so it matches the header column count.
     *
     * Trailing commas in spreadsheet exports often produce extra empty cells;
     * without this, array_combine() throws when keys and values differ in length.
     *
     * @param array $row     Parsed CSV row.
     * @param array $headers Header row.
     * @return array
     */
    private function normalize_csv_row(array $row, array $headers) {
        $header_count = count($headers);
        if ($header_count === 0) {
            return $row;
        }
        if (count($row) < $header_count) {
            return array_pad($row, $header_count, '');
        }
        if (count($row) > $header_count) {
            return array_slice($row, 0, $header_count);
        }
        return $row;
    }

    /**
     * Identify the dynamic term columns present in a header row.
     *
     * @param array $headers Header row from CSV.
     * @return array<int, array{post_type:string,taxonomy:string,index:int}>
     */
    private function get_term_columns(array $headers) {
        $columns = array();
        foreach ($headers as $index => $header) {
            if (strpos($header, ':') === false) {
                continue;
            }
            list($post_type, $taxonomy) = array_map('trim', explode(':', $header, 2));
            if ($post_type === '' || $taxonomy === '') {
                continue;
            }
            $columns[] = array(
                'header' => $header,
                'post_type' => $post_type,
                'taxonomy' => $taxonomy,
                'index' => $index,
            );
        }
        return $columns;
    }

    /**
     * Get all configured post_type/taxonomy pairs for export headers.
     *
     * @return array<int, array{post_type:string,taxonomy:string}>
     */
    private function get_configured_term_columns() {
        $columns = array();
        if (!class_exists('SubscriberNotifications_Content_Config')) {
            return $columns;
        }
        foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
            foreach (SubscriberNotifications_Content_Config::get_form_taxonomies($post_type) as $taxonomy) {
                $columns[] = array(
                    'post_type' => $post_type,
                    'taxonomy' => $taxonomy,
                );
            }
        }
        return $columns;
    }

    /**
     * Convert row data into the structured preferences array.
     */
    private function build_preferences_from_row(array $data, array $term_columns) {
        $preferences = array();

        foreach ($term_columns as $col) {
            $raw_value = isset($data[$col['header']]) ? trim((string) $data[$col['header']]) : '';
            if ($raw_value === '') {
                continue;
            }

            $names = array_filter(array_map('trim', explode(',', $raw_value)));
            $ids = array();
            foreach ($names as $name) {
                $term = get_term_by('name', $name, $col['taxonomy']);
                if ($term && !is_wp_error($term)) {
                    $ids[] = (int) $term->term_id;
                }
            }

            if (!empty($ids)) {
                if (!isset($preferences[$col['post_type']])) {
                    $preferences[$col['post_type']] = array();
                }
                $preferences[$col['post_type']][$col['taxonomy']] = array_values(array_unique($ids));
            }
        }

        return $preferences;
    }

    /**
     * Convert a list of term IDs to a comma-separated string of names.
     */
    private function term_ids_to_names(array $term_ids, $taxonomy) {
        if (empty($term_ids)) {
            return '';
        }
        $names = array();
        foreach ($term_ids as $id) {
            $term = get_term((int) $id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $names[] = $term->name;
            }
        }
        return implode(', ', $names);
    }
}

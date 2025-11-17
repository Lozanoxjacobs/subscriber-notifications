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
 * CSV handler class for importing and exporting subscribers
 */
class SubscriberNotifications_CSV_Handler {
    
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
    }
    
    /**
     * Import subscribers from CSV
     * 
     * @param string $csv_file_path Path to CSV file
     * @return array Import result
     */
    public function import_subscribers($csv_file_path) {
        if (!file_exists($csv_file_path)) {
            return array(
                'success' => false,
                'message' => __('CSV file not found.', 'subscriber-notifications')
            );
        }
        
        $imported_count = 0;
        $errors = array();
        
        $handle = fopen($csv_file_path, 'r');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Could not open CSV file.', 'subscriber-notifications')
            );
        }
        
        // Get header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return array(
                'success' => false,
                'message' => __('Invalid CSV format.', 'subscriber-notifications')
            );
        }
        
        // Validate required columns
        $required_columns = array('name', 'email');
        $missing_columns = array_diff($required_columns, $headers);
        if (!empty($missing_columns)) {
            fclose($handle);
            return array(
                'success' => false,
                'message' => sprintf(__('Missing required columns: %s', 'subscriber-notifications'), implode(', ', $missing_columns))
            );
        }
        
        // Process each row
        $row_number = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Create associative array
            $data = array_combine($headers, $row);
            
            // Validate required fields
            if (empty($data['name']) || empty($data['email'])) {
                $errors[] = sprintf(__('Row %d: Missing required fields', 'subscriber-notifications'), $row_number);
                continue;
            }
            
            // Validate email
            if (!is_email($data['email'])) {
                $errors[] = sprintf(__('Row %d: Invalid email address', 'subscriber-notifications'), $row_number);
                continue;
            }
            
            // Check if email already exists
            $existing = $this->database->get_subscriber_by_email($data['email']);
            if ($existing) {
                $errors[] = sprintf(__('Row %d: Email already exists', 'subscriber-notifications'), $row_number);
                continue;
            }
            
            // Prepare subscriber data
            $subscriber_data = array(
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'news_categories' => isset($data['news_categories']) ? $this->parse_categories($data['news_categories'], 'post') : '',
                'meeting_categories' => isset($data['meeting_categories']) ? $this->parse_categories($data['meeting_categories'], 'tribe_events_cat') : '',
                'frequency' => !empty($data['frequency']) ? sanitize_text_field($data['frequency']) : 'weekly',
                'status' => 'active', // Import as active
                'management_token' => wp_generate_password(32, false)
            );
            
            // Validate frequency
            if (!in_array($subscriber_data['frequency'], array('daily', 'weekly', 'monthly'))) {
                $subscriber_data['frequency'] = 'weekly';
            }
            
            // Add subscriber
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
            'errors' => $errors
        );
    }
    
    /**
     * Export subscribers to CSV
     * 
     * @param array $args Export arguments
     * @return array|false Export result or false on failure
     */
    public function export_subscribers($args = array()) {
        $defaults = array(
            'status' => 'active',
            'format' => 'csv'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $subscribers = $this->database->get_subscribers(array(
            'status' => $args['status'],
            'limit' => 1000 // Export limit
        ));
        
        if ($args['format'] === 'csv') {
            return $this->export_csv($subscribers);
        }
        
        return false;
    }
    
    /**
     * Export subscribers to CSV file
     * 
     * @param array $subscribers Array of subscriber objects
     * @return array|false Export result or false on failure
     */
    private function export_csv($subscribers) {
        $filename = 'subscribers_' . date('Y-m-d_H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/' . $filename;
        
        // Ensure upload directory exists and is writable
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
        
        // Write headers
        fputcsv($handle, array(
            'id',
            'name',
            'email',
            'news_categories',
            'meeting_categories',
            'frequency',
            'status',
            'date_added',
            'last_notified'
        ));
        
        // Write data
        foreach ($subscribers as $subscriber) {
            $news_categories = $this->get_category_names($subscriber->news_categories, 'post');
            $meeting_categories = $this->get_category_names($subscriber->meeting_categories, 'tribe_events_cat');
            
            fputcsv($handle, array(
                $subscriber->id,
                $subscriber->name,
                $subscriber->email,
                $news_categories,
                $meeting_categories,
                $subscriber->frequency,
                $subscriber->status,
                $subscriber->date_added,
                $subscriber->last_notified
            ));
        }
        
        fclose($handle);
        
        return array(
            'filepath' => $filepath,
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename
        );
    }
    
    /**
     * Parse categories from string
     * 
     * @param string $categories_string Comma-separated category names
     * @param string $taxonomy Taxonomy name
     * @return string Comma-separated category IDs
     */
    private function parse_categories($categories_string, $taxonomy) {
        if (empty($categories_string)) {
            return '';
        }
        
        $category_names = array_map('trim', explode(',', $categories_string));
        $category_ids = array();
        
        foreach ($category_names as $name) {
            if ($taxonomy === 'post') {
                $term = get_term_by('name', $name, 'category');
            } else {
                $term = get_term_by('name', $name, 'tribe_events_cat');
            }
            
            if ($term) {
                $category_ids[] = $term->term_id;
            }
        }
        
        return implode(',', $category_ids);
    }
    
    /**
     * Get category names from IDs
     * 
     * @param string $category_ids Comma-separated category IDs
     * @param string $taxonomy Taxonomy name
     * @return string Comma-separated category names
     */
    private function get_category_names($category_ids, $taxonomy) {
        if (empty($category_ids)) {
            return '';
        }
        
        $ids = explode(',', $category_ids);
        $names = array();
        
        foreach ($ids as $id) {
            if ($taxonomy === 'post') {
                $term = get_category($id);
            } else {
                $term = get_term($id, 'tribe_events_cat');
            }
            
            if ($term) {
                $names[] = $term->name;
            }
        }
        
        return implode(', ', $names);
    }
}
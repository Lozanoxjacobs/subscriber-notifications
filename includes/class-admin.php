<?php
/**
 * Admin interface class
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for managing plugin admin interface
 */
class SubscriberNotifications_Admin {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_test_sendgrid_connection', array($this, 'test_sendgrid_connection'));
        add_action('wp_ajax_test_wp_mail', array($this, 'test_wp_mail'));
        add_action('wp_ajax_get_notification_preview', array($this, 'get_notification_preview'));
        add_action('wp_ajax_send_preview_email', array($this, 'send_preview_email'));
        add_action('wp_ajax_save_notification', array($this, 'ajax_save_notification'));
        add_action('wp_ajax_update_notification', array($this, 'ajax_update_notification'));
        add_action('wp_ajax_subscriber_notifications_export_csv', array($this, 'export_csv'));
        
        // Restrict media library for header logo uploads
        add_filter('wp_handle_upload_prefilter', array($this, 'restrict_header_logo_upload'));
        
        // Screen Options for pagination
        add_action('current_screen', array($this, 'action_screen_options'));
        add_filter('set-screen-option', array($this, 'filter_save_screen_options'), 10, 3);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Subscriber Notifications', 'subscriber-notifications'),
            __('Notifications', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications',
            array($this, 'dashboard_page'),
            'dashicons-email-alt',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Dashboard', 'subscriber-notifications'),
            __('Dashboard', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications',
            array($this, 'dashboard_page')
        );
        
        // Subscribers submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Subscribers', 'subscriber-notifications'),
            __('Subscribers', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-subscribers',
            array($this, 'subscribers_page')
        );
        
        // Notifications submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Notifications', 'subscriber-notifications'),
            __('Notifications', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-notifications',
            array($this, 'notifications_page')
        );
        
        // Create notification submenu (hidden from menu - accessible via Add New button)
        add_submenu_page(
            null,
            __('Create Notification', 'subscriber-notifications'),
            __('Create Notification', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-create',
            array($this, 'create_notification_page')
        );
        
        // Edit notification submenu (hidden from menu)
        add_submenu_page(
            null,
            __('Edit Notification', 'subscriber-notifications'),
            __('Edit Notification', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-edit',
            array($this, 'edit_notification_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Email Logs', 'subscriber-notifications'),
            __('Email Logs', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-logs',
            array($this, 'logs_page')
        );
        
        // Import/Export submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Import/Export', 'subscriber-notifications'),
            __('Import/Export', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-import-export',
            array($this, 'import_export_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'subscriber-notifications',
            __('Settings', 'subscriber-notifications'),
            __('Settings', 'subscriber-notifications'),
            'manage_options',
            'subscriber-notifications-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'subscriber-notifications') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Enqueue WordPress editor scripts for WYSIWYG
        wp_enqueue_script('editor');
        wp_enqueue_script('word-count');
        
        // Enqueue WordPress media library scripts for image upload
        wp_enqueue_media();
        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');
        
        wp_enqueue_script(
            'subscriber-notifications-admin',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SUBSCRIBER_NOTIFICATIONS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'subscriber-notifications-admin',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SUBSCRIBER_NOTIFICATIONS_VERSION
        );
        
        wp_localize_script('subscriber-notifications-admin', 'subscriberNotifications', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subscriber_notifications_nonce'),
            'siteTitle' => get_bloginfo('name')
        ));
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle export logs action (must be early, before any output)
        if (isset($_GET['page']) && $_GET['page'] === 'subscriber-notifications-logs' 
            && isset($_GET['action']) && $_GET['action'] === 'export') {
            $this->export_logs();
            // export_logs() will exit, so we won't reach here
        }
        
        // Handle subscriber actions
        if (isset($_POST['action']) && isset($_POST['subscriber_id'])) {
            $this->handle_subscriber_actions();
        }
        
        // Handle notification creation
        if (isset($_POST['create_notification'])) {
            $this->handle_notification_creation();
        }
        
        // Handle notification actions
        if (isset($_POST['notification_action']) && isset($_POST['notification_id'])) {
            $this->handle_notification_actions();
        }
        
        // Handle notification update
        if (isset($_POST['update_notification'])) {
            $this->handle_notification_update();
        }
        
        // Handle settings save
        if (isset($_POST['save_settings'])) {
            $this->handle_settings_save();
        }
        
        // Handle CSV import
        if (isset($_POST['import_csv'])) {
            $this->handle_csv_import();
        }
    }
    
    /**
     * Handle subscriber actions
     */
    private function handle_subscriber_actions() {
        $action = sanitize_text_field($_POST['action']);
        $subscriber_id = intval($_POST['subscriber_id']);
        
        
        if (!wp_verify_nonce($_POST['subscriber_nonce'], 'subscriber_action')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        switch ($action) {
            case 'activate':
                $this->database->update_subscriber($subscriber_id, array('status' => 'active'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __('Subscriber activated successfully.', 'subscriber-notifications') . '</p></div>';
                });
                // Redirect to show all statuses so user can see the change
                wp_redirect(admin_url('admin.php?page=subscriber-notifications-subscribers'));
                exit;
                break;
                
            case 'unsubscribe':
                $this->database->update_subscriber($subscriber_id, array('status' => 'inactive'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __('Subscriber unsubscribed successfully.', 'subscriber-notifications') . '</p></div>';
                });
                // Redirect to show all statuses so user can see the change
                wp_redirect(admin_url('admin.php?page=subscriber-notifications-subscribers'));
                exit;
                break;
                
            case 'subscribe':
                $this->database->update_subscriber($subscriber_id, array('status' => 'active'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __('Subscriber subscribed successfully.', 'subscriber-notifications') . '</p></div>';
                });
                // Redirect to show all statuses so user can see the change
                wp_redirect(admin_url('admin.php?page=subscriber-notifications-subscribers'));
                exit;
                break;
                
            case 'delete':
                $this->database->delete_subscriber($subscriber_id);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __('Subscriber deleted successfully.', 'subscriber-notifications') . '</p></div>';
                });
                // Redirect to show all statuses so user can see the change
                wp_redirect(admin_url('admin.php?page=subscriber-notifications-subscribers'));
                exit;
                break;
        }
    }
    
    /**
     * Handle notification actions
     */
    private function handle_notification_actions() {
        $action = sanitize_text_field($_POST['notification_action']);
        $notification_id = intval($_POST['notification_id']);
        
        if (!wp_verify_nonce($_POST['notification_nonce'], 'notification_action')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        global $wpdb;
        $notifications_table = $wpdb->prefix . 'subscriber_notifications_queue';
        
        switch ($action) {
            case 'delete':
                $result = $wpdb->delete(
                    $notifications_table,
                    array('id' => $notification_id),
                    array('%d')
                );
                
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Notification deleted successfully.', 'subscriber-notifications') . '</p></div>';
                    });
                }
                break;
                
            case 'cancel':
                $result = $wpdb->update(
                    $notifications_table,
                    array('status' => 'cancelled'),
                    array('id' => $notification_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Notification cancelled successfully.', 'subscriber-notifications') . '</p></div>';
                    });
                }
                break;
                
            case 'resend':
                $result = $wpdb->update(
                    $notifications_table,
                    array('status' => 'pending'),
                    array('id' => $notification_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Notification queued for resending.', 'subscriber-notifications') . '</p></div>';
                    });
                }
                break;
                
            case 'reactivate':
                $result = $wpdb->update(
                    $notifications_table,
                    array('status' => 'pending'),
                    array('id' => $notification_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Notification reactivated successfully.', 'subscriber-notifications') . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Handle notification update
     */
    private function handle_notification_update() {
        if (!wp_verify_nonce($_POST['notification_nonce'], 'update_notification')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        
        // Fetch current notification state before updating (including frequency_target for comparison)
        global $wpdb;
        $current_notification = $wpdb->get_row($wpdb->prepare(
            "SELECT status, next_send_date, is_recurring, frequency_target FROM {$wpdb->prefix}subscriber_notifications_queue WHERE id = %d",
            $notification_id
        ));
        
        // Validate notification exists
        if (!$current_notification) {
            wp_die(__('Notification not found.', 'subscriber-notifications'));
        }
        
        $title = sanitize_text_field($_POST['notification_title']);
        $subject = sanitize_textarea_field($_POST['notification_subject']);
        $content = wp_kses_post($_POST['notification_content']);
        $news_categories = isset($_POST['news_categories']) ? array_map('sanitize_text_field', $_POST['news_categories']) : array();
        $meeting_categories = isset($_POST['meeting_categories']) ? array_map('sanitize_text_field', $_POST['meeting_categories']) : array();
        $frequency_target = sanitize_text_field($_POST['frequency_target']);
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        
        // Determine next_send_date based on current state and new settings
        $next_send_date = $current_notification->next_send_date; // Preserve by default
        
        // Only recalculate if notification is pending and will be recurring
        if ($current_notification->status === 'pending' && $is_recurring && in_array($frequency_target, ['daily', 'weekly', 'monthly'])) {
            // Determine if recalculation is needed
            $should_recalculate = false;
            
            // Check if frequency changed
            if ($current_notification->frequency_target !== $frequency_target) {
                $should_recalculate = true;
            }
            // Check if converting from one-time to recurring
            elseif ($current_notification->is_recurring == 0) {
                $should_recalculate = true;
            }
            // Check if existing date is null or in the past
            elseif ($current_notification->next_send_date === null) {
                $should_recalculate = true;
            }
            elseif ($current_notification->next_send_date !== null) {
                $existing_timestamp = strtotime($current_notification->next_send_date);
                $current_timestamp = current_time('timestamp');
                if ($existing_timestamp <= $current_timestamp) {
                    $should_recalculate = true;
                }
            }
            
            if ($should_recalculate) {
                // Recalculate next_send_date for pending recurring notifications
                $next_send_date = $this->calculate_next_send_date($frequency_target);
                
                // Add 5-minute safety buffer to prevent immediate sending due to race conditions
                $calculated_timestamp = strtotime($next_send_date);
                $current_timestamp = current_time('timestamp');
                $buffer_seconds = 300; // 5 minutes
                
                if ($calculated_timestamp <= ($current_timestamp + $buffer_seconds)) {
                    // If calculated date is too close to now, add buffer
                    $next_send_date = date('Y-m-d H:i:s', $current_timestamp + $buffer_seconds);
                }
            }
            // Otherwise preserve existing next_send_date (already set above)
        } elseif (!$is_recurring) {
            // Converting to one-time: clear next_send_date
            $next_send_date = null;
        }
        // If status is 'sent' or 'cancelled', preserve existing next_send_date (already set above)
        
        $result = $wpdb->update(
            $wpdb->prefix . 'subscriber_notifications_queue',
            array(
                'title' => $title,
                'subject' => $subject,
                'content' => $content,
                'news_categories' => implode(',', $news_categories),
                'meeting_categories' => implode(',', $meeting_categories),
                'frequency_target' => $frequency_target,
                'is_recurring' => $is_recurring,
                'next_send_date' => $next_send_date
            ),
            array('id' => $notification_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Notification updated successfully.', 'subscriber-notifications') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Failed to update notification.', 'subscriber-notifications') . '</p></div>';
            });
        }
    }
    
    /**
     * Handle notification creation
     */
    private function handle_notification_creation() {
        if (!wp_verify_nonce($_POST['notification_nonce'], 'create_notification')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        $title = sanitize_text_field($_POST['notification_title']);
        $subject = sanitize_textarea_field($_POST['notification_subject']);
        $content = wp_kses_post($_POST['notification_content']);
        $news_categories = isset($_POST['news_categories']) ? array_map('sanitize_text_field', $_POST['news_categories']) : array();
        $meeting_categories = isset($_POST['meeting_categories']) ? array_map('sanitize_text_field', $_POST['meeting_categories']) : array();
        $frequency_target = sanitize_text_field($_POST['frequency_target']);
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        
        // Calculate next send date for recurring notifications
        $next_send_date = null;
        if ($is_recurring && in_array($frequency_target, ['daily', 'weekly', 'monthly'])) {
            $next_send_date = $this->calculate_next_send_date($frequency_target);
        }
        
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'subscriber_notifications_queue',
            array(
                'title' => $title,
                'subject' => $subject,
                'content' => $content,
                'news_categories' => implode(',', $news_categories),
                'meeting_categories' => implode(',', $meeting_categories),
                'frequency_target' => $frequency_target,
                'status' => 'pending',
                'created_by' => get_current_user_id(),
                'is_recurring' => $is_recurring,
                'next_send_date' => $next_send_date,
                'recurrence_count' => 0
            )
        );
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Notification created successfully.', 'subscriber-notifications') . '</p></div>';
            });
        }
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['settings_nonce'], 'save_settings')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        // Get active tab from URL or default to 'general'
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Define which options belong to each tab
        $tab_options = array(
            'general' => array(
                'mail_method',
                'sendgrid_api_key',
                'sendgrid_from_email',
                'sendgrid_from_name',
                'rate_limit_hours',
                'test_email',
                'delete_data_on_uninstall'
            ),
            'email-templates' => array(
                'welcome_email_subject',
                'welcome_email_content',
                'welcome_back_email_subject',
                'welcome_back_email_content',
                'preferences_update_email_subject',
                'preferences_update_email_content'
            ),
            'scheduling' => array(
                'daily_send_time',
                'weekly_send_day',
                'weekly_send_time',
                'monthly_send_day',
                'monthly_send_time'
            ),
            'security' => array(
                'captcha_site_key',
                'captcha_secret_key'
            ),
            'email-design' => array(
                'global_header_logo',
                'global_header_content',
                'global_footer',
                'email_css'
            )
        );
        
        // Only process settings for the current tab
        if (!isset($tab_options[$current_tab])) {
            $current_tab = 'general';
        }
        
        $options_to_save = $tab_options[$current_tab];
        $scheduling_fields_changed = array();
        
        // Process each option in the current tab
        foreach ($options_to_save as $option) {
            // Handle checkboxes - they don't send a value when unchecked
            if ($option === 'delete_data_on_uninstall') {
                $new_value = isset($_POST[$option]) ? 1 : 0;
                $old_value = get_option($option, 0);
                
                // Only update if value actually changed
                if ($old_value !== $new_value) {
                    update_option($option, $new_value);
                }
                continue;
            }
            
            if (!isset($_POST[$option])) {
                continue;
            }
            
            // Get the sanitization callback
            $sanitize_callback = 'sanitize_setting_' . $option;
            if (method_exists($this, $sanitize_callback)) {
                // Get old value before sanitizing new value
                $old_value = get_option($option);
                $new_value = $this->$sanitize_callback($_POST[$option]);
                
                // Normalize values for comparison (handle type mismatches)
                // For integer fields, ensure both are integers
                if ($option === 'monthly_send_day') {
                    $old_value = intval($old_value);
                    $new_value = intval($new_value);
                }
                
                // Only update if value actually changed
                if ($old_value !== $new_value) {
                    update_option($option, $new_value);
                    
                    // Track which specific scheduling fields changed
                    if ($current_tab === 'scheduling') {
                        $scheduling_fields_changed[] = $option;
                    }
                }
            }
        }
        
        // Only reschedule cron jobs for fields that actually changed
        if (!empty($scheduling_fields_changed)) {
            // Determine which frequencies will be affected
            $frequencies_to_update = array();
            if (in_array('daily_send_time', $scheduling_fields_changed)) {
                $frequencies_to_update[] = 'daily';
            }
            if (in_array('weekly_send_day', $scheduling_fields_changed) || in_array('weekly_send_time', $scheduling_fields_changed)) {
                $frequencies_to_update[] = 'weekly';
            }
            if (in_array('monthly_send_day', $scheduling_fields_changed) || in_array('monthly_send_time', $scheduling_fields_changed)) {
                $frequencies_to_update[] = 'monthly';
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    "Subscriber Notifications: Scheduling fields changed: %s. Will update frequencies: %s",
                    implode(', ', $scheduling_fields_changed),
                    !empty($frequencies_to_update) ? implode(', ', $frequencies_to_update) : 'none'
                ));
            }
            
            $this->reschedule_cron_jobs($scheduling_fields_changed);
            $this->update_recurring_notifications_schedule($scheduling_fields_changed);
        }
        
        // Redirect back to the same tab
        $redirect_url = add_query_arg(
            array(
                'page' => 'subscriber-notifications-settings',
                'tab' => $current_tab,
                'settings-updated' => 'true'
            ),
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error uploading CSV file.', 'subscriber-notifications') . '</p></div>';
            });
            return;
        }
        
        $csv_handler = new SubscriberNotifications_CSV_Handler($this->database);
        $result = $csv_handler->import_subscribers($_FILES['csv_file']['tmp_name']);
        
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('%d subscribers imported successfully.', 'subscriber-notifications'), $result['count']) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . $result['message'] . '</p></div>';
            });
        }
    }
    
    /**
     * Test SendGrid connection
     */
    public function test_sendgrid_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'subscriber_notifications_nonce')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'subscriber-notifications'));
        }
        
        $sendgrid = new SubscriberNotifications_SendGrid();
        $result = $sendgrid->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Test WordPress mail
     */
    public function test_wp_mail() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_wp_mail')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'subscriber-notifications'));
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        if (empty($test_email)) {
            wp_send_json_error(__('Please enter a test email address.', 'subscriber-notifications'));
        }
        
        // Create a test subscriber object for shortcode processing
        $test_subscriber = (object) array(
            'id' => 0,
            'name' => 'Test User',
            'email' => $test_email,
            'news_categories' => '1,2,3',
            'meeting_categories' => '4,5,6',
            'frequency' => 'weekly',
            'status' => 'active',
            'management_token' => 'test-token'
        );
        
        $subject = __('Test Email from Subscriber Notifications', 'subscriber-notifications');
        
        // Create test content
        $test_content = '<h2>' . __('Test Email Content', 'subscriber-notifications') . '</h2>';
        $test_content .= '<p>' . __('This is a test email to verify that WordPress mail is working correctly and that the email template includes proper styling, global header, and footer.', 'subscriber-notifications') . '</p>';
        $test_content .= '<p><strong>' . __('Test Details:', 'subscriber-notifications') . '</strong></p>';
        $test_content .= '<ul>';
        $test_content .= '<li>' . __('Email sent via WordPress wp_mail()', 'subscriber-notifications') . '</li>';
        $test_content .= '<li>' . __('Template includes global header and footer', 'subscriber-notifications') . '</li>';
        $test_content .= '<li>' . __('Styling applied from custom CSS', 'subscriber-notifications') . '</li>';
        $test_content .= '<li>' . __('Shortcodes processed: [subscriber_name] = ' . $test_subscriber->name . ', [site_title] = ' . get_bloginfo('name'), 'subscriber-notifications') . '</li>';
        $test_content .= '</ul>';
        
        // Use the same email template as notifications
        $css = get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $message = $formatter->wrap_content_with_css($test_content, $css, $test_subscriber);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($test_email, $subject, $message, $headers);
        
        if ($result) {
            wp_send_json_success(__('WordPress mail test successful! Check your inbox to see the styled email with header and footer.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('WordPress mail test failed. Check your server configuration.', 'subscriber-notifications'));
        }
    }
    
    /**
     * Get notification preview
     */
    public function get_notification_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_notification_preview')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'subscriber-notifications'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        
        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue WHERE id = %d",
            $notification_id
        ));
        
        if (!$notification) {
            wp_send_json_error(__('Notification not found.', 'subscriber-notifications'));
        }
        
        // Create a sample subscriber for preview
        $sample_subscriber = (object) array(
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'news_categories' => '1,2,3',
            'meeting_categories' => '4,5,6',
            'frequency' => 'weekly'
        );
        
        // Process shortcodes for preview
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $preview_subject = $shortcodes->process_shortcodes($notification->subject, $sample_subscriber, $notification);
        $preview_content = $shortcodes->process_shortcodes($notification->content, $sample_subscriber, $notification);
        
        // Apply CSS (default CSS or custom CSS)
        $email_css = get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $preview_content = $formatter->wrap_content_with_css($preview_content, $email_css, $sample_subscriber);
        
        $preview_html = '<div class="notification-preview">';
        $preview_html .= '<h3>' . esc_html($notification->title) . '</h3>';
        $preview_html .= '<div class="notification-meta">';
        $preview_html .= '<p><strong>' . __('Status:', 'subscriber-notifications') . '</strong> ' . esc_html(ucfirst($notification->status)) . '</p>';
        $preview_html .= '<p><strong>' . __('Created:', 'subscriber-notifications') . '</strong> ' . esc_html(mysql2date('M j, Y g:i A', $notification->created_date)) . '</p>';
        
        if ($notification->sent_date) {
            $preview_html .= '<p><strong>' . __('Sent:', 'subscriber-notifications') . '</strong> ' . esc_html(mysql2date('M j, Y g:i A', $notification->sent_date)) . '</p>';
        }
        
        if ($notification->frequency_target) {
            $preview_html .= '<p><strong>' . __('Target Frequency:', 'subscriber-notifications') . '</strong> ' . esc_html(ucfirst(str_replace('_', ' ', $notification->frequency_target))) . '</p>';
        }
        
        $preview_html .= '</div>';
        $preview_html .= '<div class="notification-content">';
        $preview_html .= '<h4>' . __('Email Subject:', 'subscriber-notifications') . '</h4>';
        $preview_html .= '<div style="border: 1px solid #ddd; padding: 10px; background: #f0f0f0; margin-bottom: 15px; font-weight: bold;">';
        $preview_html .= esc_html($preview_subject);
        $preview_html .= '</div>';
        $preview_html .= '<h4>' . __('Email Content Preview:', 'subscriber-notifications') . '</h4>';
        $preview_html .= '<div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">';
        $preview_html .= $preview_content;
        $preview_html .= '</div>';
        $preview_html .= '</div>';
        $preview_html .= '</div>';
        
        wp_send_json_success($preview_html);
    }
    
    /**
     * Wrap email content with custom CSS
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->wrap_content_with_css() instead
     * @param string $content Email content
     * @param string $css Custom CSS
     * @param object $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with CSS
     */
    private function wrap_content_with_css($content, $css, $subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->wrap_content_with_css()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->wrap_content_with_css($content, $css, $subscriber);
    }
    
    /**
     * Wrap content with proper email structure
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->wrap_with_email_structure() instead
     * @param string $content Email content
     * @param string $css CSS styles
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with email structure
     */
    private function wrap_with_email_structure($content, $css, $subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->wrap_with_email_structure()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->wrap_with_email_structure($content, $css, $subscriber);
    }
    
    /**
     * Get default CSS
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_default_css() instead
     * @return string Default CSS for emails
     */
    private function get_default_css() {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_default_css()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_default_css();
    }
    
    /**
     * Get default CSS (old implementation - kept for reference)
     * 
     * @deprecated 2.2.0 This method is deprecated and will be removed in version 3.0.0
     * @return string Default CSS for emails
     */
    private function get_default_css_old() {
        return '
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* Email container */
        .email-container {
            max-width: 600px !important;
            width: 100% !important;
            margin: 0 auto !important;
            background-color: #F2F2F2 !important;
        }
        
        /* Email content area */
        .email-content {
            background-color: #F2F2F2 !important;
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
        }
        
        /* Ensure all div and span elements default to body text size */
        .email-content div,
        .email-content span {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
        }
        
        /* Override any inline font-size styles that might be smaller */
        .email-content *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6) {
            font-size: 16px !important;
        }
        
        /* Typography - Default Style Guide */
        body {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        h1 {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 28px !important;
            line-height: 32px !important;
            color: #000000 !important;
            margin: 0 0 20px 0 !important;
        }
        
        h2 {
            font-family: "Kepler Std", "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 38px !important;
            line-height: 39px !important;
            color: #000000 !important;
            margin: 0 0 20px 0 !important;
        }
        
        h3 {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 22px !important;
            line-height: 26px !important;
            color: #000000 !important;
            margin: 0 0 15px 0 !important;
        }
        
        h4 {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            margin: 0 0 15px 0 !important;
        }
        
        h5 {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            line-height: 18px !important;
            color: #000000 !important;
            margin: 0 0 10px 0 !important;
        }
        
        h6 {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            line-height: 16px !important;
            color: #000000 !important;
            margin: 0 0 10px 0 !important;
        }
        
        p {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            margin: 0 0 15px 0 !important;
        }
        
        /* Links */
        a {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            text-decoration: underline !important;
        }
        
        a:hover {
            color: #004EBE !important;
            text-decoration: underline !important;
        }
        
        /* Links on dark background */
        .email-footer a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .email-footer a:hover {
            color: #A4EAFF !important;
            text-decoration: underline !important;
        }
        
        /* Header styling */
        .email-header {
            background-color: #F2F2F2 !important;
            color: #000000 !important;
        }
        
        .email-header h1 {
            color: #000000 !important;
        }
        
        /* Footer text and headings - force white color */
        .email-footer {
            color: #ffffff !important;
        }
        
        .email-footer h1,
        .email-footer h2,
        .email-footer h3,
        .email-footer h4,
        .email-footer h5,
        .email-footer h6 {
            color: #ffffff !important;
        }
        
        .email-footer p {
            color: #ffffff !important;
        }
        
        .email-footer div {
            color: #ffffff !important;
        }
        
        .email-footer span {
            color: #ffffff !important;
        }
        
        /* Buttons - Default Style Guide */
        .primary-button {
            display: inline-block !important;
            background-color: #F02929 !important;
            color: #ffffff !important;
            padding: 12px 24px !important;
            text-decoration: none !important;
            border-radius: 4px !important;
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            line-height: 22px !important;
            text-align: center !important;
            margin: 10px 0 !important;
        }
        
        .primary-button:hover {
            background-color: #D91F1F !important;
            text-decoration: none !important;
        }
        
        .secondary-button {
            display: inline-block !important;
            background-color: #ffffff !important;
            color: #4D4D4D !important;
            padding: 12px 24px !important;
            text-decoration: none !important;
            border: 2px solid #4D4D4D !important;
            border-radius: 4px !important;
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            line-height: 22px !important;
            text-align: center !important;
            margin: 10px 0 !important;
        }
        
        .secondary-button:hover {
            background-color: #F2F2F2 !important;
            text-decoration: none !important;
        }
        
        /* Lists */
        ul, ol {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            margin: 0 0 15px 0 !important;
            padding-left: 20px !important;
        }
        
        li {
            font-family: "Montserrat", Arial, Helvetica, sans-serif !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: #000000 !important;
            margin: 0 0 8px 0 !important;
        }
        
        /* Tables */
        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        
        /* Content sections */
        .content-section {
            margin: 20px 0 !important;
            padding: 20px !important;
            background-color: #ffffff !important;
            border-left: 4px solid #F02929 !important;
        }
        
        .news-item {
            margin: 20px 0 !important;
            padding: 15px !important;
            background-color: #F2F2F2 !important;
            border-radius: 4px !important;
        }
        
        .news-item h3 {
            margin: 0 0 10px 0 !important;
            color: #01228C !important;
        }
        
        .news-item p {
            margin: 0 0 10px 0 !important;
        }
        
        /* Mobile Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .email-content {
                padding: 20px 15px !important;
            }
            
            .email-header,
            .email-footer {
                padding: 15px !important;
            }
            
            h1 {
                font-size: 24px !important;
                line-height: 28px !important;
            }
            
            h2 {
                font-size: 32px !important;
                line-height: 36px !important;
            }
            
            h3 {
                font-size: 20px !important;
                line-height: 24px !important;
            }
            
            .primary-button,
            .secondary-button {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                padding: 15px 20px !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-content {
                background-color: #1a1a1a !important;
            }
            
            body, p, li {
                color: #ffffff !important;
            }
            
            h1, h2, h3, h4, h5, h6 {
                color: #A5EAF7 !important;
            }
        }
        ';
    }
    
    /**
     * Validate header logo attachment
     * 
     * @param string $logo_id Attachment ID
     * @return string Validated attachment ID or empty string
     */
    private function validate_header_logo($logo_id) {
        if (empty($logo_id)) {
            return '';
        }
        
        $logo_id = intval($logo_id);
        
        // Check if attachment exists
        $attachment = get_post($logo_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Invalid logo attachment selected.', 'subscriber-notifications') . '</p></div>';
            });
            return '';
        }
        
        // Check if it's an image
        if (!wp_attachment_is_image($logo_id)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Selected file is not a valid image.', 'subscriber-notifications') . '</p></div>';
            });
            return '';
        }
        
        // Check MIME type for allowed formats
        $mime_type = get_post_mime_type($logo_id);
        $allowed_mimes = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        
        if (!in_array($mime_type, $allowed_mimes)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Logo must be a JPG, PNG, or GIF file. SVG files are not supported for email headers.', 'subscriber-notifications') . '</p></div>';
            });
            return '';
        }
        
        // Get image metadata
        $image_meta = wp_get_attachment_metadata($logo_id);
        if (!$image_meta) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Could not read image metadata.', 'subscriber-notifications') . '</p></div>';
            });
            return '';
        }
        
        // Check dimensions
        $max_width = 700;
        $max_height = 200;
        
        if (isset($image_meta['width']) && $image_meta['width'] > $max_width) {
            add_action('admin_notices', function() use ($image_meta, $max_width) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('Logo width (%dpx) exceeds maximum allowed width (%dpx).', 'subscriber-notifications'), $image_meta['width'], $max_width) . '</p></div>';
            });
            return '';
        }
        
        if (isset($image_meta['height']) && $image_meta['height'] > $max_height) {
            add_action('admin_notices', function() use ($image_meta, $max_height) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('Logo height (%dpx) exceeds maximum allowed height (%dpx).', 'subscriber-notifications'), $image_meta['height'], $max_height) . '</p></div>';
            });
            return '';
        }
        
        // Check file size (200KB limit)
        $file_path = get_attached_file($logo_id);
        if ($file_path && file_exists($file_path)) {
            $file_size = filesize($file_path);
            if ($file_size > 200 * 1024) { // 200KB in bytes
                add_action('admin_notices', function() use ($file_size) {
                    $file_size_kb = round($file_size / 1024, 1);
                    echo '<div class="notice notice-error"><p>' . sprintf(__('Logo file size (%sKB) exceeds maximum allowed size (200KB).', 'subscriber-notifications'), $file_size_kb) . '</p></div>';
                });
                return '';
            }
        }
        
        return $logo_id;
    }
    
    /**
     * Restrict header logo uploads to allowed file types
     * 
     * @param array $file File array from wp_handle_upload_prefilter
     * @return array Modified file array
     */
    public function restrict_header_logo_upload($file) {
        // Only apply restrictions when uploading from our settings page
        if (!isset($_POST['action']) || $_POST['action'] !== 'upload-attachment') {
            return $file;
        }
        
        // Check if this is a header logo upload (we'll identify this by checking the referrer)
        $referrer = wp_get_referer();
        if (strpos($referrer, 'subscriber-notifications-settings') === false) {
            return $file;
        }
        
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        
        if (!in_array($file['type'], $allowed_types)) {
            $file['error'] = __('Only JPG, PNG, and GIF files are allowed for email header logos. SVG files are not supported.', 'subscriber-notifications');
        }
        
        // Check file size (200KB limit)
        if ($file['size'] > 200 * 1024) {
            $file_size_kb = round($file['size'] / 1024, 1);
            $file['error'] = sprintf(__('File size (%sKB) exceeds maximum allowed size (200KB) for email header logos.', 'subscriber-notifications'), $file_size_kb);
        }
        
        return $file;
    }
    
    /**
     * Get global header content
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_global_header_content() instead
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Header HTML
     */
    private function get_global_header_content($subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_global_header_content()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_global_header_content($subscriber);
    }
    
    /**
     * Get global footer content
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->get_global_footer_content() instead
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Footer HTML
     */
    private function get_global_footer_content($subscriber = null) {
        _deprecated_function(__METHOD__, '2.2.0', 'SubscriberNotifications_Email_Formatter::get_instance()->get_global_footer_content()');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        return $formatter->get_global_footer_content($subscriber);
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $total_subscribers = $this->database->get_subscriber_count(array('status' => 'active'));
        $pending_subscribers = $this->database->get_subscriber_count(array('status' => 'pending'));
        $analytics = $this->database->get_analytics_data();
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Subscribers page
     */
    public function subscribers_page() {
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        
        // Get screen option - WordPress stores it via get_user_option
        // get_user_option automatically handles the WordPress prefix
        $per_page = get_user_option('subscriber_notifications_subscribers_per_page');
        if ($per_page === false || empty($per_page) || $per_page < 1) {
            $per_page = 20;
        }
        $per_page = intval($per_page);
        
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : ''
        );
        
        $subscribers = $this->database->get_subscribers($args);
        
        $count_args = array(
            'search' => $args['search'],
            'status' => $args['status']
        );
        $total_subscribers = $this->database->get_subscriber_count($count_args);
        $total_pages = ceil($total_subscribers / $per_page);
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-subscribers.php';
    }
    
    /**
     * Notifications page
     */
    public function notifications_page() {
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        
        // Get screen option - WordPress stores it via get_user_option
        // get_user_option automatically handles the WordPress prefix
        $per_page = get_user_option('subscriber_notifications_notifications_per_page');
        if ($per_page === false || empty($per_page) || $per_page < 1) {
            $per_page = 20;
        }
        $per_page = intval($per_page);
        
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : ''
        );
        
        $notifications = $this->get_notifications($args);
        
        $count_args = array(
            'search' => $args['search'],
            'status' => $args['status']
        );
        $total_notifications = $this->get_notification_count($count_args);
        $total_pages = ceil($total_notifications / $per_page);
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-notifications.php';
    }
    
    /**
     * Create notification page
     */
    public function create_notification_page() {
        $news_categories = get_categories(array('hide_empty' => false));
        $meeting_categories = $this->get_meeting_categories();
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-create-notification.php';
    }
    
    /**
     * Edit notification page
     */
    public function edit_notification_page() {
        $notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$notification_id) {
            wp_die(__('Invalid notification ID.', 'subscriber-notifications'));
        }
        
        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue WHERE id = %d",
            $notification_id
        ));
        
        if (!$notification) {
            wp_die(__('Notification not found.', 'subscriber-notifications'));
        }
        
        // Allow editing of all notifications (pending, sent, cancelled)
        // This allows admins to reuse content and make corrections
        
        $news_categories = get_categories(array('hide_empty' => false));
        $meeting_categories = $this->get_meeting_categories();
        
        // Parse existing categories
        $selected_news_categories = $notification->news_categories ? explode(',', $notification->news_categories) : array();
        $selected_meeting_categories = $notification->meeting_categories ? explode(',', $notification->meeting_categories) : array();
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-edit-notification.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        
        // Get screen option - WordPress stores it via get_user_option
        // get_user_option automatically handles the WordPress prefix
        $per_page = get_user_option('subscriber_notifications_logs_per_page');
        if ($per_page === false || empty($per_page) || $per_page < 1) {
            $per_page = 20;
        }
        $per_page = intval($per_page);
        
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'subscriber_id' => isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0,
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : ''
        );
        
        $logs = $this->database->get_logs($args);
        
        $count_args = array(
            'subscriber_id' => $args['subscriber_id'],
            'status' => $args['status'],
            'date_from' => $args['date_from'],
            'date_to' => $args['date_to']
        );
        $total_logs = $this->database->get_logs_count($count_args);
        $total_pages = ceil($total_logs / $per_page);
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-logs.php';
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export logs.', 'subscriber-notifications'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_logs')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        // Get filter parameters (same as logs_page)
        $args = array(
            'limit' => 0, // No limit for export
            'offset' => 0,
            'subscriber_id' => isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0,
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : ''
        );
        
        // Get all logs matching filters
        $logs = $this->database->get_logs($args);
        
        // Set headers for CSV download
        $filename = 'email-logs_' . date('Y-m-d_H-i-s') . '.csv';
        $charset = get_option('blog_charset');
        
        header('Content-Type: text/csv; charset=' . $charset);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Pragma: no-cache');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Write CSV headers
        fputcsv($output, array(
            __('Subscriber Name', 'subscriber-notifications'),
            __('Subscriber Email', 'subscriber-notifications'),
            __('Email Type', 'subscriber-notifications'),
            __('Status', 'subscriber-notifications'),
            __('Sent Date (UTC)', 'subscriber-notifications'),
            __('Opens', 'subscriber-notifications'),
            __('Clicks', 'subscriber-notifications'),
            __('Last Opened', 'subscriber-notifications'),
            __('Last Clicked', 'subscriber-notifications'),
            __('Error Message', 'subscriber-notifications')
        ));
        
        // Write log data
        foreach ($logs as $log) {
            $subscriber_name = '';
            $subscriber_email = '';
            
            if (empty($log->email) && !empty($log->subscriber_id)) {
                $subscriber_name = __('Subscriber Deleted', 'subscriber-notifications');
                $subscriber_email = sprintf(__('ID: %d', 'subscriber-notifications'), intval($log->subscriber_id));
            } elseif ($log->name) {
                $subscriber_name = $log->name;
                $subscriber_email = $log->email;
            } else {
                $subscriber_email = $log->email;
            }
            
            fputcsv($output, array(
                $subscriber_name,
                $subscriber_email,
                ucfirst($log->email_type),
                ucfirst($log->status),
                $log->sent_date,
                $log->open_count,
                $log->click_count,
                $log->last_opened ? $log->last_opened : '',
                $log->last_clicked ? $log->last_clicked : '',
                $log->error_message ? $log->error_message : ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Get active tab from URL or default to 'general'
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Validate tab name
        $valid_tabs = array('general', 'email-templates', 'scheduling', 'security', 'email-design');
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'general';
        }
        
        // Show success message if settings were updated
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'subscriber-notifications') . '</p></div>';
            });
        }
        
        // Pass admin instance to template
        $admin = $this;
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Register settings with WordPress Settings API
     */
    public function register_settings() {
        // Define tab groups
        $tabs = array(
            'general' => array(
                'mail_method',
                'sendgrid_api_key',
                'sendgrid_from_email',
                'sendgrid_from_name',
                'test_email',
                'delete_data_on_uninstall'
            ),
            'email-templates' => array(
                'welcome_email_subject',
                'welcome_email_content',
                'welcome_back_email_subject',
                'welcome_back_email_content',
                'preferences_update_email_subject',
                'preferences_update_email_content'
            ),
            'scheduling' => array(
                'daily_send_time',
                'weekly_send_day',
                'weekly_send_time',
                'monthly_send_day',
                'monthly_send_time'
            ),
            'security' => array(
                'captcha_site_key',
                'captcha_secret_key'
            ),
            'email-design' => array(
                'global_header_logo',
                'global_header_content',
                'global_footer',
                'email_css'
            )
        );
        
        // Register all settings with sanitization callbacks
        foreach ($tabs as $tab => $options) {
            foreach ($options as $option) {
                register_setting(
                    'subscriber_notifications_' . $tab,
                    $option,
                    array($this, 'sanitize_setting_' . $option)
                );
            }
        }
        
        // Add settings sections
        $this->add_settings_sections();
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings sections for each tab
     */
    private function add_settings_sections() {
        add_settings_section(
            'subscriber_notifications_general',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings'
        );
        
        add_settings_section(
            'subscriber_notifications_email_templates',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings'
        );
        
        add_settings_section(
            'subscriber_notifications_scheduling',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings'
        );
        
        add_settings_section(
            'subscriber_notifications_security',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings'
        );
        
        add_settings_section(
            'subscriber_notifications_email_design',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings'
        );
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General tab fields
        add_settings_field(
            'mail_method',
            __('Mail Delivery Method', 'subscriber-notifications'),
            array($this, 'render_mail_method_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        add_settings_field(
            'sendgrid_api_key',
            __('SendGrid API Key', 'subscriber-notifications'),
            array($this, 'render_sendgrid_api_key_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        add_settings_field(
            'sendgrid_from_email',
            __('From Email', 'subscriber-notifications'),
            array($this, 'render_sendgrid_from_email_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        add_settings_field(
            'sendgrid_from_name',
            __('From Name', 'subscriber-notifications'),
            array($this, 'render_sendgrid_from_name_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        add_settings_field(
            'test_email',
            __('Test Email Address', 'subscriber-notifications'),
            array($this, 'render_test_email_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        add_settings_field(
            'delete_data_on_uninstall',
            __('Delete Data on Uninstall', 'subscriber-notifications'),
            array($this, 'render_delete_data_on_uninstall_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_general'
        );
        
        // Email Templates tab fields
        add_settings_field(
            'welcome_email_subject',
            __('Welcome Email Subject', 'subscriber-notifications'),
            array($this, 'render_welcome_email_subject_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        add_settings_field(
            'welcome_email_content',
            __('Welcome Email Content', 'subscriber-notifications'),
            array($this, 'render_welcome_email_content_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        add_settings_field(
            'welcome_back_email_subject',
            __('Welcome Back Email Subject', 'subscriber-notifications'),
            array($this, 'render_welcome_back_email_subject_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        add_settings_field(
            'welcome_back_email_content',
            __('Welcome Back Email Content', 'subscriber-notifications'),
            array($this, 'render_welcome_back_email_content_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        add_settings_field(
            'preferences_update_email_subject',
            __('Preferences Updated Email Subject', 'subscriber-notifications'),
            array($this, 'render_preferences_update_email_subject_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        add_settings_field(
            'preferences_update_email_content',
            __('Preferences Updated Email Content', 'subscriber-notifications'),
            array($this, 'render_preferences_update_email_content_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_templates'
        );
        
        // Scheduling tab fields
        add_settings_field(
            'daily_send_time',
            __('Daily Email Time', 'subscriber-notifications'),
            array($this, 'render_daily_send_time_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_scheduling'
        );
        
        add_settings_field(
            'weekly_send_day',
            __('Weekly Email Day', 'subscriber-notifications'),
            array($this, 'render_weekly_send_day_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_scheduling'
        );
        
        add_settings_field(
            'weekly_send_time',
            __('Weekly Email Time', 'subscriber-notifications'),
            array($this, 'render_weekly_send_time_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_scheduling'
        );
        
        add_settings_field(
            'monthly_send_day',
            __('Monthly Email Day', 'subscriber-notifications'),
            array($this, 'render_monthly_send_day_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_scheduling'
        );
        
        add_settings_field(
            'monthly_send_time',
            __('Monthly Email Time', 'subscriber-notifications'),
            array($this, 'render_monthly_send_time_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_scheduling'
        );
        
        // Security tab fields
        add_settings_field(
            'captcha_site_key',
            __('reCAPTCHA Site Key', 'subscriber-notifications'),
            array($this, 'render_captcha_site_key_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_security'
        );
        
        add_settings_field(
            'captcha_secret_key',
            __('reCAPTCHA Secret Key', 'subscriber-notifications'),
            array($this, 'render_captcha_secret_key_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_security'
        );
        
        // Email Design tab fields
        add_settings_field(
            'global_header_logo',
            __('Global Header Logo', 'subscriber-notifications'),
            array($this, 'render_global_header_logo_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_design'
        );
        
        add_settings_field(
            'global_header_content',
            __('Global Header Content', 'subscriber-notifications'),
            array($this, 'render_global_header_content_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_design'
        );
        
        add_settings_field(
            'global_footer',
            __('Global Footer Content', 'subscriber-notifications'),
            array($this, 'render_global_footer_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_design'
        );
        
        add_settings_field(
            'email_css',
            __('Custom Email CSS', 'subscriber-notifications'),
            array($this, 'render_email_css_field'),
            'subscriber-notifications-settings',
            'subscriber_notifications_email_design'
        );
    }
    
    /**
     * Sanitization callbacks for each setting
     */
    public function sanitize_setting_mail_method($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_sendgrid_api_key($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_sendgrid_from_email($value) {
        return sanitize_email($value);
    }
    
    public function sanitize_setting_sendgrid_from_name($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_test_email($value) {
        return sanitize_email($value);
    }
    
    public function sanitize_setting_welcome_email_subject($value) {
        return sanitize_textarea_field($value);
    }
    
    public function sanitize_setting_welcome_email_content($value) {
        return $this->sanitize_content_with_shortcodes($value);
    }
    
    public function sanitize_setting_welcome_back_email_subject($value) {
        return sanitize_textarea_field($value);
    }
    
    public function sanitize_setting_welcome_back_email_content($value) {
        return $this->sanitize_content_with_shortcodes($value);
    }
    
    public function sanitize_setting_preferences_update_email_subject($value) {
        return sanitize_textarea_field($value);
    }
    
    public function sanitize_setting_preferences_update_email_content($value) {
        return $this->sanitize_content_with_shortcodes($value);
    }
    
    public function sanitize_setting_daily_send_time($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_weekly_send_day($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_weekly_send_time($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_monthly_send_day($value) {
        return intval($value);
    }
    
    public function sanitize_setting_monthly_send_time($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_captcha_site_key($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_captcha_secret_key($value) {
        return sanitize_text_field($value);
    }
    
    public function sanitize_setting_global_header_logo($value) {
        return $this->validate_header_logo($value);
    }
    
    public function sanitize_setting_global_header_content($value) {
        return $this->sanitize_content_with_shortcodes($value);
    }
    
    public function sanitize_setting_global_footer($value) {
        return $this->sanitize_content_with_shortcodes($value);
    }
    
    public function sanitize_setting_email_css($value) {
        // Don't sanitize CSS - it needs to preserve quotes and special characters
        // Just strip slashes if WordPress added them during POST processing
        return stripslashes($value);
    }
    
    public function sanitize_setting_delete_data_on_uninstall($value) {
        return isset($value) ? 1 : 0;
    }
    
    /**
     * Field render methods - General tab
     */
    public function render_mail_method_field() {
        $value = get_option('mail_method', 'sendgrid');
        ?>
        <select name="mail_method" id="mail_method">
            <option value="sendgrid" <?php selected($value, 'sendgrid'); ?>>
                <?php _e('SendGrid (Recommended)', 'subscriber-notifications'); ?>
            </option>
            <option value="wp_mail" <?php selected($value, 'wp_mail'); ?>>
                <?php _e('WordPress Default Mail', 'subscriber-notifications'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Choose how emails should be delivered. WordPress default mail is useful for testing.', 'subscriber-notifications'); ?>
        </p>
        <div id="mail-method-status" class="notice notice-info inline" style="margin-top: 10px;">
            <p>
                <strong><?php _e('Current Status:', 'subscriber-notifications'); ?></strong>
                <span id="current-method-status">
                    <?php 
                    if ($value === 'wp_mail') {
                        _e('Using WordPress Default Mail', 'subscriber-notifications');
                    } else {
                        _e('Using SendGrid', 'subscriber-notifications');
                    }
                    ?>
                </span>
            </p>
        </div>
        <?php
    }
    
    public function render_sendgrid_api_key_field() {
        $value = get_option('sendgrid_api_key', '');
        ?>
        <input type="password" id="sendgrid_api_key" name="sendgrid_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Your SendGrid API key for sending emails.', 'subscriber-notifications'); ?></p>
        <button type="button" id="test-sendgrid" class="button"><?php _e('Test Connection', 'subscriber-notifications'); ?></button>
        <div id="sendgrid-test-result"></div>
        <?php
    }
    
    public function render_sendgrid_from_email_field() {
        $value = get_option('sendgrid_from_email', get_option('admin_email'));
        ?>
        <input type="email" id="sendgrid_from_email" name="sendgrid_from_email" value="<?php echo esc_attr($value); ?>" class="regular-text" required>
        <p class="description"><?php _e('Email address to send notifications from.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_sendgrid_from_name_field() {
        $value = get_option('sendgrid_from_name', get_bloginfo('name'));
        ?>
        <input type="text" id="sendgrid_from_name" name="sendgrid_from_name" value="<?php echo esc_attr($value); ?>" class="regular-text" required>
        <p class="description"><?php _e('Name to send notifications from.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_test_email_field() {
        $value = get_option('test_email', get_option('admin_email'));
        ?>
        <input type="email" id="test_email" name="test_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Email address to send test notifications to.', 'subscriber-notifications'); ?></p>
        <button type="button" id="test-wp-mail" class="button"><?php _e('Test WordPress Mail', 'subscriber-notifications'); ?></button>
        <div id="wp-mail-test-result"></div>
        <?php
    }
    
    public function render_delete_data_on_uninstall_field() {
        $value = get_option('delete_data_on_uninstall', 0);
        ?>
        <label>
            <input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($value, 1); ?>>
            <?php _e('Delete all plugin data (subscribers, logs, settings) when the plugin is uninstalled', 'subscriber-notifications'); ?>
        </label>
        <p class="description">
            <?php _e('By default, all data is preserved when you uninstall the plugin. Check this box if you want all data to be deleted when uninstalling. This includes all subscribers, email logs, notification queues, and plugin settings.', 'subscriber-notifications'); ?>
            <br><br>
            <strong><?php _e('Current Status:', 'subscriber-notifications'); ?></strong> 
            <?php if ($value): ?>
                <span style="color: #d63638;"><?php _e('Data will be DELETED on uninstall', 'subscriber-notifications'); ?></span>
            <?php else: ?>
                <span style="color: #00a32a;"><?php _e('Data will be PRESERVED on uninstall', 'subscriber-notifications'); ?></span>
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Field render methods - Email Templates tab
     */
    public function render_welcome_email_subject_field() {
        $value = get_option('welcome_email_subject', __('Welcome! Your subscription is confirmed', 'subscriber-notifications'));
        ?>
        <input type="text" id="welcome_email_subject" name="welcome_email_subject" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the welcome email sent immediately after subscription.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_welcome_email_content_field() {
        $value = get_option('welcome_email_content', __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'welcome_email_content',
            array(
                'textarea_name' => 'welcome_email_content',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('Welcome email content. Available shortcodes:', 'subscriber-notifications'); ?><br>
            <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
            <code>[selected_news_categories]</code> - <?php _e('Selected news categories', 'subscriber-notifications'); ?><br>
            <code>[selected_meeting_categories]</code> - <?php _e('Selected meeting categories', 'subscriber-notifications'); ?><br>
            <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
            <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    public function render_welcome_back_email_subject_field() {
        $value = get_option('welcome_back_email_subject', __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'));
        ?>
        <input type="text" id="welcome_back_email_subject" name="welcome_back_email_subject" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the welcome back email sent when an inactive subscriber resubscribes.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_welcome_back_email_content_field() {
        $value = get_option('welcome_back_email_content', __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'welcome_back_email_content',
            array(
                'textarea_name' => 'welcome_back_email_content',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('Welcome back email content sent when an inactive subscriber resubscribes. Available shortcodes:', 'subscriber-notifications'); ?><br>
            <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
            <code>[selected_news_categories]</code> - <?php _e('Selected news categories', 'subscriber-notifications'); ?><br>
            <code>[selected_meeting_categories]</code> - <?php _e('Selected meeting categories', 'subscriber-notifications'); ?><br>
            <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
            <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    public function render_preferences_update_email_subject_field() {
        $value = get_option('preferences_update_email_subject', __('Your preferences have been updated', 'subscriber-notifications'));
        ?>
        <input type="text" id="preferences_update_email_subject" name="preferences_update_email_subject" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the email sent when a subscriber updates their preferences.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_preferences_update_email_content_field() {
        $value = get_option('preferences_update_email_content', __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n" . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n" . __('Your current preferences:', 'subscriber-notifications') . "\n" . __('News Categories: [selected_news_categories]', 'subscriber-notifications') . "\n" . __('Meeting Categories: [selected_meeting_categories]', 'subscriber-notifications') . "\n" . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n" . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'preferences_update_email_content',
            array(
                'textarea_name' => 'preferences_update_email_content',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('Email content sent when a subscriber updates their preferences. Available shortcodes:', 'subscriber-notifications'); ?><br>
            <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
            <code>[selected_news_categories]</code> - <?php _e('Selected news categories', 'subscriber-notifications'); ?><br>
            <code>[selected_meeting_categories]</code> - <?php _e('Selected meeting categories', 'subscriber-notifications'); ?><br>
            <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
            <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
            <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    /**
     * Field render methods - Scheduling tab
     */
    public function render_daily_send_time_field() {
        $value = get_option('daily_send_time', '09:00');
        ?>
        <input type="time" name="daily_send_time" id="daily_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send daily notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_weekly_send_day_field() {
        $value = get_option('weekly_send_day', 'tuesday');
        ?>
        <select name="weekly_send_day" id="weekly_send_day">
            <option value="monday" <?php selected($value, 'monday'); ?>><?php _e('Monday', 'subscriber-notifications'); ?></option>
            <option value="tuesday" <?php selected($value, 'tuesday'); ?>><?php _e('Tuesday', 'subscriber-notifications'); ?></option>
            <option value="wednesday" <?php selected($value, 'wednesday'); ?>><?php _e('Wednesday', 'subscriber-notifications'); ?></option>
            <option value="thursday" <?php selected($value, 'thursday'); ?>><?php _e('Thursday', 'subscriber-notifications'); ?></option>
            <option value="friday" <?php selected($value, 'friday'); ?>><?php _e('Friday', 'subscriber-notifications'); ?></option>
            <option value="saturday" <?php selected($value, 'saturday'); ?>><?php _e('Saturday', 'subscriber-notifications'); ?></option>
            <option value="sunday" <?php selected($value, 'sunday'); ?>><?php _e('Sunday', 'subscriber-notifications'); ?></option>
        </select>
        <p class="description"><?php _e('Day of the week to send weekly notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_weekly_send_time_field() {
        $value = get_option('weekly_send_time', '14:00');
        ?>
        <input type="time" name="weekly_send_time" id="weekly_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send weekly notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_monthly_send_day_field() {
        $value = get_option('monthly_send_day', 15);
        ?>
        <select name="monthly_send_day" id="monthly_send_day">
            <?php for ($i = 1; $i <= 31; $i++): ?>
                <option value="<?php echo $i; ?>" <?php selected($value, $i); ?>>
                    <?php 
                    if ($i == 1) {
                        echo $i . 'st';
                    } elseif ($i == 2) {
                        echo $i . 'nd';
                    } elseif ($i == 3) {
                        echo $i . 'rd';
                    } else {
                        echo $i . 'th';
                    }
                    ?>
                </option>
            <?php endfor; ?>
        </select>
        <p class="description"><?php _e('Day of the month to send monthly notifications. If the month has fewer days, the email will be sent on the last day of the month.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_monthly_send_time_field() {
        $value = get_option('monthly_send_time', '14:00');
        ?>
        <input type="time" name="monthly_send_time" id="monthly_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send monthly notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    /**
     * Field render methods - Security tab
     */
    public function render_captcha_site_key_field() {
        $value = get_option('captcha_site_key', '');
        ?>
        <input type="text" id="captcha_site_key" name="captcha_site_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google reCAPTCHA site key.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_captcha_secret_key_field() {
        $value = get_option('captcha_secret_key', '');
        ?>
        <input type="password" id="captcha_secret_key" name="captcha_secret_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google reCAPTCHA secret key.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    /**
     * Field render methods - Email Design tab
     */
    public function render_global_header_logo_field() {
        $header_logo_id = get_option('global_header_logo', '');
        $header_logo_url = '';
        if ($header_logo_id) {
            $header_logo_url = wp_get_attachment_url($header_logo_id);
        }
        ?>
        <div class="header-logo-upload">
            <input type="hidden" id="global_header_logo" name="global_header_logo" value="<?php echo esc_attr($header_logo_id); ?>" />
            <div class="logo-preview" style="margin-bottom: 10px;">
                <?php if ($header_logo_url): ?>
                    <img src="<?php echo esc_url($header_logo_url); ?>" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd;" />
                    <br>
                    <button type="button" class="button remove-logo" style="margin-top: 5px;"><?php _e('Remove Logo', 'subscriber-notifications'); ?></button>
                <?php else: ?>
                    <div class="no-logo" style="width: 200px; height: 100px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #666;">
                        <?php _e('No logo selected', 'subscriber-notifications'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="button upload-logo"><?php _e('Select Logo', 'subscriber-notifications'); ?></button>
            <p class="description">
                <?php _e('Upload a logo for the email header. Recommended size: 200x100px or smaller. Max file size: 200KB. Supported formats: JPG, PNG, GIF.', 'subscriber-notifications'); ?>
            </p>
        </div>
        <?php
    }
    
    public function render_global_header_content_field() {
        $value = get_option('global_header_content', '');
        wp_editor(
            wp_unslash($value),
            'global_header_content',
            array(
                'textarea_name' => 'global_header_content',
                'media_buttons' => false,
                'textarea_rows' => 6,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('This content will be displayed in the email header alongside the logo. You can use shortcodes like [subscriber_name], [site_title], etc.', 'subscriber-notifications'); ?><br>
            <strong><?php _e('Note:', 'subscriber-notifications'); ?></strong> <?php _e('Keep header content concise. The content will appear on the left, logo on the right.', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    public function render_global_footer_field() {
        $value = get_option('global_footer', '');
        wp_editor(
            wp_unslash($value),
            'global_footer',
            array(
                'textarea_name' => 'global_footer',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('This content will be automatically added to the bottom of every notification email. You can use shortcodes like [site_title], [manage_preferences_link], [subscriber_name], etc.', 'subscriber-notifications'); ?><br>
            <strong><?php _e('Example:', 'subscriber-notifications'); ?></strong> <code>[site_title] | [manage_preferences_link]</code><br>
            <strong><?php _e('Recommended:', 'subscriber-notifications'); ?></strong> <?php _e('Include manage preferences link, contact information, and any legal disclaimers.', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    public function render_email_css_field() {
        $value = get_option('email_css', '');
        ?>
        <textarea id="email_css" name="email_css" rows="10" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Custom CSS styles for email notifications. These styles will be applied to all notification emails.', 'subscriber-notifications'); ?><br>
            <strong><?php _e('Note:', 'subscriber-notifications'); ?></strong> <?php _e('The plugin includes default styling. Leave this field empty to use the default styles, or add custom CSS to override specific elements.', 'subscriber-notifications'); ?><br>
            <strong><?php _e('CSS Inlining:', 'subscriber-notifications'); ?></strong> <?php _e('Your CSS is automatically converted to inline styles for maximum email client compatibility. You can use stylesheet CSS (not just inline styles) - the plugin handles the conversion automatically.', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    /**
     * Reschedule cron jobs
     * 
     * Note: The cron jobs actually run every minute and check if it's time to send.
     * We don't need to reschedule them when settings change - we just need to update
     * the next_send_date for recurring notifications, which is handled separately.
     * 
     * @param array $changed_fields Array of option names that changed (optional)
     */
    private function reschedule_cron_jobs($changed_fields = array()) {
        // The cron jobs are scheduled to run every minute and check if it's time to send.
        // We don't need to reschedule them when settings change - the scheduler handles
        // checking the time internally. We only need to update next_send_date for
        // recurring notifications, which is done in update_recurring_notifications_schedule().
        
        // This method is kept for backward compatibility but doesn't need to do anything
        // since the cron jobs always run every minute regardless of settings.
    }
    
    /**
     * Get notifications
     * 
     * @param array $args Query arguments
     * @return array Array of notification objects
     */
    private function get_notifications($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'limit' => 20,
            'offset' => 0,
            'search' => '',
            'orderby' => 'created_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            if ($args['status'] === 'active_recurring') {
                // Active Recurring: status = pending AND is_recurring = 1 AND recurrence_count > 0
                $where_conditions[] = "status = 'pending' AND is_recurring = 1 AND recurrence_count > 0";
            } else {
                $where_conditions[] = "status = %s";
                $where_values[] = $args['status'];
            }
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(title LIKE %s OR content LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = $wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE {$where_clause} 
            ORDER BY {$args['orderby']} {$args['order']} 
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get notification count
     * 
     * @param array $args Query arguments
     * @return int Notification count
     */
    private function get_notification_count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("1=1");
        $where_values = array();
        
        if (!empty($args['status'])) {
            if ($args['status'] === 'active_recurring') {
                // Active Recurring: status = pending AND is_recurring = 1 AND recurrence_count > 0
                $where_conditions[] = "status = 'pending' AND is_recurring = 1 AND recurrence_count > 0";
            } else {
                $where_conditions[] = "status = %s";
                $where_values[] = $args['status'];
            }
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(title LIKE %s OR content LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (empty($where_values)) {
            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_queue WHERE {$where_clause}";
            return $wpdb->get_var($sql);
        }
        
        $sql = $wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE {$where_clause}
        ", $where_values);
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Get meeting categories
     * 
     * @return array Array of meeting category objects
     */
    private function get_meeting_categories() {
        if (!class_exists('Tribe__Events__Main')) {
            return array();
        }
        
        $meetings_parent = get_term_by('slug', 'meetings', 'tribe_events_cat');
        if (!$meetings_parent) {
            return array();
        }
        
        return get_terms(array(
            'taxonomy' => 'tribe_events_cat',
            'parent' => $meetings_parent->term_id,
            'hide_empty' => false
        ));
    }
    
    
    /**
     * Send preview email via AJAX
     */
    public function send_preview_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'send_preview_email')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to send preview emails.', 'subscriber-notifications'));
        }
        
        $email = sanitize_email($_POST['email']);
        $subject = sanitize_textarea_field($_POST['subject']);
        $content = wp_kses_post($_POST['content']);
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'subscriber-notifications'));
        }
        
        if (empty($subject)) {
            wp_send_json_error(__('Please enter a subject.', 'subscriber-notifications'));
        }
        
        if (empty($content)) {
            wp_send_json_error(__('Please enter content.', 'subscriber-notifications'));
        }
        
        try {
            // Create a sample subscriber for shortcode processing
            $sample_subscriber = (object) array(
                'name' => 'Preview User',
                'email' => $email,
                'news_categories' => '1,2,3',
                'meeting_categories' => '4,5,6',
                'frequency' => 'weekly'
            );
            
            // Process shortcodes
            $shortcodes = new SubscriberNotifications_Shortcodes();
            $processed_subject = $shortcodes->process_shortcodes($subject, $sample_subscriber);
            $processed_content = $shortcodes->process_shortcodes($content, $sample_subscriber);
            
            // Apply CSS (default CSS or custom CSS)
            $email_css = get_option('email_css', '');
            $formatter = SubscriberNotifications_Email_Formatter::get_instance();
            $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $sample_subscriber);
            
            // Send email using current mail method
            $settings = get_option('subscriber_notifications_settings', array());
            $mail_method = isset($settings['mail_method']) ? $settings['mail_method'] : 'wp_mail';
            
            if ($mail_method === 'sendgrid') {
                $sendgrid = new SubscriberNotifications_SendGrid();
                $result = $sendgrid->send_email($email, $processed_subject, $processed_content, 0, 0);
            } else {
                // Use WordPress default mail
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $result = wp_mail($email, $processed_subject, $processed_content, $headers);
            }
            
            if ($result) {
                wp_send_json_success(__('Preview email sent successfully!', 'subscriber-notifications'));
            } else {
                wp_send_json_error(__('Failed to send preview email.', 'subscriber-notifications'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Sanitize notification content while preserving shortcode attributes
     */
    private function sanitize_notification_content($content) {
        // First, unescape the content to fix WordPress auto-escaping
        $content = wp_unslash($content);
        
        // Allow shortcodes with attributes - this is safe for our use case
        // since we control the shortcodes and they don't execute arbitrary code
        $allowed_shortcodes = array(
            'subscriber_name',
            'subscriber_email', 
            'selected_news_categories',
            'selected_meeting_categories',
            'delivery_frequency',
            'news_feed',
            'meetings_feed',
            'site_title',
            'manage_preferences_link'
        );
        
        // Basic HTML sanitization but preserve shortcode attributes
        $content = wp_kses($content, array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'u' => array(),
            'a' => array('href' => array(), 'title' => array()),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'div' => array('class' => array(), 'style' => array()),
            'span' => array('class' => array(), 'style' => array())
        ));
        
        return $content;
    }
    
    /**
     * Sanitize content with shortcodes (for global footer, header, email templates, etc.)
     * 
     * This method handles WordPress auto-escaping by unslashing before sanitization,
     * ensuring shortcode attributes with quotes are preserved correctly.
     * 
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    private function sanitize_content_with_shortcodes($content) {
        // First, unescape the content to fix WordPress auto-escaping
        // WordPress adds slashes to $_POST data, which breaks shortcode attributes with quotes
        $content = wp_unslash($content);
        
        // Use wp_kses_post for HTML sanitization
        // This preserves shortcodes and their attributes while sanitizing HTML
        return wp_kses_post($content);
    }
    
    
    
    
    /**
     * AJAX handler for saving notifications
     */
    public function ajax_save_notification() {
        if (!wp_verify_nonce($_POST['notification_nonce'], 'create_notification')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'subscriber-notifications'));
        }
        
        try {
            $this->handle_notification_creation();
            wp_send_json_success(__('Notification created successfully.', 'subscriber-notifications'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for updating notifications
     */
    public function ajax_update_notification() {
        if (!wp_verify_nonce($_POST['notification_nonce'], 'update_notification')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'subscriber-notifications'));
        }
        
        try {
            $this->handle_notification_update();
            wp_send_json_success(__('Notification updated successfully.', 'subscriber-notifications'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    
    /**
     * Export subscribers to CSV via AJAX
     */
    public function export_csv() {
        if (!wp_verify_nonce($_POST['nonce'], 'subscriber_notifications_nonce')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export subscribers.', 'subscriber-notifications'));
        }
        
        try {
            $csv_handler = new SubscriberNotifications_CSV_Handler($this->database);
            $result = $csv_handler->export_subscribers(array(
                'status' => 'active',
                'format' => 'csv'
            ));
            
            if ($result && isset($result['url'])) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(__('Failed to export subscribers.', 'subscriber-notifications'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    
    /**
     * Import/Export page
     */
    public function import_export_page() {
        // Get available categories for reference
        $news_categories = get_categories(array('hide_empty' => false));
        $meeting_categories = $this->get_meeting_categories();
        
        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-import-export.php';
    }
    
    /**
     * Get WordPress timezone string for date calculations
     * 
     * @return string Timezone string
     */
    private function get_wordpress_timezone() {
        $timezone_string = get_option('timezone_string');
        if (empty($timezone_string)) {
            // Fallback to GMT offset if timezone string is not set
            $gmt_offset = get_option('gmt_offset');
            $timezone_string = 'UTC' . ($gmt_offset >= 0 ? '+' : '') . $gmt_offset;
        }
        return $timezone_string;
    }
    
    /**
     * Calculate next send date for recurring notifications
     * 
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @return string Next send date in MySQL format
     */
    private function calculate_next_send_date($frequency) {
        $current_time = current_time('timestamp');
        $current_utc_time = time(); // UTC timestamp for accurate comparisons
        
        switch ($frequency) {
            case 'daily':
                $daily_time = get_option('daily_send_time', '09:00');
                $timezone = wp_timezone();
                // Use timezone-aware method to get today's date
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $daily_time, $timezone);
                $today_time = $today_datetime->getTimestamp();
                
                // Compare UTC timestamps for accuracy
                if ($today_time <= $current_utc_time) {
                    // Time has passed today, schedule for tomorrow
                    $tomorrow_datetime = clone $today_datetime;
                    $tomorrow_datetime->modify('+1 day');
                    return $tomorrow_datetime->format('Y-m-d H:i:s');
                } else {
                    // Time hasn't passed today, schedule for today
                    return $today_datetime->format('Y-m-d H:i:s');
                }
                
            case 'weekly':
                $weekly_time = get_option('weekly_send_time', '14:00');
                $weekly_day = get_option('weekly_send_day', 'tuesday');
                
                $day_numbers = array(
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                );
                $day_number = $day_numbers[$weekly_day];
                
                // Calculate days until next occurrence
                // Use timezone-aware method to get current day
                $timezone = wp_timezone();
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_day = (int)$now->format('w');
                $days_until = ($day_number - $current_day + 7) % 7;
                
                if ($days_until == 0) {
                    // Same day - check if time has passed
                    // Use timezone-aware DateTime instead of strtotime
                    $today_datetime = new DateTime($now->format('Y-m-d') . ' ' . $weekly_time, $timezone);
                    $today_time = $today_datetime->getTimestamp();
                    // Compare UTC timestamps for accuracy
                    if ($today_time <= $current_utc_time) {
                        $days_until = 7; // Next week
                    }
                }
                
                // Use timezone-aware method to calculate next date
                $next_date_datetime = new DateTime('+' . $days_until . ' days', $timezone);
                $next_date_datetime->setTime(
                    intval(substr($weekly_time, 0, 2)), 
                    intval(substr($weekly_time, 3, 2))
                );
                return $next_date_datetime->format('Y-m-d H:i:s');
                
            case 'monthly':
                $monthly_day = get_option('monthly_send_day', 15);
                $monthly_time = get_option('monthly_send_time', '14:00');
                
                // Use timezone-aware method to get current month/year
                $timezone = wp_timezone();
                $now = new DateTime('@' . $current_time);
                $now->setTimezone($timezone);
                $current_month = (int)$now->format('n');
                $current_year = (int)$now->format('Y');
                
                // Get the target day for current month using timezone-aware method
                $target_datetime = new DateTime($current_year . '-' . $current_month . '-01', $timezone);
                $days_in_month = (int)$target_datetime->format('t');
                $target_day = min($monthly_day, $days_in_month);
                
                // Use WordPress timezone-aware date functions
                $datetime = new DateTime($current_year . '-' . $current_month . '-' . $target_day . ' ' . $monthly_time, $timezone);
                $target_timestamp = $datetime->getTimestamp();
                
                // Compare timestamps - DateTime->getTimestamp() returns UTC timestamp
                // Use time() (UTC) for accurate comparison, not current_time('timestamp') which is timezone-adjusted
                if ($target_timestamp <= $current_utc_time) {
                    // This month's time has passed, go to next month
                    $next_month = $current_month + 1;
                    $next_year = $current_year;
                    if ($next_month > 12) {
                        $next_month = 1;
                        $next_year++;
                    }
                    
                    // Get the target day for next month using timezone-aware method
                    $next_target_datetime = new DateTime($next_year . '-' . $next_month . '-01', $timezone);
                    $days_in_next_month = (int)$next_target_datetime->format('t');
                    $target_day = min($monthly_day, $days_in_next_month);
                    $datetime = new DateTime($next_year . '-' . $next_month . '-' . $target_day . ' ' . $monthly_time, $timezone);
                    $target_timestamp = $datetime->getTimestamp();
                }
                
                return $datetime->format('Y-m-d H:i:s');
                
            default:
                return null;
        }
    }
    
    /**
     * Update next_send_date for existing recurring notifications
     * 
     * @param array $changed_fields Array of option names that changed (optional)
     */
    private function update_recurring_notifications_schedule($changed_fields = array()) {
        global $wpdb;
        
        // If no specific fields provided, update all (backward compatibility)
        if (empty($changed_fields)) {
            $changed_fields = array('daily_send_time', 'weekly_send_day', 'weekly_send_time', 'monthly_send_day', 'monthly_send_time');
        }
        
        // Determine which frequencies need updating
        $frequencies_to_update = array();
        
        if (in_array('daily_send_time', $changed_fields)) {
            $frequencies_to_update[] = 'daily';
        }
        
        if (in_array('weekly_send_day', $changed_fields) || in_array('weekly_send_time', $changed_fields)) {
            $frequencies_to_update[] = 'weekly';
        }
        
        if (in_array('monthly_send_day', $changed_fields) || in_array('monthly_send_time', $changed_fields)) {
            $frequencies_to_update[] = 'monthly';
        }
        
        // If no frequencies need updating, return early
        if (empty($frequencies_to_update)) {
            return;
        }
        
        // Get all pending recurring notifications for the affected frequencies
        $placeholders = implode(',', array_fill(0, count($frequencies_to_update), '%s'));
        $recurring_notifications = $wpdb->get_results($wpdb->prepare("
            SELECT id, frequency_target, next_send_date 
            FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE is_recurring = 1 AND status = 'pending' AND frequency_target IN ($placeholders)
        ", $frequencies_to_update));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "Subscriber Notifications: Found %d pending recurring notifications to update for frequencies: %s",
                count($recurring_notifications),
                implode(', ', $frequencies_to_update)
            ));
        }
        
        foreach ($recurring_notifications as $notification) {
            $old_next_send_date = $notification->next_send_date;
            $new_next_send_date = $this->calculate_next_send_date($notification->frequency_target);
            
            // Validate calculated date is in the future (minimum 1 minute buffer)
            $calculated_timestamp = strtotime($new_next_send_date);
            $current_timestamp = current_time('timestamp');
            $minimum_future_seconds = 60; // 1 minute minimum buffer
            
            $needs_adjustment = false;
            $original_date = $new_next_send_date;
            
            if ($calculated_timestamp <= ($current_timestamp + $minimum_future_seconds)) {
                $needs_adjustment = true;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        "Subscriber Notifications: Calculated date '%s' (timestamp: %d) is in the past or too close (current: %d). Adjusting to minimum future date.",
                        $new_next_send_date,
                        $calculated_timestamp,
                        $current_timestamp
                    ));
                }
                
                // Force to next occurrence based on frequency
                // Use WordPress timezone for consistency
                $timezone = wp_timezone();
                $adjusted_datetime = new DateTime($new_next_send_date, $timezone);
                
                switch ($notification->frequency_target) {
                    case 'daily':
                        // Add 1 day to the calculated date
                        $adjusted_datetime->modify('+1 day');
                        $new_next_send_date = $adjusted_datetime->format('Y-m-d H:i:s');
                        break;
                        
                    case 'weekly':
                        // Add 7 days to the calculated date
                        $adjusted_datetime->modify('+7 days');
                        $new_next_send_date = $adjusted_datetime->format('Y-m-d H:i:s');
                        break;
                        
                    case 'monthly':
                        // Add 1 month to the calculated date
                        $adjusted_datetime->modify('+1 month');
                        $new_next_send_date = $adjusted_datetime->format('Y-m-d H:i:s');
                        break;
                }
                
                // Double-check the adjusted date is in the future
                $adjusted_timestamp = strtotime($new_next_send_date);
                if ($adjusted_timestamp <= ($current_timestamp + $minimum_future_seconds)) {
                    // Fallback: set to 1 minute from now (should never happen, but safety net)
                    $new_next_send_date = date('Y-m-d H:i:s', $current_timestamp + $minimum_future_seconds);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            "Subscriber Notifications: Adjusted date still in past. Using fallback: %s (1 minute from now)",
                            $new_next_send_date
                        ));
                    }
                }
            }
            
            // Update the database
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notifications_queue',
                array('next_send_date' => $new_next_send_date),
                array('id' => $notification->id),
                array('%s'),
                array('%d')
            );
            
            $log_message = sprintf(
                "Subscriber Notifications: Updated recurring notification %d (%s) next send date from '%s' to '%s'",
                $notification->id,
                $notification->frequency_target,
                $old_next_send_date,
                $new_next_send_date
            );
            
            if ($needs_adjustment) {
                $log_message .= sprintf(" (adjusted from '%s' to ensure future date)", $original_date);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($log_message);
            }
        }
    }
    
    
    /**
     * Add Screen Options for pagination
     * 
     * @param WP_Screen $screen Current screen object
     */
    public function action_screen_options($screen) {
        // WordPress core pattern: check the page parameter (most reliable)
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        
        // Check if we're on one of our admin pages
        if ($current_page === 'subscriber-notifications-logs') {
            $screen->add_option('per_page', array(
                'label' => __('Logs per page', 'subscriber-notifications'),
                'default' => 20,
                'option' => 'subscriber_notifications_logs_per_page'
            ));
        } elseif ($current_page === 'subscriber-notifications-subscribers') {
            $screen->add_option('per_page', array(
                'label' => __('Subscribers per page', 'subscriber-notifications'),
                'default' => 20,
                'option' => 'subscriber_notifications_subscribers_per_page'
            ));
        } elseif ($current_page === 'subscriber-notifications-notifications') {
            $screen->add_option('per_page', array(
                'label' => __('Notifications per page', 'subscriber-notifications'),
                'default' => 20,
                'option' => 'subscriber_notifications_notifications_per_page'
            ));
        }
    }
    
    /**
     * Save Screen Options
     * 
     * @param mixed $status Status value
     * @param string $option Option name
     * @param mixed $value Option value
     * @return mixed Status or value
     */
    public function filter_save_screen_options($status, $option, $value) {
        $allowed_options = array(
            'subscriber_notifications_logs_per_page',
            'subscriber_notifications_subscribers_per_page',
            'subscriber_notifications_notifications_per_page'
        );
        
        if (in_array($option, $allowed_options)) {
            // WordPress will save this automatically, we just need to return the value
            return intval($value);
        }
        
        return $status;
    }
    
}
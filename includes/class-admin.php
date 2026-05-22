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
        $this->register_scheduling_side_effects();
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

        // Color picker on the Email Design tab.
        $is_settings_page = isset($_GET['page']) && $_GET['page'] === 'subscriber-notifications-settings';
        $is_email_design_tab = $is_settings_page && isset($_GET['tab']) && $_GET['tab'] === 'email-design';
        if ($is_email_design_tab) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            add_action('admin_footer', array($this, 'render_color_picker_init_script'));
        }
        
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
                // Remove any queued recipients for this notification first.
                $wpdb->delete(
                    $wpdb->prefix . 'subscriber_notifications_send_queue',
                    array('notification_id' => $notification_id),
                    array('%d')
                );
                
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
                    // Mark any remaining `pending` queue rows for this notification as
                    // `skipped` so the drain handler excludes them.
                    $wpdb->update(
                        $wpdb->prefix . 'subscriber_notifications_send_queue',
                        array('status' => 'skipped'),
                        array(
                            'notification_id' => $notification_id,
                            'status'          => 'pending',
                        ),
                        array('%s'),
                        array('%d', '%s')
                    );
                    
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Notification cancelled successfully.', 'subscriber-notifications') . '</p></div>';
                    });
                }
                break;
                
            case 'resend':
            case 'reactivate':
                // Clear any previous queue rows so the notification can be re-enqueued
                // fresh on the next cron tick (the UNIQUE KEY on the queue table would
                // otherwise block the new INSERT IGNORE).
                $wpdb->delete(
                    $wpdb->prefix . 'subscriber_notifications_send_queue',
                    array('notification_id' => $notification_id),
                    array('%d')
                );

                // Look up the notification so we can recompute next_send_date from the
                // current scheduling settings instead of reusing whatever stale value
                // was left from a prior send. Without this, a notification re-queued
                // via the Resend / Reactivate button keeps its old next_send_date
                // (often in the past) and fires immediately on the next cron tick.
                $current = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, frequency_target, is_recurring, last_sent_date FROM {$notifications_table} WHERE id = %d",
                    $notification_id
                ));

                if (!$current) {
                    break;
                }

                $calculator = new SubscriberNotifications_Schedule_Calculator();
                if ((int) $current->is_recurring === 1) {
                    $next_send_date = $calculator->next_recurring(
                        $current->frequency_target,
                        $current->last_sent_date
                    );
                } else {
                    $next_send_date = $calculator->next_one_time($current->frequency_target);
                }

                // For one-time notifications also clear the prior `sent_date` so the
                // admin "Sent" column does not show a stale timestamp while the
                // notification is awaiting its new send.
                $update_data = array(
                    'status'         => 'pending',
                    'next_send_date' => $next_send_date,
                );
                if ((int) $current->is_recurring !== 1) {
                    $update_data['sent_date'] = null;
                }

                $result = $wpdb->update(
                    $notifications_table,
                    $update_data,
                    array('id' => $notification_id),
                    null,
                    array('%d')
                );

                if ($result !== false) {
                    $message = ($action === 'resend')
                        ? __('Notification queued for resending.', 'subscriber-notifications')
                        : __('Notification reactivated successfully.', 'subscriber-notifications');
                    add_action('admin_notices', function() use ($message) {
                        echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
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
        
        $form = $this->parse_notification_form_from_request();
        $errors = $this->validate_notification_form($form);
        if ($errors->has_errors()) {
            $this->register_notification_form_errors($errors);
            return;
        }

        $target_prefs_json = SubscriberNotifications_Preferences::encode($form['target_prefs']);
        $frequency_target  = $form['frequency_target'];
        $is_recurring      = (int) $form['is_recurring'];
        $allowed_freqs     = $this->get_allowed_notification_frequencies();

        // Determine next_send_date based on current state and new settings.
        // Both one-time and recurring notifications have a next_send_date so
        // process_queue() can filter on a single SQL predicate.
        $next_send_date = $current_notification->next_send_date; // Preserve by default
        
        if ($current_notification->status === 'pending' && in_array($frequency_target, $allowed_freqs, true)) {
            $should_recalculate = false;
            
            if ($current_notification->frequency_target !== $frequency_target) {
                $should_recalculate = true;
            } elseif ((int) $current_notification->is_recurring !== $is_recurring) {
                $should_recalculate = true;
            } elseif ($current_notification->next_send_date === null) {
                $should_recalculate = true;
            } else {
                $tz       = wp_timezone();
                $existing = new DateTimeImmutable($current_notification->next_send_date, $tz);
                $now      = new DateTimeImmutable('now', $tz);
                if ($existing <= $now) {
                    $should_recalculate = true;
                }
            }
            
            if ($should_recalculate) {
                $next_send_date = (new SubscriberNotifications_Schedule_Calculator())->next_one_time($frequency_target);
                
                // Add 5-minute safety buffer to prevent immediate sending due to race conditions
                $tz         = wp_timezone();
                $calculated = new DateTimeImmutable($next_send_date, $tz);
                $threshold  = (new DateTimeImmutable('now', $tz))->modify('+5 minutes');
                
                if ($calculated <= $threshold) {
                    $next_send_date = $threshold->format('Y-m-d H:i:s');
                }
            }
        }
        // If status is 'sent' or 'cancelled', preserve existing next_send_date (already set above)
        
        $result = $wpdb->update(
            $wpdb->prefix . 'subscriber_notifications_queue',
            array(
                'title'              => $form['title'],
                'subject'            => $form['subject'],
                'content'            => $form['content'],
                'target_preferences' => $target_prefs_json,
                'frequency_target'   => $frequency_target,
                'is_recurring'       => $is_recurring,
                'next_send_date'     => $next_send_date,
            ),
            array('id' => $notification_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s'),
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
     * Handle notification creation (form POST). Redirects to the edit screen on success.
     */
    private function handle_notification_creation() {
        if (!wp_verify_nonce($_POST['notification_nonce'], 'create_notification')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }

        $result = $this->create_notification_from_post();
        if (is_int($result) && $result > 0) {
            wp_safe_redirect($this->get_notification_edit_url($result, 'created'));
            exit;
        }

        if ($result === false) {
            add_settings_error(
                'subscriber_notifications',
                'notification_create_failed',
                __('Failed to create notification.', 'subscriber-notifications'),
                'error'
            );
        }
    }

    /**
     * Valid target frequency values for notifications.
     *
     * @return string[]
     */
    private function get_allowed_notification_frequencies() {
        return array('daily', 'weekly', 'monthly');
    }

    /**
     * Default notification form values for create screen.
     *
     * @return array
     */
    private function get_empty_notification_form() {
        return array(
            'title'              => '',
            'subject'            => '',
            'content'            => '',
            'frequency_target'   => '',
            'is_recurring'       => 0,
            'target_prefs'       => array(),
            'selected_targets'   => array(),
        );
    }

    /**
     * Parse and sanitize notification form fields from $_POST.
     *
     * @return array
     */
    private function parse_notification_form_from_request() {
        $raw_targets  = isset($_POST['target_preferences']) ? wp_unslash($_POST['target_preferences']) : array();
        $target_prefs = SubscriberNotifications_Preferences::sanitize_from_post($raw_targets);
        $target_prefs = SubscriberNotifications_Preferences::prune_to_allowed_terms($target_prefs);

        $frequency_target = isset($_POST['frequency_target']) ? sanitize_text_field(wp_unslash($_POST['frequency_target'])) : '';
        if (!in_array($frequency_target, $this->get_allowed_notification_frequencies(), true)) {
            $frequency_target = '';
        }

        return array(
            'title'            => isset($_POST['notification_title']) ? sanitize_text_field(wp_unslash($_POST['notification_title'])) : '',
            'subject'          => isset($_POST['notification_subject']) ? sanitize_textarea_field(wp_unslash($_POST['notification_subject'])) : '',
            'content'          => isset($_POST['notification_content']) ? wp_kses_post(wp_unslash($_POST['notification_content'])) : '',
            'frequency_target' => $frequency_target,
            'is_recurring'     => isset($_POST['is_recurring']) ? 1 : 0,
            'target_prefs'     => $target_prefs,
            'selected_targets' => $target_prefs,
        );
    }

    /**
     * Validate parsed notification form data.
     *
     * @param array $form Parsed form from {@see parse_notification_form_from_request()}.
     * @return WP_Error Empty when valid.
     */
    private function validate_notification_form(array $form) {
        $errors = new WP_Error();

        if (!SubscriberNotifications_Preferences::has_at_least_one_term($form['target_prefs'])) {
            $errors->add(
                'target_prefs',
                __('Please select at least one target term in Target Content.', 'subscriber-notifications')
            );
        }

        if (!in_array($form['frequency_target'], $this->get_allowed_notification_frequencies(), true)) {
            $errors->add(
                'frequency_target',
                __('Please select a Target Frequency (Daily, Weekly, or Monthly).', 'subscriber-notifications')
            );
        }

        return $errors;
    }

    /**
     * Queue validation errors for display via settings_errors() in templates.
     *
     * @param WP_Error $errors Validation errors.
     */
    private function register_notification_form_errors(WP_Error $errors) {
        foreach ($errors->get_error_codes() as $code) {
            add_settings_error(
                'subscriber_notifications',
                'notification_' . $code,
                $errors->get_error_message($code),
                'error'
            );
        }
    }

    /**
     * Apply parsed form values onto a notification row object for template display.
     *
     * @param object $notification Queue row object.
     * @param array  $form         Parsed form values.
     * @return object
     */
    private function apply_notification_form_to_object($notification, array $form) {
        $notification->title            = $form['title'];
        $notification->subject          = $form['subject'];
        $notification->content          = $form['content'];
        $notification->frequency_target = $form['frequency_target'];
        $notification->is_recurring     = (int) $form['is_recurring'];

        return $notification;
    }

    /**
     * Insert a notification from the current POST payload.
     *
     * @return int|false|WP_Error New notification ID, WP_Error when validation fails, false on DB error.
     */
    private function create_notification_from_post() {
        $form   = $this->parse_notification_form_from_request();
        $errors = $this->validate_notification_form($form);

        if ($errors->has_errors()) {
            $this->register_notification_form_errors($errors);
            return $errors;
        }

        $target_prefs_json = SubscriberNotifications_Preferences::encode($form['target_prefs']);

        // Both one-time and recurring notifications get a `next_send_date` so the
        // unified process_queue() SQL can filter on `next_send_date <= NOW()` without
        // a separate is_recurring branch.
        $next_send_date = (new SubscriberNotifications_Schedule_Calculator())->next_one_time($form['frequency_target']);

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'subscriber_notifications_queue',
            array(
                'title'              => $form['title'],
                'subject'            => $form['subject'],
                'content'            => $form['content'],
                'target_preferences' => $target_prefs_json,
                'frequency_target'   => $form['frequency_target'],
                'status'             => 'pending',
                'created_by'         => get_current_user_id(),
                'is_recurring'       => (int) $form['is_recurring'],
                'next_send_date'     => $next_send_date,
                'recurrence_count'   => 0,
            )
        );

        if ($result === false) {
            return false;
        }

        $notification_id = (int) $wpdb->insert_id;
        return $notification_id > 0 ? $notification_id : false;
    }

    /**
     * Admin URL for the notification edit screen.
     *
     * @param int    $notification_id Notification row ID.
     * @param string $message         Optional flash message key (e.g. `created`).
     * @return string
     */
    private function get_notification_edit_url($notification_id, $message = '') {
        $args = array(
            'page' => 'subscriber-notifications-edit',
            'id'   => (int) $notification_id,
        );

        if ($message !== '') {
            $args['message'] = sanitize_key($message);
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Show a one-time admin notice after redirect (Post-Redirect-Get).
     */
    private function maybe_render_notification_flash_notice() {
        if (!isset($_GET['page'], $_GET['message'])) {
            return;
        }

        if ($_GET['page'] !== 'subscriber-notifications-edit') {
            return;
        }

        $message_key = sanitize_key(wp_unslash($_GET['message']));
        $messages    = array(
            'created' => __('Notification created successfully.', 'subscriber-notifications'),
        );

        if (!isset($messages[$message_key])) {
            return;
        }

        $text = $messages[$message_key];
        add_action(
            'admin_notices',
            function () use ($text) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
            }
        );
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
                $count = isset($result['count']) ? (int) $result['count'] : 0;
                echo '<div class="notice notice-success"><p>' . sprintf(__('%d subscribers imported successfully.', 'subscriber-notifications'), $count) . '</p></div>';

                if (!empty($result['errors'])) {
                    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Some rows were skipped:', 'subscriber-notifications') . '</strong></p><ul style="list-style: disc; margin-left: 20px;">';
                    foreach ($result['errors'] as $error_line) {
                        echo '<li>' . esc_html($error_line) . '</li>';
                    }
                    echo '</ul></div>';
                }
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            });
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
            'subscription_preferences' => '{}',
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
        $css = subscriber_notifications_get_option('email_css', '');
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
            'subscription_preferences' => '{}',
            'frequency' => 'weekly'
        );
        
        // Process shortcodes for preview
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $preview_subject = $shortcodes->process_shortcodes($notification->subject, $sample_subscriber, $notification);
        $preview_content = $shortcodes->process_shortcodes($notification->content, $sample_subscriber, $notification);
        
        // Apply CSS (default CSS or custom CSS)
        $email_css = subscriber_notifications_get_option('email_css', '');
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
        $is_configured      = SubscriberNotifications_Content_Config::is_configured();
        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();

        $notification_form = $this->get_empty_notification_form();

        if (isset($_POST['create_notification'])) {
            $notification_form = $this->parse_notification_form_from_request();
        }

        $selected_targets = $notification_form['selected_targets'];

        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-create-notification.php';
    }
    
    /**
     * Edit notification page
     */
    public function edit_notification_page() {
        $this->maybe_render_notification_flash_notice();

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

        $is_configured      = SubscriberNotifications_Content_Config::is_configured();
        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        $selected_targets   = SubscriberNotifications_Preferences::decode($notification->target_preferences ?? '');

        if (isset($_POST['update_notification'])) {
            $form_state       = $this->parse_notification_form_from_request();
            $notification     = $this->apply_notification_form_to_object($notification, $form_state);
            $selected_targets = $form_state['selected_targets'];
        }

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
        $filename = 'email-logs_' . wp_date('Y-m-d_H-i-s') . '.csv';
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
     * Admin URL of the Shortcodes reference tab.
     *
     * @return string
     */
    public static function get_shortcode_reference_url() {
        return admin_url('admin.php?page=subscriber-notifications-settings&tab=shortcodes');
    }

    /**
     * Echo a `<p class="description">` linking to the Shortcodes reference tab.
     *
     * Used in place of inline shortcode lists on any field that supports shortcodes.
     */
    public static function render_shortcode_reference_description() {
        printf(
            '<p class="description">%s</p>',
            wp_kses(
                sprintf(
                    /* translators: %s: link to the Shortcodes settings tab. */
                    __('Dynamic content is inserted with shortcodes. %s.', 'subscriber-notifications'),
                    '<a href="' . esc_url(self::get_shortcode_reference_url()) . '">' . esc_html__('View shortcode reference', 'subscriber-notifications') . '</a>'
                ),
                array('a' => array('href' => array()))
            )
        );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        // Get active tab from URL or default to 'general'
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Validate tab name
        $valid_tabs = array('general', 'email-templates', 'scheduling', 'security', 'email-design', 'shortcodes');
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
     * Wire up cron-related side effects for scheduling options.
     *
     * Listens to the per-option `update_option_{$option}` and `add_option_{$option}`
     * actions so all pending notifications (recurring and one-time) get their
     * `next_send_date` recalculated whenever a scheduling option changes —
     * regardless of whether the update comes from the admin Settings page, WP-CLI,
     * the REST API, or any other code path.
     *
     * Each callback passes the specific short key that changed so
     * update_pending_notifications_schedule() only touches the affected frequency.
     */
    public function register_scheduling_side_effects() {
        $scheduling_short_keys = array(
            'daily_send_time',
            'weekly_send_day',
            'weekly_send_time',
            'monthly_send_day',
            'monthly_send_time',
        );

        foreach ($scheduling_short_keys as $short_key) {
            $full_option_name = subscriber_notifications_option_name($short_key);
            $callback = function () use ($short_key) {
                $this->update_pending_notifications_schedule(array($short_key));
            };
            add_action("update_option_{$full_option_name}", $callback);
            add_action("add_option_{$full_option_name}", $callback);
        }
    }

    /**
     * Register settings with WordPress Settings API
     */
    public function register_settings() {
        // Define tab groups
        $tabs = array(
            'general' => array(
                'test_email',
                'delete_data_on_uninstall',
                'hide_terms_without_published_content'
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
                'email_css',
                'email_font_body',
                'email_font_heading',
                'email_color_text',
                'email_color_link',
                'email_color_background',
                'email_color_content_bg',
                'email_color_link_hover',
                'email_color_footer_bg',
                'email_color_footer_text'
            )
        );

        // Register all settings with sanitization callbacks
        foreach ($tabs as $tab => $options) {
            foreach ($options as $option) {
                $full_option_name = subscriber_notifications_option_name($option);
                register_setting(
                    'subscriber_notifications_' . $tab,
                    $full_option_name,
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
     * Add settings sections for each tab.
     *
     * Each Settings page (tab) gets its own page slug so do_settings_sections()
     * renders only that tab's fields. Most tabs use one section; Email Design
     * uses four sub-sections (Header & Footer, Brand Colors, Typography, Advanced).
     */
    private function add_settings_sections() {
        add_settings_section(
            'subscriber_notifications_general_section',
            '',
            array($this, 'render_general_section_description'),
            'subscriber-notifications-settings-general'
        );

        add_settings_section(
            'subscriber_notifications_email_templates_section',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings-email-templates'
        );

        add_settings_section(
            'subscriber_notifications_scheduling_section',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings-scheduling'
        );

        add_settings_section(
            'subscriber_notifications_security_section',
            '',
            '__return_empty_string',
            'subscriber-notifications-settings-security'
        );

        add_settings_section(
            'subscriber_notifications_email_design_header_footer',
            __('Header & Footer', 'subscriber-notifications'),
            '__return_empty_string',
            'subscriber-notifications-settings-email-design'
        );

        add_settings_section(
            'subscriber_notifications_email_design_brand_colors',
            __('Brand Colors', 'subscriber-notifications'),
            '__return_empty_string',
            'subscriber-notifications-settings-email-design'
        );

        add_settings_section(
            'subscriber_notifications_email_design_typography',
            __('Typography', 'subscriber-notifications'),
            '__return_empty_string',
            'subscriber-notifications-settings-email-design'
        );

        add_settings_section(
            'subscriber_notifications_email_design_advanced',
            __('Advanced', 'subscriber-notifications'),
            '__return_empty_string',
            'subscriber-notifications-settings-email-design'
        );
    }

    /**
     * Section description callback for the General tab.
     *
     * Surfaces the wp_mail() notice that previously lived inline in
     * templates/admin-settings.php so it renders inside the Settings API
     * section markup.
     */
    public function render_general_section_description() {
        ?>
        <div class="notice notice-info inline" style="margin: 15px 0;">
            <p><?php esc_html_e('Outgoing mail uses WordPress wp_mail(). Configure your site email (SMTP plugin, hosts mail, etc.) as needed.', 'subscriber-notifications'); ?></p>
        </div>
        <?php
    }

    /**
     * Add settings fields for each tab and section.
     */
    private function add_settings_fields() {
        // General tab fields
        add_settings_field(
            'test_email',
            __('Test Email Address', 'subscriber-notifications'),
            array($this, 'render_test_email_field'),
            'subscriber-notifications-settings-general',
            'subscriber_notifications_general_section'
        );

        add_settings_field(
            'hide_terms_without_published_content',
            __('Hide Empty Terms on Subscription Form', 'subscriber-notifications'),
            array($this, 'render_hide_terms_without_published_content_field'),
            'subscriber-notifications-settings-general',
            'subscriber_notifications_general_section'
        );

        add_settings_field(
            'delete_data_on_uninstall',
            __('Delete Data on Uninstall', 'subscriber-notifications'),
            array($this, 'render_delete_data_on_uninstall_field'),
            'subscriber-notifications-settings-general',
            'subscriber_notifications_general_section'
        );

        // Email Templates tab fields
        add_settings_field(
            'welcome_email_subject',
            __('Welcome Email Subject', 'subscriber-notifications'),
            array($this, 'render_welcome_email_subject_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        add_settings_field(
            'welcome_email_content',
            __('Welcome Email Content', 'subscriber-notifications'),
            array($this, 'render_welcome_email_content_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        add_settings_field(
            'welcome_back_email_subject',
            __('Welcome Back Email Subject', 'subscriber-notifications'),
            array($this, 'render_welcome_back_email_subject_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        add_settings_field(
            'welcome_back_email_content',
            __('Welcome Back Email Content', 'subscriber-notifications'),
            array($this, 'render_welcome_back_email_content_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        add_settings_field(
            'preferences_update_email_subject',
            __('Preferences Updated Email Subject', 'subscriber-notifications'),
            array($this, 'render_preferences_update_email_subject_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        add_settings_field(
            'preferences_update_email_content',
            __('Preferences Updated Email Content', 'subscriber-notifications'),
            array($this, 'render_preferences_update_email_content_field'),
            'subscriber-notifications-settings-email-templates',
            'subscriber_notifications_email_templates_section'
        );

        // Scheduling tab fields
        add_settings_field(
            'daily_send_time',
            __('Daily Email Time', 'subscriber-notifications'),
            array($this, 'render_daily_send_time_field'),
            'subscriber-notifications-settings-scheduling',
            'subscriber_notifications_scheduling_section'
        );

        add_settings_field(
            'weekly_send_day',
            __('Weekly Email Day', 'subscriber-notifications'),
            array($this, 'render_weekly_send_day_field'),
            'subscriber-notifications-settings-scheduling',
            'subscriber_notifications_scheduling_section'
        );

        add_settings_field(
            'weekly_send_time',
            __('Weekly Email Time', 'subscriber-notifications'),
            array($this, 'render_weekly_send_time_field'),
            'subscriber-notifications-settings-scheduling',
            'subscriber_notifications_scheduling_section'
        );

        add_settings_field(
            'monthly_send_day',
            __('Monthly Email Day', 'subscriber-notifications'),
            array($this, 'render_monthly_send_day_field'),
            'subscriber-notifications-settings-scheduling',
            'subscriber_notifications_scheduling_section'
        );

        add_settings_field(
            'monthly_send_time',
            __('Monthly Email Time', 'subscriber-notifications'),
            array($this, 'render_monthly_send_time_field'),
            'subscriber-notifications-settings-scheduling',
            'subscriber_notifications_scheduling_section'
        );

        // Security tab fields
        add_settings_field(
            'captcha_site_key',
            __('reCAPTCHA Site Key', 'subscriber-notifications'),
            array($this, 'render_captcha_site_key_field'),
            'subscriber-notifications-settings-security',
            'subscriber_notifications_security_section'
        );

        add_settings_field(
            'captcha_secret_key',
            __('reCAPTCHA Secret Key', 'subscriber-notifications'),
            array($this, 'render_captcha_secret_key_field'),
            'subscriber-notifications-settings-security',
            'subscriber_notifications_security_section'
        );

        // Email Design — Header & Footer section
        add_settings_field(
            'global_header_logo',
            __('Global Header Logo', 'subscriber-notifications'),
            array($this, 'render_global_header_logo_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_header_footer'
        );

        add_settings_field(
            'global_header_content',
            __('Global Header Content', 'subscriber-notifications'),
            array($this, 'render_global_header_content_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_header_footer'
        );

        add_settings_field(
            'global_footer',
            __('Global Footer Content', 'subscriber-notifications'),
            array($this, 'render_global_footer_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_header_footer'
        );

        // Email Design — Brand Colors section
        add_settings_field(
            'email_color_text',
            __('Body Text', 'subscriber-notifications'),
            array($this, 'render_email_color_text_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_link',
            __('Link', 'subscriber-notifications'),
            array($this, 'render_email_color_link_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_link_hover',
            __('Link Hover', 'subscriber-notifications'),
            array($this, 'render_email_color_link_hover_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_background',
            __('Outer Background', 'subscriber-notifications'),
            array($this, 'render_email_color_background_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_content_bg',
            __('Content Background', 'subscriber-notifications'),
            array($this, 'render_email_color_content_bg_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_footer_bg',
            __('Footer Background', 'subscriber-notifications'),
            array($this, 'render_email_color_footer_bg_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        add_settings_field(
            'email_color_footer_text',
            __('Footer Text', 'subscriber-notifications'),
            array($this, 'render_email_color_footer_text_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_brand_colors'
        );

        // Email Design — Typography section
        add_settings_field(
            'email_font_body',
            __('Body Font', 'subscriber-notifications'),
            array($this, 'render_email_font_body_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_typography'
        );

        add_settings_field(
            'email_font_heading',
            __('Heading Font', 'subscriber-notifications'),
            array($this, 'render_email_font_heading_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_typography'
        );

        // Email Design — Advanced section
        add_settings_field(
            'email_css',
            __('Custom Email CSS', 'subscriber-notifications'),
            array($this, 'render_email_css_field'),
            'subscriber-notifications-settings-email-design',
            'subscriber_notifications_email_design_advanced'
        );
    }
    
    /**
     * Sanitization callbacks for each setting
     */
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

    public function sanitize_setting_email_font_body($value) {
        return $this->sanitize_font_stack($value, 'Arial, Helvetica, sans-serif');
    }

    public function sanitize_setting_email_font_heading($value) {
        $value = is_string($value) ? trim(wp_strip_all_tags($value)) : '';
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9 ,\'"\-]+$/', $value)) {
            return '';
        }
        return $value;
    }

    public function sanitize_setting_email_color_text($value) {
        return $this->sanitize_hex_color_with_default($value, '#333333');
    }

    public function sanitize_setting_email_color_link($value) {
        return $this->sanitize_hex_color_with_default($value, '#0066cc');
    }

    public function sanitize_setting_email_color_background($value) {
        return $this->sanitize_hex_color_with_default($value, '#f5f5f5');
    }

    public function sanitize_setting_email_color_content_bg($value) {
        return $this->sanitize_hex_color_with_default($value, '#ffffff');
    }

    public function sanitize_setting_email_color_link_hover($value) {
        return $this->sanitize_hex_color_with_default($value, '#004499');
    }

    public function sanitize_setting_email_color_footer_bg($value) {
        return $this->sanitize_hex_color_with_default($value, '#1d2327');
    }

    public function sanitize_setting_email_color_footer_text($value) {
        return $this->sanitize_hex_color_with_default($value, '#ffffff');
    }

    /**
     * Validate a CSS font stack. Allows letters, digits, spaces, quotes, commas, hyphens.
     */
    private function sanitize_font_stack($value, $default) {
        $value = is_string($value) ? trim(wp_strip_all_tags($value)) : '';
        if ($value === '') {
            return $default;
        }
        if (!preg_match('/^[A-Za-z0-9 ,\'"\-]+$/', $value)) {
            return $default;
        }
        return $value;
    }

    /**
     * Sanitize a hex color value, falling back to the default when invalid.
     */
    private function sanitize_hex_color_with_default($value, $default) {
        $value = is_string($value) ? trim($value) : '';
        $sanitized = sanitize_hex_color($value);
        return $sanitized ?: $default;
    }

    public function sanitize_setting_delete_data_on_uninstall($value) {
        return !empty($value) ? 1 : 0;
    }

    public function sanitize_setting_hide_terms_without_published_content($value) {
        return !empty($value) ? 1 : 0;
    }

    /**
     * Field render methods - General tab
     */
    public function render_test_email_field() {
        $name_opt = subscriber_notifications_option_name('test_email');
        $value = subscriber_notifications_get_option('test_email', get_option('admin_email'));
        ?>
        <input type="email" id="test_email" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Email address to send test notifications to.', 'subscriber-notifications'); ?></p>
        <p class="description"><?php _e('Emails are sent through WordPress wp_mail() (configure SMTP or a mail plugin as needed).', 'subscriber-notifications'); ?></p>
        <button type="button" id="test-wp-mail" class="button"><?php _e('Send Test Email', 'subscriber-notifications'); ?></button>
        <div id="wp-mail-test-result"></div>
        <?php
    }
    
    public function render_delete_data_on_uninstall_field() {
        $name_opt = subscriber_notifications_option_name('delete_data_on_uninstall');
        $value = (int) subscriber_notifications_get_option('delete_data_on_uninstall', 0);
        ?>
        <label>
            <input type="hidden" name="<?php echo esc_attr($name_opt); ?>" value="0">
            <input type="checkbox" name="<?php echo esc_attr($name_opt); ?>" value="1" <?php checked($value, 1); ?>>
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

    public function render_hide_terms_without_published_content_field() {
        $name_opt = subscriber_notifications_option_name('hide_terms_without_published_content');
        $value    = (int) subscriber_notifications_get_option('hide_terms_without_published_content', 1);
        ?>
        <label>
            <input type="hidden" name="<?php echo esc_attr($name_opt); ?>" value="0">
            <input type="checkbox" name="<?php echo esc_attr($name_opt); ?>" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Hide terms with no published posts from the public subscription form', 'subscriber-notifications'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, only terms attached to at least one published post of the configured post type appear in the subscribe and preferences forms. Admin notification targets always show every configured term.', 'subscriber-notifications'); ?>
        </p>
        <?php
    }
    
    /**
     * Field render methods - Email Templates tab
     */
    public function render_welcome_email_subject_field() {
        $name_opt = subscriber_notifications_option_name('welcome_email_subject');
        $value = subscriber_notifications_get_option('welcome_email_subject', __('Welcome! Your subscription is confirmed', 'subscriber-notifications'));
        ?>
        <input type="text" id="welcome_email_subject" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the welcome email sent immediately after subscription.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_welcome_email_content_field() {
        $name_opt = subscriber_notifications_option_name('welcome_email_content');
        $value = subscriber_notifications_get_option('welcome_email_content', __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'welcome_email_content',
            array(
                'textarea_name' => $name_opt,
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description"><?php _e('Sent immediately after a new subscription is confirmed.', 'subscriber-notifications'); ?></p>
        <?php self::render_shortcode_reference_description(); ?>
        <?php
    }
    
    public function render_welcome_back_email_subject_field() {
        $name_opt = subscriber_notifications_option_name('welcome_back_email_subject');
        $value = subscriber_notifications_get_option('welcome_back_email_subject', __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'));
        ?>
        <input type="text" id="welcome_back_email_subject" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the welcome back email sent when an inactive subscriber resubscribes.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_welcome_back_email_content_field() {
        $name_opt = subscriber_notifications_option_name('welcome_back_email_content');
        $value = subscriber_notifications_get_option('welcome_back_email_content', __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'welcome_back_email_content',
            array(
                'textarea_name' => $name_opt,
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description"><?php _e('Sent when an inactive subscriber resubscribes.', 'subscriber-notifications'); ?></p>
        <?php self::render_shortcode_reference_description(); ?>
        <?php
    }
    
    public function render_preferences_update_email_subject_field() {
        $name_opt = subscriber_notifications_option_name('preferences_update_email_subject');
        $value = subscriber_notifications_get_option('preferences_update_email_subject', __('Your preferences have been updated', 'subscriber-notifications'));
        ?>
        <input type="text" id="preferences_update_email_subject" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="large-text" required>
        <p class="description"><?php _e('Subject line for the email sent when a subscriber updates their preferences.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_preferences_update_email_content_field() {
        $name_opt = subscriber_notifications_option_name('preferences_update_email_content');
        $value = subscriber_notifications_get_option('preferences_update_email_content', __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n" . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n" . __('Your current preferences:', 'subscriber-notifications') . "\n" . __('Subscriptions: [selected_subscriptions]', 'subscriber-notifications') . "\n" . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n" . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications'));
        wp_editor(
            wp_unslash($value),
            'preferences_update_email_content',
            array(
                'textarea_name' => $name_opt,
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description"><?php _e('Sent when a subscriber updates their preferences.', 'subscriber-notifications'); ?></p>
        <?php self::render_shortcode_reference_description(); ?>
        <?php
    }
    
    /**
     * Field render methods - Scheduling tab
     */
    public function render_daily_send_time_field() {
        $name_opt = subscriber_notifications_option_name('daily_send_time');
        $value = subscriber_notifications_get_option('daily_send_time', '09:00');
        ?>
        <input type="time" name="<?php echo esc_attr($name_opt); ?>" id="daily_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send daily notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_weekly_send_day_field() {
        $name_opt = subscriber_notifications_option_name('weekly_send_day');
        $value = subscriber_notifications_get_option('weekly_send_day', 'tuesday');
        ?>
        <select name="<?php echo esc_attr($name_opt); ?>" id="weekly_send_day">
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
        $name_opt = subscriber_notifications_option_name('weekly_send_time');
        $value = subscriber_notifications_get_option('weekly_send_time', '14:00');
        ?>
        <input type="time" name="<?php echo esc_attr($name_opt); ?>" id="weekly_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send weekly notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_monthly_send_day_field() {
        $name_opt = subscriber_notifications_option_name('monthly_send_day');
        $value = subscriber_notifications_get_option('monthly_send_day', 15);
        ?>
        <select name="<?php echo esc_attr($name_opt); ?>" id="monthly_send_day">
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
        $name_opt = subscriber_notifications_option_name('monthly_send_time');
        $value = subscriber_notifications_get_option('monthly_send_time', '14:00');
        ?>
        <input type="time" name="<?php echo esc_attr($name_opt); ?>" id="monthly_send_time" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Time to send monthly notifications.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    /**
     * Field render methods - Security tab
     */
    public function render_captcha_site_key_field() {
        $name_opt = subscriber_notifications_option_name('captcha_site_key');
        $value = subscriber_notifications_get_option('captcha_site_key', '');
        ?>
        <input type="text" id="captcha_site_key" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google reCAPTCHA v2 site key (the "I\'m not a robot" checkbox version).', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    public function render_captcha_secret_key_field() {
        $name_opt = subscriber_notifications_option_name('captcha_secret_key');
        $value = subscriber_notifications_get_option('captcha_secret_key', '');
        ?>
        <input type="password" id="captcha_secret_key" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google reCAPTCHA v2 secret key.', 'subscriber-notifications'); ?></p>
        <?php
    }
    
    /**
     * Field render methods - Email Design tab
     */
    public function render_global_header_logo_field() {
        $name_opt = subscriber_notifications_option_name('global_header_logo');
        $header_logo_id = subscriber_notifications_get_option('global_header_logo', '');
        $header_logo_url = '';
        if ($header_logo_id) {
            $header_logo_url = wp_get_attachment_url($header_logo_id);
        }
        ?>
        <div class="header-logo-upload">
            <input type="hidden" id="global_header_logo" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($header_logo_id); ?>" />
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
        $name_opt = subscriber_notifications_option_name('global_header_content');
        $value = subscriber_notifications_get_option('global_header_content', '');
        wp_editor(
            wp_unslash($value),
            'global_header_content',
            array(
                'textarea_name' => $name_opt,
                'media_buttons' => false,
                'textarea_rows' => 6,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('Displayed in the email header alongside the logo (content on the left, logo on the right). Keep it concise.', 'subscriber-notifications'); ?>
        </p>
        <?php self::render_shortcode_reference_description(); ?>
        <?php
    }
    
    public function render_global_footer_field() {
        $name_opt = subscriber_notifications_option_name('global_footer');
        $value = subscriber_notifications_get_option('global_footer', '');
        wp_editor(
            wp_unslash($value),
            'global_footer',
            array(
                'textarea_name' => $name_opt,
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false
            )
        );
        ?>
        <p class="description">
            <?php _e('Added to the bottom of every notification email. Recommended: include a manage preferences link, contact information, and any legal disclaimers.', 'subscriber-notifications'); ?>
        </p>
        <?php self::render_shortcode_reference_description(); ?>
        <?php
    }
    
    public function render_email_css_field() {
        $name_opt = subscriber_notifications_option_name('email_css');
        $value = subscriber_notifications_get_option('email_css', '');
        ?>
        <textarea id="email_css" name="<?php echo esc_attr($name_opt); ?>" rows="10" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Custom CSS appended after the generated branding CSS. Useful for fine-tuning when the brand tokens above are not enough.', 'subscriber-notifications'); ?>
        </p>
        <?php
    }

    /**
     * Render a brand color picker bound to the given short option key.
     */
    private function render_brand_color_field($short_key, $default, $description = '') {
        $name_opt = subscriber_notifications_option_name($short_key);
        $value = subscriber_notifications_get_option($short_key, $default);
        ?>
        <input type="text" id="<?php echo esc_attr($short_key); ?>" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="subscriber-notifications-color-field" data-default-color="<?php echo esc_attr($default); ?>" />
        <?php if ($description) : ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_email_color_text_field() {
        $this->render_brand_color_field('email_color_text', '#333333', __('Body text color.', 'subscriber-notifications'));
    }

    public function render_email_color_link_field() {
        $this->render_brand_color_field('email_color_link', '#0066cc', __('Hyperlink color.', 'subscriber-notifications'));
    }

    public function render_email_color_background_field() {
        $this->render_brand_color_field('email_color_background', '#f5f5f5', __('Email outer background color.', 'subscriber-notifications'));
    }

    public function render_email_color_content_bg_field() {
        $this->render_brand_color_field('email_color_content_bg', '#ffffff', __('Background color of the main content card.', 'subscriber-notifications'));
    }

    public function render_email_color_link_hover_field() {
        $this->render_brand_color_field(
            'email_color_link_hover',
            '#004499',
            __('Link color on hover (in clients that support :hover).', 'subscriber-notifications')
        );
    }

    public function render_email_color_footer_bg_field() {
        $this->render_brand_color_field('email_color_footer_bg', '#1d2327', __('Footer background color.', 'subscriber-notifications'));
    }

    public function render_email_color_footer_text_field() {
        $this->render_brand_color_field('email_color_footer_text', '#ffffff', __('Footer text color.', 'subscriber-notifications'));
    }

    public function render_email_font_body_field() {
        $name_opt = subscriber_notifications_option_name('email_font_body');
        $value = subscriber_notifications_get_option('email_font_body', 'Arial, Helvetica, sans-serif');
        ?>
        <input type="text" id="email_font_body" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Font stack for body text (paragraphs, lists, links).', 'subscriber-notifications'); ?></p>
        <?php
    }

    public function render_email_font_heading_field() {
        $name_opt = subscriber_notifications_option_name('email_font_heading');
        $value = subscriber_notifications_get_option('email_font_heading', '');
        ?>
        <input type="text" id="email_font_heading" name="<?php echo esc_attr($name_opt); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Font stack for headings (h1–h6). Leave blank to use the body font.', 'subscriber-notifications'); ?></p>
        <?php
    }

    /**
     * Inline init for the WP color picker on the Email Design tab.
     */
    public function render_color_picker_init_script() {
        ?>
        <script>
        jQuery(function ($) {
            if ($.fn.wpColorPicker) {
                $('.subscriber-notifications-color-field').wpColorPicker();
            }
        });
        </script>
        <?php
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
                'subscription_preferences' => '{}',
                'frequency' => 'weekly'
            );
            
            // Process shortcodes
            $shortcodes = new SubscriberNotifications_Shortcodes();
            $processed_subject = $shortcodes->process_shortcodes($subject, $sample_subscriber);
            $processed_content = $shortcodes->process_shortcodes($content, $sample_subscriber);
            
            // Apply CSS (default CSS or custom CSS)
            $email_css = subscriber_notifications_get_option('email_css', '');
            $formatter = SubscriberNotifications_Email_Formatter::get_instance();
            $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $sample_subscriber);

            $mailer = new SubscriberNotifications_Email_Sender();
            $result = $mailer->send_email($email, $processed_subject, $processed_content, 0, 0);

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
            'selected_subscriptions',
            'selected_terms',
            'content_feed',
            'delivery_frequency',
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
            $result = $this->create_notification_from_post();

            if (is_int($result) && $result > 0) {
                wp_send_json_success(
                    array(
                        'message'  => __('Notification created successfully.', 'subscriber-notifications'),
                        'redirect' => $this->get_notification_edit_url($result, 'created'),
                    )
                );
            }

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            if ($result === false) {
                wp_send_json_error(__('Failed to create notification.', 'subscriber-notifications'));
            }

            wp_send_json_error(__('Unable to create notification.', 'subscriber-notifications'));
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
        // Build a reference list of configured post type + taxonomy term IDs for the import help text.
        $reference_lists = array();
        foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
            foreach (SubscriberNotifications_Content_Config::get_form_taxonomies($post_type) as $taxonomy) {
                $terms = SubscriberNotifications_Term_Resolver::get_terms_for_form($post_type, $taxonomy);
                if (empty($terms)) {
                    continue;
                }
                $term_rows = array();
                foreach ($terms as $term) {
                    $term_rows[] = array(
                        'term'                    => $term,
                        'hidden_from_public_form' => SubscriberNotifications_Term_Resolver::is_term_hidden_from_public_form(
                            $post_type,
                            $taxonomy,
                            (int) $term->term_id
                        ),
                    );
                }

                $reference_lists[] = array(
                    'post_type'       => $post_type,
                    'post_type_label' => SubscriberNotifications_Content_Config::get_post_type_label($post_type),
                    'taxonomy'        => $taxonomy,
                    'taxonomy_label'  => SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy),
                    'terms'           => $term_rows,
                );
            }
        }

        include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/admin-import-export.php';
    }
    
    /**
     * Update next_send_date for pending notifications (recurring and one-time).
     *
     * Both recurring and non-recurring notifications derive their `next_send_date`
     * from the global daily / weekly / monthly send-time options at creation time,
     * so changes to those options must propagate to every pending row of the
     * affected frequency — not just recurring rows.
     *
     * @param array $changed_fields Array of option short-keys that changed (optional).
     */
    private function update_pending_notifications_schedule($changed_fields = array()) {
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
        
        // Get all pending notifications (recurring and one-time) for the affected frequencies.
        // One-time notifications are included because their initial next_send_date was
        // calculated from the same global send-time options that just changed.
        $placeholders = implode(',', array_fill(0, count($frequencies_to_update), '%s'));
        $pending_notifications = $wpdb->get_results($wpdb->prepare("
            SELECT id, frequency_target, next_send_date, is_recurring
            FROM {$wpdb->prefix}subscriber_notifications_queue 
            WHERE status = 'pending' AND frequency_target IN ($placeholders)
        ", $frequencies_to_update));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "Subscriber Notifications: Found %d pending notifications to update for frequencies: %s",
                count($pending_notifications),
                implode(', ', $frequencies_to_update)
            ));
        }
        
        foreach ($pending_notifications as $notification) {
            $old_next_send_date = $notification->next_send_date;
            $new_next_send_date = (new SubscriberNotifications_Schedule_Calculator())->next_one_time($notification->frequency_target);
            
            // Validate calculated date is in the future (minimum 1 minute buffer)
            $tz                = wp_timezone();
            $minimum_threshold = (new DateTimeImmutable('now', $tz))->modify('+1 minute');
            $calculated        = new DateTimeImmutable($new_next_send_date, $tz);
            
            $needs_adjustment = false;
            $original_date    = $new_next_send_date;
            
            if ($calculated <= $minimum_threshold) {
                $needs_adjustment = true;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        "Subscriber Notifications: Calculated date '%s' is in the past or too close to now (threshold: %s). Adjusting to minimum future date.",
                        $new_next_send_date,
                        $minimum_threshold->format('Y-m-d H:i:s')
                    ));
                }
                
                // Force to next occurrence based on frequency
                $adjusted = $calculated;
                switch ($notification->frequency_target) {
                    case 'daily':
                        $adjusted = $calculated->modify('+1 day');
                        break;
                    case 'weekly':
                        $adjusted = $calculated->modify('+7 days');
                        break;
                    case 'monthly':
                        $adjusted = $calculated->modify('+1 month');
                        break;
                }
                $new_next_send_date = $adjusted->format('Y-m-d H:i:s');
                
                // Double-check the adjusted date is in the future
                if ($adjusted <= $minimum_threshold) {
                    // Fallback: set to minimum threshold (should never happen, but safety net)
                    $new_next_send_date = $minimum_threshold->format('Y-m-d H:i:s');
                    
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
                "Subscriber Notifications: Updated %s notification %d (%s) next send date from '%s' to '%s'",
                ((int) $notification->is_recurring === 1) ? 'recurring' : 'one-time',
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
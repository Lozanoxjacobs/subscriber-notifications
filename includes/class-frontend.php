<?php
/**
 * Frontend functionality class
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class for managing public-facing functionality
 */
class SubscriberNotifications_Frontend {
    
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_nopriv_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('wp_ajax_nopriv_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('template_redirect', array($this, 'handle_unsubscribe'));
        add_shortcode('subscriber_notifications_form', array($this, 'subscription_form_shortcode'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Enqueue reCAPTCHA
        $site_key = get_option('captcha_site_key', '');
        if (!empty($site_key)) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js',
                array(),
                null,
                true
            );
        }
        
        wp_enqueue_script(
            'subscriber-notifications-frontend',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SUBSCRIBER_NOTIFICATIONS_VERSION,
            true
        );
        
        wp_localize_script('subscriber-notifications-frontend', 'subscriberNotifications', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'homeUrl' => home_url('/'),
            'nonce' => wp_create_nonce('subscriber_notifications_nonce'),
            'unsubscribeNonce' => wp_create_nonce('subscriber_notifications_unsubscribe'),
            'siteKey' => $site_key
        ));
        
        wp_enqueue_style(
            'subscriber-notifications-frontend',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SUBSCRIBER_NOTIFICATIONS_VERSION
        );
    }
    
    /**
     * Subscription form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public function subscription_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Subscribe to Notifications', 'subscriber-notifications')
        ), $atts);
        
        ob_start();
        $this->render_subscription_form($atts);
        return ob_get_clean();
    }
    
    /**
     * Render subscription form
     * 
     * @param array $atts Form attributes
     */
    private function render_subscription_form($atts) {
        $news_categories = get_categories(array('hide_empty' => false));
        $meeting_categories = $this->get_meeting_categories();
        ?>
        <div class="subscriber-notifications-form">
            
            <form id="subscriber-notifications-form" method="post">
                <?php wp_nonce_field('subscriber_notifications_subscribe', 'subscriber_nonce'); ?>
                
                <h3><?php _e('Contact Information', 'subscriber-notifications'); ?></h3>
                
                <div class="form-group">
                    <label for="subscriber_name"><?php _e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name" required>
                </div>
                
                <div class="form-group">
                    <label for="subscriber_email"><?php _e('Email', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="email" id="subscriber_email" name="subscriber_email" required>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('News Alert Preferences', 'subscriber-notifications'); ?></h3>
                    <?php if (!empty($news_categories)): ?>
                        <label class="checkbox-label select-all-label">
                            <input type="checkbox" id="select-all-news" class="select-all-checkbox" data-target="news_categories">
                            <strong><?php _e('Select All News Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes">
                            <?php foreach ($news_categories as $category): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="news_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="news-category-checkbox">
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No news categories available.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('Meeting Alert Preferences', 'subscriber-notifications'); ?></h3>
                    <?php if (!empty($meeting_categories)): ?>
                        <label class="checkbox-label select-all-label">
                            <input type="checkbox" id="select-all-meetings" class="select-all-checkbox" data-target="meeting_categories">
                            <strong><?php _e('Select All Meeting Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes">
                            <?php foreach ($meeting_categories as $category): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="meeting_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="meeting-category-checkbox">
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No meeting categories available.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('How often would you like to receive alerts?', 'subscriber-notifications'); ?></h3>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="daily" required>
                        <?php _e('Daily', 'subscriber-notifications'); ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="weekly" required>
                        <?php _e('Weekly', 'subscriber-notifications'); ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="monthly" required>
                        <?php _e('Monthly', 'subscriber-notifications'); ?>
                    </label>
                </div>
                
                <?php if (!empty(get_option('captcha_site_key', ''))): ?>
                <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(get_option('captcha_site_key')); ?>"></div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="button button-primary">
                        <?php _e('Subscribe', 'subscriber-notifications'); ?>
                    </button>
                </div>
                
                <div id="subscriber-message" class="subscriber-message" style="display: none;"></div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle subscription form submission
     */
    public function handle_subscription() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['subscriber_nonce'], 'subscriber_notifications_subscribe')) {
            wp_die(__('Security check failed.', 'subscriber-notifications'));
        }
        
        // Verify CAPTCHA if enabled
        if (!empty(get_option('captcha_site_key', ''))) {
            if (!$this->verify_captcha($_POST['g-recaptcha-response'])) {
                wp_send_json_error(__('CAPTCHA verification failed.', 'subscriber-notifications'));
                return;
            }
        }
        
        // Sanitize and validate data
        $name = sanitize_text_field($_POST['subscriber_name']);
        $email = sanitize_email($_POST['subscriber_email']);
        $news_categories = isset($_POST['news_categories']) ? array_map('intval', $_POST['news_categories']) : array();
        $meeting_categories = isset($_POST['meeting_categories']) ? array_map('intval', $_POST['meeting_categories']) : array();
        $frequency = sanitize_text_field($_POST['frequency']);
        
        // Validate required fields
        if (empty($name) || empty($email) || !is_email($email)) {
            wp_send_json_error(__('Please provide a valid name and email address.', 'subscriber-notifications'));
            return;
        }
        
        // Check if email already exists
        $existing_subscriber = $this->database->get_subscriber_by_email($email);
        if ($existing_subscriber) {
            // Check if subscriber is inactive (can resubscribe)
            if ($existing_subscriber->status === 'inactive') {
                // Reactivate subscriber and update preferences
                $update_data = array(
                    'name' => $name,
                    'news_categories' => implode(',', $news_categories),
                    'meeting_categories' => implode(',', $meeting_categories),
                    'frequency' => $frequency,
                    'status' => 'active',
                    'management_token' => wp_generate_password(32, false)
                    // Note: date_added is NOT included to preserve original subscription date
                );
                
                $updated = $this->database->update_subscriber($existing_subscriber->id, $update_data);
                
                if ($updated !== false) {
                    // Get updated subscriber object
                    $subscriber = $this->database->get_subscriber($existing_subscriber->id);
                    
                    if ($subscriber) {
                        // Send welcome back email
                        $this->send_welcome_back_email($subscriber);
                    }
                    
                    wp_send_json_success(__('Welcome back! Your subscription has been reactivated.', 'subscriber-notifications'));
                } else {
                    wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
                }
                return;
            } else {
                // Subscriber is already active
                wp_send_json_error(__('This email address is already subscribed.', 'subscriber-notifications'));
                return;
            }
        }
        
        // Prepare subscriber data for new subscriber
        $subscriber_data = array(
            'name' => $name,
            'email' => $email,
            'news_categories' => implode(',', $news_categories),
            'meeting_categories' => implode(',', $meeting_categories),
            'frequency' => $frequency,
            'status' => 'active',
            'management_token' => wp_generate_password(32, false)
        );
        
        // Add subscriber to database
        $subscriber_id = $this->database->add_subscriber($subscriber_data);
        
        if ($subscriber_id) {
            // Get the subscriber object for welcome email
            $subscriber = $this->database->get_subscriber($subscriber_id);
            
            if ($subscriber) {
                // Send welcome email immediately
                $this->send_welcome_email($subscriber);
            }
            
            wp_send_json_success(__('Thank you for subscribing! You will now receive notifications according to your preferences.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
        }
    }
    
    /**
     * Verify CAPTCHA
     * 
     * @param string $response CAPTCHA response
     * @return bool True if valid, false otherwise
     */
    private function verify_captcha($response) {
        $secret_key = get_option('captcha_secret_key', '');
        if (empty($secret_key)) {
            return true; // No CAPTCHA configured
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        // Sanitize and validate IP address
        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        // Validate IP format
        if (!empty($remote_ip) && !filter_var($remote_ip, FILTER_VALIDATE_IP)) {
            $remote_ip = '';
        }
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $remote_ip
        );
        
        $response = wp_remote_post($url, array(
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return isset($result['success']) && $result['success'] === true;
    }
    
    
    
    
    /**
     * Send welcome email after verification
     */
    private function send_welcome_email($subscriber) {
        $subject = get_option('welcome_email_subject', __('Welcome! Your subscription is confirmed', 'subscriber-notifications'));
        $content = get_option('welcome_email_content', __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'));
        
        // Process shortcodes
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);
        
        // Apply custom CSS if set
        $email_css = get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);
        
        // Send email using current mail method
        $settings = get_option('subscriber_notifications_settings', array());
        $mail_method = isset($settings['mail_method']) ? $settings['mail_method'] : 'wp_mail';
        
        if ($mail_method === 'sendgrid') {
            $sendgrid = new SubscriberNotifications_SendGrid();
            $sendgrid->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
        } else {
            // Use WordPress default mail
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($subscriber->email, $processed_subject, $processed_content, $headers);
        }
    }
    
    /**
     * Send welcome back email for resubscribing users
     * 
     * @param object $subscriber Subscriber object
     */
    private function send_welcome_back_email($subscriber) {
        $subject = get_option('welcome_back_email_subject', __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'));
        $content = get_option('welcome_back_email_content', __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'));
        
        // Process shortcodes
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);
        
        // Apply custom CSS if set
        $email_css = get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);
        
        // Send email using current mail method
        $settings = get_option('subscriber_notifications_settings', array());
        $mail_method = isset($settings['mail_method']) ? $settings['mail_method'] : 'wp_mail';
        
        if ($mail_method === 'sendgrid') {
            $sendgrid = new SubscriberNotifications_SendGrid();
            $sendgrid->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
        } else {
            // Use WordPress default mail
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($subscriber->email, $processed_subject, $processed_content, $headers);
        }
    }
    
    /**
     * Wrap content with CSS
     * 
     * @deprecated 2.2.0 Use SubscriberNotifications_Email_Formatter::get_instance()->wrap_content_with_css() instead
     * @param string $content Email content
     * @param string $css Custom CSS
     * @param object|null $subscriber Subscriber object for shortcode processing
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
     * Handle unsubscribe and manage preferences routes
     */
    public function handle_unsubscribe() {
        if (isset($_GET['action']) && isset($_GET['token'])) {
            $action = sanitize_text_field($_GET['action']);
            $token = sanitize_text_field($_GET['token']);
            
            if ($action === 'unsubscribe') {
                // Redirect old unsubscribe links to manage page
                wp_redirect(add_query_arg(array(
                    'action' => 'manage',
                    'token' => $token
                ), home_url()));
                exit;
            } elseif ($action === 'manage') {
                // Show preferences management form
                $this->render_preferences_form($token);
                exit;
            }
        }
    }
    
    /**
     * Render preferences management form
     * 
     * @param string $token Management token
     */
    private function render_preferences_form($token) {
        $token = trim($token);
        
        if (empty($token)) {
            wp_die(__('Invalid management link.', 'subscriber-notifications'), __('Error', 'subscriber-notifications'), array('response' => 404));
        }
        
        $subscriber = $this->database->get_subscriber_by_management_token($token);
        
        if (!$subscriber) {
            wp_die(__('Invalid management link.', 'subscriber-notifications'), __('Error', 'subscriber-notifications'), array('response' => 404));
        }
        
        // Security check: Re-fetch subscriber to ensure we have the latest token
        // This prevents issues with stale data if token was recently changed
        $fresh_subscriber = $this->database->get_subscriber($subscriber->id);
        if (!$fresh_subscriber || $fresh_subscriber->management_token !== $token) {
            get_header();
            ?>
            <div class="subscriber-notifications-form" style="max-width: 600px; margin: 140px auto 20px auto; padding: 20px; background: #F2F2F2;">
                <h2><?php _e('Link Expired', 'subscriber-notifications'); ?></h2>
                <p><?php _e('This management link has expired. Please use the most recent link from your email, or subscribe again using the form below.', 'subscriber-notifications'); ?></p>
                <?php echo do_shortcode('[subscriber_notifications_form]'); ?>
            </div>
            <?php
            get_footer();
            exit;
        }
        
        // Use fresh subscriber data to ensure we show current preferences
        $subscriber = $fresh_subscriber;
        
        $news_categories = get_categories(array('hide_empty' => false));
        $meeting_categories = $this->get_meeting_categories();
        
        // Get current selections
        $current_news_categories = !empty($subscriber->news_categories) ? explode(',', $subscriber->news_categories) : array();
        $current_meeting_categories = !empty($subscriber->meeting_categories) ? explode(',', $subscriber->meeting_categories) : array();
        
        // Get WordPress header and footer
        get_header();
        ?>
        <div class="subscriber-notifications-form">
            <h2><?php _e('Manage Your Preferences', 'subscriber-notifications'); ?></h2>
            
            <form id="subscriber-preferences-form" method="post">
                <?php wp_nonce_field('subscriber_notifications_update_preferences', 'preferences_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                
                <h3><?php _e('Contact Information', 'subscriber-notifications'); ?></h3>
                
                <div class="form-group">
                    <label for="subscriber_name"><?php _e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name" value="<?php echo esc_attr($subscriber->name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="subscriber_email"><?php _e('Email', 'subscriber-notifications'); ?></label>
                    <input type="email" id="subscriber_email" name="subscriber_email" value="<?php echo esc_attr($subscriber->email); ?>" disabled>
                    <p class="description"><?php _e('Email address cannot be changed.', 'subscriber-notifications'); ?></p>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('News Alert Preferences', 'subscriber-notifications'); ?></h3>
                    <?php if (!empty($news_categories)): ?>
                        <label class="checkbox-label select-all-label">
                            <input type="checkbox" id="select-all-news-prefs" class="select-all-checkbox" data-target="news_categories">
                            <strong><?php _e('Select All News Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes">
                            <?php foreach ($news_categories as $category): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="news_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="news-category-checkbox" <?php checked(in_array($category->term_id, $current_news_categories)); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No news categories available.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('Meeting Alert Preferences', 'subscriber-notifications'); ?></h3>
                    <?php if (!empty($meeting_categories)): ?>
                        <label class="checkbox-label select-all-label">
                            <input type="checkbox" id="select-all-meetings-prefs" class="select-all-checkbox" data-target="meeting_categories">
                            <strong><?php _e('Select All Meeting Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes">
                            <?php foreach ($meeting_categories as $category): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="meeting_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="meeting-category-checkbox" <?php checked(in_array($category->term_id, $current_meeting_categories)); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No meeting categories available.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('How often would you like to receive alerts?', 'subscriber-notifications'); ?></h3>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="daily" <?php checked($subscriber->frequency, 'daily'); ?> required>
                        <?php _e('Daily', 'subscriber-notifications'); ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="weekly" <?php checked($subscriber->frequency, 'weekly'); ?> required>
                        <?php _e('Weekly', 'subscriber-notifications'); ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="frequency" value="monthly" <?php checked($subscriber->frequency, 'monthly'); ?> required>
                        <?php _e('Monthly', 'subscriber-notifications'); ?>
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="button button-primary">
                        <?php _e('Update Preferences', 'subscriber-notifications'); ?>
                    </button>
                </div>
                
                <div id="preferences-message" class="subscriber-message" style="display: none;"></div>
            </form>
            
            <div class="unsubscribe-section">
                <hr style="margin: 30px 0; border: none; border-top: 2px solid #ddd;">
                <h3><?php _e('Unsubscribe', 'subscriber-notifications'); ?></h3>
                <p><?php _e('If you no longer wish to receive notifications, you can unsubscribe below.', 'subscriber-notifications'); ?></p>
                <button type="button" id="unsubscribe-button" class="button button-secondary unsubscribe-button">
                    <?php _e('Unsubscribe', 'subscriber-notifications'); ?>
                </button>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Handle preferences update AJAX request
     */
    public function handle_preferences_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['preferences_nonce'], 'subscriber_notifications_update_preferences')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }
        
        $token = sanitize_text_field($_POST['token']);
        $subscriber = $this->database->get_subscriber_by_management_token($token);
        
        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }
        
        // Sanitize and validate data
        $name = sanitize_text_field($_POST['subscriber_name']);
        $news_categories = isset($_POST['news_categories']) ? array_map('intval', $_POST['news_categories']) : array();
        $meeting_categories = isset($_POST['meeting_categories']) ? array_map('intval', $_POST['meeting_categories']) : array();
        $frequency = sanitize_text_field($_POST['frequency']);
        
        // Validate required fields
        if (empty($name) || empty($frequency)) {
            wp_send_json_error(__('Please provide a valid name and frequency preference.', 'subscriber-notifications'));
            return;
        }
        
        // Update subscriber data
        $update_data = array(
            'name' => $name,
            'news_categories' => implode(',', $news_categories),
            'meeting_categories' => implode(',', $meeting_categories),
            'frequency' => $frequency
        );
        
        $result = $this->database->update_subscriber($subscriber->id, $update_data);
        
        if ($result !== false) {
            // Get updated subscriber for email
            $updated_subscriber = $this->database->get_subscriber($subscriber->id);
            
            if ($updated_subscriber) {
                // Send confirmation email
                $this->send_preferences_update_email($updated_subscriber);
            }
            
            wp_send_json_success(__('Your preferences have been updated successfully.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred while updating your preferences. Please try again.', 'subscriber-notifications'));
        }
    }
    
    /**
     * Handle unsubscribe AJAX request
     */
    public function handle_unsubscribe_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['unsubscribe_nonce'], 'subscriber_notifications_unsubscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }
        
        $token = sanitize_text_field($_POST['token']);
        $subscriber = $this->database->get_subscriber_by_management_token($token);
        
        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }
        
        // Update subscriber status to inactive
        $result = $this->database->update_subscriber($subscriber->id, array('status' => 'inactive'));
        
        if ($result !== false) {
            wp_send_json_success(__('You have been successfully unsubscribed from our notifications.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred while unsubscribing. Please try again.', 'subscriber-notifications'));
        }
    }
    
    /**
     * Send preferences update confirmation email
     * 
     * @param object $subscriber Subscriber object
     */
    private function send_preferences_update_email($subscriber) {
        $subject = get_option('preferences_update_email_subject', __('Your preferences have been updated', 'subscriber-notifications'));
        $content = get_option('preferences_update_email_content', __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n" . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n" . __('Your current preferences:', 'subscriber-notifications') . "\n" . __('News Categories: [selected_news_categories]', 'subscriber-notifications') . "\n" . __('Meeting Categories: [selected_meeting_categories]', 'subscriber-notifications') . "\n" . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n" . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications'));
        
        // Process shortcodes
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);
        
        // Apply custom CSS if set
        $email_css = get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);
        
        // Send email using current mail method
        $settings = get_option('subscriber_notifications_settings', array());
        $mail_method = isset($settings['mail_method']) ? $settings['mail_method'] : 'wp_mail';
        
        if ($mail_method === 'sendgrid') {
            $sendgrid = new SubscriberNotifications_SendGrid();
            $sendgrid->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
        } else {
            // Use WordPress default mail
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($subscriber->email, $processed_subject, $processed_content, $headers);
        }
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
}
<?php
/**
 * Frontend functionality class.
 *
 * Renders the subscribe form and preferences page using the configurable Content Types
 * system introduced in v3.0. Markup leans on native HTML elements (`<details>` /
 * `<summary>`, plain inputs and buttons) so the active theme controls the
 * presentation. The plugin only contributes minimal layout CSS.
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
     * Database instance.
     *
     * @var SubscriberNotifications_Database
     */
    private $database;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Database $database Database instance.
     */
    public function __construct($database) {
        $this->database = $database;
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('wp_ajax_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_nopriv_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('wp_ajax_nopriv_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('template_redirect', array($this, 'handle_manage_preferences_route'));
        add_shortcode('subscriber_notifications_form', array($this, 'subscription_form_shortcode'));
    }

    /**
     * Enqueue CSS/JS when subscription or preferences UI is rendered.
     */
    private function enqueue_subscription_form_assets() {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }
        $enqueued = true;

        wp_enqueue_script('jquery');

        $site_key = subscriber_notifications_get_option('captcha_site_key', '');
        if ('' !== $site_key) {
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

        $logged_in_contact = $this->get_logged_in_contact_for_form();
        $is_logged_in      = is_user_logged_in();

        wp_localize_script(
            'subscriber-notifications-frontend',
            'subscriberNotifications',
            array(
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'homeUrl'          => home_url('/'),
                'nonce'            => wp_create_nonce('subscriber_notifications_nonce'),
                'unsubscribeNonce' => wp_create_nonce('subscriber_notifications_unsubscribe'),
                'siteKey'          => $site_key,
                'isLoggedIn'       => $is_logged_in,
                'lockedName'       => $logged_in_contact ? $logged_in_contact['name'] : '',
                'lockedEmail'      => $logged_in_contact ? $logged_in_contact['email'] : '',
                'i18n'             => array(
                    'subscribing'         => __('Subscribing...', 'subscriber-notifications'),
                    'subscribe'           => __('Subscribe', 'subscriber-notifications'),
                    'updating'            => __('Updating...', 'subscriber-notifications'),
                    'update'              => __('Update Preferences', 'subscriber-notifications'),
                    'unsubscribing'       => __('Unsubscribing...', 'subscriber-notifications'),
                    'unsubscribe'         => __('Unsubscribe', 'subscriber-notifications'),
                    'confirmUnsubscribe'  => __('Are you sure you want to unsubscribe? You will no longer receive any notifications.', 'subscriber-notifications'),
                    'genericError'        => __('An error occurred. Please try again.', 'subscriber-notifications'),
                    'errorAtLeastOneTerm' => __('Please select at least one option to subscribe to.', 'subscriber-notifications'),
                    'errorNameLength'     => __('Name must be at least 2 characters long.', 'subscriber-notifications'),
                    'errorEmail'          => __('Please enter a valid email address.', 'subscriber-notifications'),
                    'errorFrequency'      => __('Please select a frequency preference.', 'subscriber-notifications'),
                    'errorMissingProfileName' => __('Please add your name to your account profile before subscribing.', 'subscriber-notifications'),
                ),
            )
        );

        wp_enqueue_style(
            'subscriber-notifications-frontend',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SUBSCRIBER_NOTIFICATIONS_VERSION
        );
    }

    /**
     * Subscription form shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Form HTML.
     */
    public function subscription_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Subscribe to Notifications', 'subscriber-notifications'),
        ), $atts);

        if (!SubscriberNotifications_Content_Config::is_configured()) {
            return $this->render_not_configured_message();
        }

        $this->enqueue_subscription_form_assets();

        ob_start();
        $this->render_subscription_form($atts);
        return ob_get_clean();
    }

    /**
     * Render a friendly placeholder when Content Types has no enabled post type + taxonomy.
     */
    private function render_not_configured_message() {
        $message = current_user_can('manage_options')
            ? __('Subscriptions are not configured. Visit Notifications → Content Types to enable post types and taxonomies for subscription.', 'subscriber-notifications')
            : __('Subscriptions are not available at this time. Please check back soon.', 'subscriber-notifications');

        return '<div class="subscriber-notifications-form subscriber-notifications-empty"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Render the subscription form (logged-in or anonymous).
     *
     * @param array $atts Form attributes.
     */
    private function render_subscription_form($atts) {
        $logged_in_contact = $this->get_logged_in_contact_for_form();
        $is_locked         = is_user_logged_in() && null !== $logged_in_contact;
        $name_value        = $is_locked ? $logged_in_contact['name'] : '';
        $email_value       = $is_locked ? $logged_in_contact['email'] : '';
        $locked_class      = $is_locked ? 'subscriber-notifications-field--locked' : '';
        ?>
        <div class="subscriber-notifications-form">
            <form id="subscriber-notifications-form" method="post">
                <?php wp_nonce_field('subscriber_notifications_subscribe', 'subscriber_nonce'); ?>

                <h3><?php esc_html_e('Contact Information', 'subscriber-notifications'); ?></h3>

                <p>
                    <label for="subscriber_name"><?php esc_html_e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name"
                        value="<?php echo esc_attr($name_value); ?>"
                        class="<?php echo esc_attr($locked_class); ?>"
                        <?php echo $is_locked ? 'readonly' : ''; ?> required>
                    <?php if ($is_locked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account profile.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="subscriber_email"><?php esc_html_e('Email', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="email" id="subscriber_email" name="subscriber_email"
                        value="<?php echo esc_attr($email_value); ?>"
                        class="<?php echo esc_attr($locked_class); ?>"
                        <?php echo $is_locked ? 'readonly' : ''; ?> required>
                    <?php if ($is_locked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account email address.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <h3><?php esc_html_e('Choose your subscriptions', 'subscriber-notifications'); ?></h3>
                <?php $this->render_preferences_sections(array()); ?>

                <h3><?php esc_html_e('How often would you like to receive notifications?', 'subscriber-notifications'); ?></h3>
                <?php $this->render_frequency_fieldset(''); ?>

                <?php if (!empty(subscriber_notifications_get_option('captcha_site_key', ''))): ?>
                    <p>
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(subscriber_notifications_get_option('captcha_site_key')); ?>"></div>
                    </p>
                <?php endif; ?>

                <p>
                    <button type="submit" class="subscriber-notifications-submit wp-element-button">
                        <?php esc_html_e('Subscribe', 'subscriber-notifications'); ?>
                    </button>
                </p>

                <div id="subscriber-message" class="subscriber-message" style="display: none;"></div>
            </form>
        </div>
        <?php
    }

    /**
     * Render one `<details>` block per enabled post type with a checklist per
     * `enabled_on_form` taxonomy inside.
     *
     * @param array $current_prefs Existing subscriber preferences (preferences shape).
     */
    private function render_preferences_sections(array $current_prefs) {
        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        foreach ($enabled_post_types as $post_type) {
            $form_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            if (empty($form_taxonomies)) {
                continue;
            }
            $post_type_label = SubscriberNotifications_Content_Config::get_post_type_label($post_type);
            ?>
            <details class="sn-section">
                <summary><strong><?php echo esc_html($post_type_label); ?></strong></summary>
                <div class="sn-section__body">
                <?php foreach ($form_taxonomies as $taxonomy) :
                    $tax_label = SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy);
                    $terms = SubscriberNotifications_Term_Resolver::get_terms_for_public_form($post_type, $taxonomy);
                    if (empty($terms)) {
                        continue;
                    }
                    $field_name = 'preferences[' . $post_type . '][' . $taxonomy . '][]';
                    $select_all_target = 'preferences[' . $post_type . '][' . $taxonomy . ']';
                    $selected_ids = isset($current_prefs[$post_type][$taxonomy]) && is_array($current_prefs[$post_type][$taxonomy])
                        ? array_map('intval', $current_prefs[$post_type][$taxonomy])
                        : array();
                ?>
                    <fieldset class="sn-taxonomy" data-target="<?php echo esc_attr($select_all_target); ?>">
                        <legend><?php echo esc_html($tax_label); ?></legend>
                        <label class="sn-select-all-label">
                            <input type="checkbox" class="sn-select-all" data-target="<?php echo esc_attr($select_all_target); ?>" />
                            <?php
                            /* translators: %s: Taxonomy label */
                            printf(esc_html__('Select all %s', 'subscriber-notifications'), esc_html($tax_label));
                            ?>
                        </label>
                        <?php
                        SubscriberNotifications_Term_Checklist::render(
                            $terms,
                            $field_name,
                            $selected_ids,
                            $taxonomy
                        );
                        ?>
                    </fieldset>
                <?php endforeach; ?>
                </div>
            </details>
            <?php
        }
    }

    /**
     * Render the frequency field label and radio fieldset.
     *
     * @param string $current Current frequency value.
     */
    private function render_frequency_fieldset($current) {
        $options = array(
            'daily'   => __('Daily', 'subscriber-notifications'),
            'weekly'  => __('Weekly', 'subscriber-notifications'),
            'monthly' => __('Monthly', 'subscriber-notifications'),
        );
        $is_first = true;
        ?>
        <p>
            <label id="sn-frequency-label"><?php esc_html_e('Delivery Frequency', 'subscriber-notifications'); ?> <span class="required">*</span></label>
        </p>
        <fieldset class="sn-frequency" aria-required="true" aria-labelledby="sn-frequency-label">
            <legend class="screen-reader-text"><?php esc_html_e('Delivery Frequency', 'subscriber-notifications'); ?></legend>
            <?php foreach ($options as $value => $label) : ?>
                <label>
                    <input type="radio" name="frequency" value="<?php echo esc_attr($value); ?>"
                        <?php checked($current, $value); ?>
                        <?php echo $is_first ? 'required aria-required="true"' : ''; ?>>
                    <?php echo esc_html($label); ?>
                </label>
                <?php
                $is_first = false;
            endforeach;
            ?>
        </fieldset>
        <?php
    }

    /**
     * Handle subscription form submission.
     */
    public function handle_subscription() {
        $nonce = isset($_POST['subscriber_nonce']) ? sanitize_text_field(wp_unslash($_POST['subscriber_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_subscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        if (!SubscriberNotifications_Content_Config::is_configured()) {
            wp_send_json_error(__('Subscriptions are not configured.', 'subscriber-notifications'));
            return;
        }

        if (!empty(subscriber_notifications_get_option('captcha_site_key', ''))) {
            $captcha = isset($_POST['g-recaptcha-response']) ? wp_unslash($_POST['g-recaptcha-response']) : '';
            if (!$this->verify_captcha($captcha)) {
                wp_send_json_error(__('CAPTCHA verification failed.', 'subscriber-notifications'));
                return;
            }
        }

        $raw_prefs = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        $prefs     = SubscriberNotifications_Preferences::sanitize_from_post($raw_prefs);
        $prefs     = SubscriberNotifications_Preferences::prune_to_allowed_terms($prefs, 'public');

        if (!SubscriberNotifications_Preferences::has_at_least_one_term($prefs)) {
            wp_send_json_error(__('Please select at least one option to subscribe to.', 'subscriber-notifications'));
            return;
        }

        $frequency = sanitize_text_field(wp_unslash($_POST['frequency'] ?? ''));
        if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true)) {
            wp_send_json_error(__('Please select a frequency preference.', 'subscriber-notifications'));
            return;
        }

        $wp_user_id = 0;

        if (is_user_logged_in()) {
            $contact = $this->get_logged_in_contact_for_form();
            if (!$contact) {
                wp_send_json_error(__('Unable to load your account information. Please try again.', 'subscriber-notifications'));
                return;
            }
            if ('' === $contact['name']) {
                wp_send_json_error(__('Please add your first or last name to your profile before subscribing.', 'subscriber-notifications'));
                return;
            }
            if (empty($contact['email']) || !is_email($contact['email'])) {
                wp_send_json_error(__('Your account does not have a valid email address.', 'subscriber-notifications'));
                return;
            }
            $name       = $contact['name'];
            $email      = $contact['email'];
            $wp_user_id = $contact['user_id'];
        } else {
            $name  = sanitize_text_field(wp_unslash($_POST['subscriber_name'] ?? ''));
            $email = sanitize_email(wp_unslash($_POST['subscriber_email'] ?? ''));

            if (empty($name) || empty($email) || !is_email($email)) {
                wp_send_json_error(__('Please provide a valid name and email address.', 'subscriber-notifications'));
                return;
            }
        }

        $existing_subscriber = $this->find_existing_subscriber_for_subscription($email, $wp_user_id);

        if ($existing_subscriber) {
            if ($existing_subscriber->status === 'inactive') {
                $update_data = array(
                    'name'                     => $name,
                    'email'                    => $email,
                    'subscription_preferences' => $prefs,
                    'frequency'                => $frequency,
                    'status'                   => 'active',
                    'management_token'         => wp_generate_password(32, false),
                );

                if ($wp_user_id > 0) {
                    $update_data['user_id'] = $wp_user_id;
                }

                $updated = $this->database->update_subscriber($existing_subscriber->id, $update_data);

                if ($updated !== false) {
                    $subscriber = $this->database->get_subscriber($existing_subscriber->id);
                    if ($subscriber) {
                        $this->send_welcome_back_email($subscriber);
                    }
                    wp_send_json_success(__('Welcome back! Your subscription has been reactivated.', 'subscriber-notifications'));
                } else {
                    wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
                }
                return;
            }

            wp_send_json_error(__('This email address is already subscribed.', 'subscriber-notifications'));
            return;
        }

        $subscriber_data = array(
            'name'                     => $name,
            'email'                    => $email,
            'subscription_preferences' => $prefs,
            'frequency'                => $frequency,
            'status'                   => 'active',
            'management_token'         => wp_generate_password(32, false),
        );

        if ($wp_user_id > 0) {
            $subscriber_data['user_id'] = $wp_user_id;
        }

        $subscriber_id = $this->database->add_subscriber($subscriber_data);

        if ($subscriber_id) {
            $subscriber = $this->database->get_subscriber($subscriber_id);
            if ($subscriber) {
                $this->send_welcome_email($subscriber);
            }
            wp_send_json_success(__('Thank you for subscribing! You will now receive notifications according to your preferences.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Verify reCAPTCHA response.
     *
     * @param string $response CAPTCHA response token.
     * @return bool
     */
    private function verify_captcha($response) {
        $secret_key = subscriber_notifications_get_option('captcha_secret_key', '');
        if (empty($secret_key)) {
            return true;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!empty($remote_ip) && !filter_var($remote_ip, FILTER_VALIDATE_IP)) {
            $remote_ip = '';
        }
        $data = array(
            'secret'   => $secret_key,
            'response' => $response,
            'remoteip' => $remote_ip,
        );

        $http = wp_remote_post($url, array(
            'body'    => $data,
            'timeout' => 30,
        ));

        if (is_wp_error($http)) {
            return false;
        }

        $body   = wp_remote_retrieve_body($http);
        $result = json_decode($body, true);

        return isset($result['success']) && $result['success'] === true;
    }

    /**
     * Send welcome email after subscribe.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_welcome_email($subscriber) {
        $subject = subscriber_notifications_get_option('welcome_email_subject', __('Welcome! Your subscription is confirmed', 'subscriber-notifications'));
        $content = subscriber_notifications_get_option('welcome_email_content', __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
    }

    /**
     * Send welcome-back email after reactivating a subscription.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_welcome_back_email($subscriber) {
        $subject = subscriber_notifications_get_option('welcome_back_email_subject', __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'));
        $content = subscriber_notifications_get_option('welcome_back_email_content', __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
    }

    /**
     * Handle manage preferences route.
     */
    public function handle_manage_preferences_route() {
        if (!isset($_GET['action'], $_GET['token'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_GET['action']));
        $token  = sanitize_text_field(wp_unslash($_GET['token']));

        if ($action === 'manage') {
            $this->render_preferences_form($token);
            exit;
        }
    }

    /**
     * Render preferences management form.
     *
     * @param string $token Management token.
     */
    private function render_preferences_form($token) {
        $this->enqueue_subscription_form_assets();
        $token = trim($token);

        if (empty($token)) {
            wp_die(esc_html__('Invalid management link.', 'subscriber-notifications'), esc_html__('Error', 'subscriber-notifications'), array('response' => 404));
        }

        $subscriber = $this->database->get_subscriber_by_management_token($token);

        if (!$subscriber) {
            wp_die(esc_html__('Invalid management link.', 'subscriber-notifications'), esc_html__('Error', 'subscriber-notifications'), array('response' => 404));
        }

        $fresh_subscriber = $this->database->get_subscriber($subscriber->id);
        if (!$fresh_subscriber || $fresh_subscriber->management_token !== $token) {
            get_header();
            ?>
            <div class="subscriber-notifications-form">
                <h2><?php esc_html_e('Link Expired', 'subscriber-notifications'); ?></h2>
                <p><?php esc_html_e('This management link has expired. Please use the most recent link from your email, or subscribe again using the form below.', 'subscriber-notifications'); ?></p>
                <?php echo do_shortcode('[subscriber_notifications_form]'); ?>
            </div>
            <?php
            get_footer();
            exit;
        }

        $subscriber = $fresh_subscriber;

        $current_prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');

        get_header();
        ?>
        <div class="subscriber-notifications-form">
            <h2><?php esc_html_e('Manage Your Preferences', 'subscriber-notifications'); ?></h2>

            <?php if (!SubscriberNotifications_Content_Config::is_configured()) : ?>
                <p><?php esc_html_e('Subscriptions are not currently configured. You can still unsubscribe below.', 'subscriber-notifications'); ?></p>
            <?php endif; ?>

            <form id="subscriber-preferences-form" method="post">
                <?php wp_nonce_field('subscriber_notifications_update_preferences', 'preferences_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <h3><?php esc_html_e('Contact Information', 'subscriber-notifications'); ?></h3>

                <p>
                    <label for="subscriber_name"><?php esc_html_e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name" value="<?php echo esc_attr($subscriber->name); ?>" required>
                </p>

                <p>
                    <label for="subscriber_email"><?php esc_html_e('Email', 'subscriber-notifications'); ?></label>
                    <input type="email" id="subscriber_email" name="subscriber_email" value="<?php echo esc_attr($subscriber->email); ?>" disabled>
                    <small class="description"><?php esc_html_e('Email address cannot be changed.', 'subscriber-notifications'); ?></small>
                </p>

                <?php if (SubscriberNotifications_Content_Config::is_configured()) : ?>
                    <h3><?php esc_html_e('Your subscriptions', 'subscriber-notifications'); ?></h3>
                    <?php $this->render_preferences_sections($current_prefs); ?>
                <?php endif; ?>

                <h3><?php esc_html_e('How often would you like to receive notifications?', 'subscriber-notifications'); ?></h3>
                <?php $this->render_frequency_fieldset($subscriber->frequency); ?>

                <p>
                    <button type="submit" class="subscriber-notifications-submit wp-element-button">
                        <?php esc_html_e('Update Preferences', 'subscriber-notifications'); ?>
                    </button>
                </p>

                <div id="preferences-message" class="subscriber-message" style="display: none;"></div>
            </form>

            <div class="unsubscribe-section">
                <hr>
                <h3><?php esc_html_e('Unsubscribe', 'subscriber-notifications'); ?></h3>
                <p><?php esc_html_e('If you no longer wish to receive notifications, you can unsubscribe below.', 'subscriber-notifications'); ?></p>
                <button type="button" id="unsubscribe-button" class="subscriber-notifications-submit wp-element-button unsubscribe-button">
                    <?php esc_html_e('Unsubscribe', 'subscriber-notifications'); ?>
                </button>
            </div>
        </div>
        <?php
        get_footer();
    }

    /**
     * Handle preferences update AJAX request.
     */
    public function handle_preferences_update() {
        $nonce = isset($_POST['preferences_nonce']) ? sanitize_text_field(wp_unslash($_POST['preferences_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_update_preferences')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        $token      = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $subscriber = $this->database->get_subscriber_by_management_token($token);

        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }

        $name      = isset($_POST['subscriber_name']) ? sanitize_text_field(wp_unslash($_POST['subscriber_name'])) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : '';

        if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true)) {
            wp_send_json_error(__('Please select a frequency preference.', 'subscriber-notifications'));
            return;
        }

        if (empty($name)) {
            wp_send_json_error(__('Please provide a valid name.', 'subscriber-notifications'));
            return;
        }

        $raw_prefs = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        $prefs     = SubscriberNotifications_Preferences::sanitize_from_post($raw_prefs);
        $prefs     = SubscriberNotifications_Preferences::prune_to_allowed_terms($prefs, 'public');

        if (SubscriberNotifications_Content_Config::is_configured() && !SubscriberNotifications_Preferences::has_at_least_one_term($prefs)) {
            wp_send_json_error(__('Please select at least one option, or use the Unsubscribe button below.', 'subscriber-notifications'));
            return;
        }

        $update_data = array(
            'name'                     => $name,
            'subscription_preferences' => $prefs,
            'frequency'                => $frequency,
        );

        $result = $this->database->update_subscriber($subscriber->id, $update_data);

        if ($result !== false) {
            $updated_subscriber = $this->database->get_subscriber($subscriber->id);
            if ($updated_subscriber) {
                $this->send_preferences_update_email($updated_subscriber);
            }
            wp_send_json_success(__('Your preferences have been updated successfully.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred while updating your preferences. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Handle unsubscribe AJAX request.
     */
    public function handle_unsubscribe_action() {
        $nonce = isset($_POST['unsubscribe_nonce']) ? sanitize_text_field(wp_unslash($_POST['unsubscribe_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_unsubscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        $token      = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $subscriber = $this->database->get_subscriber_by_management_token($token);

        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }

        $result = $this->database->update_subscriber($subscriber->id, array('status' => 'inactive'));

        if ($result !== false) {
            wp_send_json_success(__('You have been successfully unsubscribed from our notifications.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred while unsubscribing. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Send preferences update confirmation email.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_preferences_update_email($subscriber) {
        $subject = subscriber_notifications_get_option('preferences_update_email_subject', __('Your preferences have been updated', 'subscriber-notifications'));
        $default_content = __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n"
            . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n"
            . __('Your current preferences:', 'subscriber-notifications') . "\n"
            . __('Subscriptions: [selected_subscriptions]', 'subscriber-notifications') . "\n"
            . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n"
            . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications');
        $content = subscriber_notifications_get_option('preferences_update_email_content', $default_content);

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0);
    }

    /**
     * Build display name from WordPress first_name / last_name user meta.
     *
     * @param int|WP_User $user User ID or object.
     * @return string Trimmed display name (may be empty).
     */
    private function build_display_name_from_user($user) {
        if (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!$user instanceof WP_User) {
            return '';
        }

        $first = trim((string) get_user_meta($user->ID, 'first_name', true));
        $last  = trim((string) get_user_meta($user->ID, 'last_name', true));

        if ('' !== $first && '' !== $last) {
            return trim($first . ' ' . $last);
        }

        if ('' !== $first) {
            return $first;
        }

        return $last;
    }

    /**
     * Contact fields for the subscription form when the visitor is logged in.
     *
     * @return array{user_id: int, name: string, email: string}|null Null when not logged in.
     */
    private function get_logged_in_contact_for_form() {
        if (!is_user_logged_in()) {
            return null;
        }

        $user_id = get_current_user_id();
        $user    = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return null;
        }

        $email = sanitize_email($user->user_email);

        return array(
            'user_id' => $user_id,
            'name'    => $this->build_display_name_from_user($user),
            'email'   => $email,
        );
    }

    /**
     * Find an existing subscriber row for a subscription attempt.
     *
     * @param string $email     Account or submitted email.
     * @param int    $wp_user_id WordPress user ID when logged in (0 for guests).
     * @return object|null
     */
    private function find_existing_subscriber_for_subscription($email, $wp_user_id) {
        if ($wp_user_id > 0) {
            $by_user = $this->database->get_subscriber_by_user_id($wp_user_id);
            if ($by_user) {
                return $by_user;
            }
        }

        if (!empty($email) && is_email($email)) {
            return $this->database->get_subscriber_by_email($email);
        }

        return null;
    }
}

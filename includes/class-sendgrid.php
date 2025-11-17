<?php
/**
 * SendGrid integration class
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SendGrid class for email delivery
 */
class SubscriberNotifications_SendGrid {
    
    /**
     * SendGrid API key
     */
    private $api_key;
    
    /**
     * From email
     */
    private $from_email;
    
    /**
     * From name
     */
    private $from_name;
    
    /**
     * Mail method
     */
    private $mail_method;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('sendgrid_api_key', '');
        $this->from_email = get_option('sendgrid_from_email', get_option('admin_email'));
        $this->from_name = get_option('sendgrid_from_name', get_bloginfo('name'));
        $this->mail_method = get_option('mail_method', 'sendgrid');
    }
    
    /**
     * Send email
     * 
     * @param string $to_email Recipient email
     * @param string $subject Email subject
     * @param string $content Email content
     * @param int $subscriber_id Subscriber ID
     * @param int $notification_id Notification ID
     * @return bool True on success, false on failure
     */
    public function send_email($to_email, $subject, $content, $subscriber_id = 0, $notification_id = 0) {
        // Check if we should use WordPress mail
        if ($this->mail_method === 'wp_mail' || empty($this->api_key)) {
            return $this->send_via_wp_mail($to_email, $subject, $content, $subscriber_id, $notification_id);
        }
        
        $tracking_id = wp_generate_password(32, false);
        
        // Add click tracking to links
        $content = $this->add_click_tracking($content, $tracking_id);
        
        // Add tracking pixel
        $tracking_pixel = $this->get_tracking_pixel($tracking_id);
        $content .= $tracking_pixel;
        
        // Add manage preferences link only if not already present in global footer
        $manage_url = $this->get_manage_preferences_url($subscriber_id);
        if ($manage_url && strpos($content, 'manage') === false && strpos($content, 'Manage Preferences') === false) {
            $content .= '<p><a href="' . esc_url($manage_url) . '">' . __('Manage Preferences', 'subscriber-notifications') . '</a></p>';
        }
        
        // Prepare SendGrid API request
        $data = array(
            'personalizations' => array(
                array(
                    'to' => array(
                        array('email' => $to_email)
                    ),
                    'subject' => $subject
                )
            ),
            'from' => array(
                'email' => $this->from_email,
                'name' => $this->from_name
            ),
            'content' => array(
                array(
                    'type' => 'text/html',
                    'value' => $content
                )
            ),
            'tracking_settings' => array(
                'open_tracking' => array(
                    'enable' => false
                ),
                'click_tracking' => array(
                    'enable' => false
                )
            ),
            'custom_args' => array(
                'tracking_id' => $tracking_id,
                'subscriber_id' => $subscriber_id,
                'notification_id' => $notification_id
            )
        );
        
        // Log email attempt
        $database = new SubscriberNotifications_Database();
        $log_id = $database->log_email(array(
            'subscriber_id' => $subscriber_id,
            'notification_id' => $notification_id,
            'email_type' => 'notification',
            'tracking_id' => $tracking_id
        ));
        
        // Send via SendGrid API
        $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            // Log error
            $database->update_log($log_id, array(
                'status' => 'failed',
                'error_message' => $response->get_error_message()
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            // Success
            $database->update_log($log_id, array(
                'status' => 'sent'
            ));
            return true;
        } else {
            // Error
            $database->update_log($log_id, array(
                'status' => 'failed',
                'error_message' => 'SendGrid API Error: ' . $response_code . ' - ' . $response_body
            ));
            return false;
        }
    }
    
    /**
     * Send via WordPress mail
     * 
     * @param string $to_email Recipient email
     * @param string $subject Email subject
     * @param string $content Email content
     * @param int $subscriber_id Subscriber ID
     * @param int $notification_id Notification ID
     * @return bool True on success, false on failure
     */
    private function send_via_wp_mail($to_email, $subject, $content, $subscriber_id = 0, $notification_id = 0) {
        $tracking_id = wp_generate_password(32, false);
        
        // Log email attempt
        $database = new SubscriberNotifications_Database();
        $log_id = $database->log_email(array(
            'subscriber_id' => $subscriber_id,
            'notification_id' => $notification_id,
            'email_type' => 'notification',
            'tracking_id' => $tracking_id
        ));
        
        // Add click tracking to links
        $content = $this->add_click_tracking($content, $tracking_id);
        
        // Add tracking pixel
        $tracking_pixel = $this->get_tracking_pixel($tracking_id);
        $content .= $tracking_pixel;
        
        // Add manage preferences link only if not already present in global footer
        $manage_url = $this->get_manage_preferences_url($subscriber_id);
        if ($manage_url && strpos($content, 'manage') === false && strpos($content, 'Manage Preferences') === false) {
            $content .= '<p><a href="' . esc_url($manage_url) . '">' . __('Manage Preferences', 'subscriber-notifications') . '</a></p>';
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>'
        );
        
        $result = wp_mail($to_email, $subject, $content, $headers);
        
        if ($result) {
            $database->update_log($log_id, array(
                'status' => 'sent'
            ));
        } else {
            $database->update_log($log_id, array(
                'status' => 'failed',
                'error_message' => 'WordPress mail failed'
            ));
        }
        
        return $result;
    }
    
    /**
     * Get tracking pixel
     * 
     * @param string $tracking_id Tracking ID
     * @return string Tracking pixel HTML
     */
    private function get_tracking_pixel($tracking_id) {
        $tracking_url = add_query_arg(array(
            'tracking_id' => $tracking_id
        ), home_url('track/open/'));
        
        return '<img src="' . esc_url($tracking_url) . '" width="1" height="1" style="display:none;" />';
    }
    
    /**
     * Get click tracking URL
     * 
     * @param string $url Original URL
     * @param string $tracking_id Tracking ID
     * @return string Click tracking URL
     */
    private function get_click_tracking_url($url, $tracking_id) {
        return add_query_arg(array(
            'tracking_id' => $tracking_id,
            'url' => urlencode($url)
        ), home_url('track/click/'));
    }
    
    /**
     * Add click tracking to links in content
     * 
     * @param string $content Email content
     * @param string $tracking_id Tracking ID
     * @return string Content with tracked links
     */
    private function add_click_tracking($content, $tracking_id) {
        // Pattern to match links but exclude unsubscribe links
        $pattern = '/<a\s+([^>]*?)href=["\']([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/i';
        
        return preg_replace_callback($pattern, function($matches) use ($tracking_id) {
            $full_match = $matches[0];
            $before_href = $matches[1];
            $url = $matches[2];
            $after_href = $matches[3];
            $link_text = $matches[4];
            
            // Skip unsubscribe links
            if (strpos($url, 'unsubscribe') !== false || strpos($url, 'action=unsubscribe') !== false) {
                return $full_match;
            }
            
            // Skip manage preferences links (they contain sensitive tokens)
            if (strpos($url, 'action=manage') !== false || strpos($url, 'token=') !== false) {
                return $full_match;
            }
            
            // Skip mailto links
            if (strpos($url, 'mailto:') === 0) {
                return $full_match;
            }
            
            // Skip tel links
            if (strpos($url, 'tel:') === 0) {
                return $full_match;
            }
            
            // Create tracking URL
            $tracking_url = $this->get_click_tracking_url($url, $tracking_id);
            
            return '<a ' . $before_href . 'href="' . esc_url($tracking_url) . '"' . $after_href . '>' . $link_text . '</a>';
        }, $content);
    }
    
    /**
     * Get manage preferences URL
     * 
     * @param int $subscriber_id Subscriber ID
     * @return string|false Manage preferences URL or false
     */
    private function get_manage_preferences_url($subscriber_id) {
        if (!$subscriber_id) {
            return false;
        }
        
        $database = new SubscriberNotifications_Database();
        $subscriber = $database->get_subscriber($subscriber_id);
        
        if (!$subscriber || !$subscriber->management_token) {
            return false;
        }
        
        return add_query_arg(array(
            'action' => 'manage',
            'token' => $subscriber->management_token
        ), home_url());
    }
    
    /**
     * Test SendGrid connection
     * 
     * @return array Test result
     */
    public function test_connection() {
        if ($this->mail_method === 'wp_mail') {
            return array(
                'success' => true,
                'message' => __('Using WordPress default mail (SendGrid test skipped).', 'subscriber-notifications')
            );
        }
        
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('SendGrid API key not configured.', 'subscriber-notifications')
            );
        }
        
        // Test API key by making a simple request
        $response = wp_remote_get('https://api.sendgrid.com/v3/user/profile', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => __('SendGrid connection successful.', 'subscriber-notifications')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('SendGrid API error: %d', 'subscriber-notifications'), $response_code)
            );
        }
    }
}
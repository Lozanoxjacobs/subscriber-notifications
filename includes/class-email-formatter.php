<?php
/**
 * Email formatter class
 * 
 * @package SubscriberNotifications
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email formatter class for managing email formatting and styling
 * Uses singleton pattern to match plugin architecture
 */
class SubscriberNotifications_Email_Formatter {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     * 
     * @return SubscriberNotifications_Email_Formatter Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton pattern
    }
    
    /**
     * Get the configured branding tokens (with fallbacks).
     *
     * @return array
     */
    public function get_brand_tokens(): array {
        $font_body = subscriber_notifications_get_option('email_font_body', 'Arial, Helvetica, sans-serif');
        $font_heading_raw = subscriber_notifications_get_option('email_font_heading', '');
        $font_heading = (is_string($font_heading_raw) && $font_heading_raw !== '') ? $font_heading_raw : $font_body;

        return array(
            'font_body'       => $font_body,
            'font_heading'    => $font_heading,
            'color_text'      => subscriber_notifications_get_option('email_color_text', '#333333'),
            'color_link'       => subscriber_notifications_get_option('email_color_link', '#0066cc'),
            'color_link_hover' => subscriber_notifications_get_option('email_color_link_hover', '#004499'),
            'color_bg'         => subscriber_notifications_get_option('email_color_background', '#f5f5f5'),
            'color_content'    => subscriber_notifications_get_option('email_color_content_bg', '#ffffff'),
            'color_footer_bg'  => subscriber_notifications_get_option('email_color_footer_bg', '#1d2327'),
            'color_footer_tx' => subscriber_notifications_get_option('email_color_footer_text', '#ffffff'),
        );
    }

    /**
     * Get default CSS, generated from configured brand tokens.
     *
     * @return string Default CSS for emails.
     */
    public function get_default_css(): string {
        $t = $this->get_brand_tokens();

        $font_body    = esc_attr($t['font_body']);
        $font_heading = esc_attr($t['font_heading']);
        $color_text   = esc_attr($t['color_text']);
        $color_link       = esc_attr($t['color_link']);
        $color_link_hover = esc_attr($t['color_link_hover']);
        $color_bg         = esc_attr($t['color_bg']);
        $color_card       = esc_attr($t['color_content']);
        $color_ftr_bg     = esc_attr($t['color_footer_bg']);
        $color_ftr_tx = esc_attr($t['color_footer_tx']);

        return "
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

        body {
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
            background-color: {$color_bg} !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .email-container {
            max-width: 600px !important;
            width: 100% !important;
            margin: 0 auto !important;
            background-color: {$color_card} !important;
        }

        .email-content {
            background-color: {$color_card} !important;
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
        }

        .email-content div,
        .email-content span {
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
        }

        .email-content *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6) {
            font-size: 16px !important;
        }

        .email-content h1,
        .email-content h2,
        .email-content h3,
        .email-content h4,
        .email-content h5,
        .email-content h6 {
            font-family: {$font_heading} !important;
            font-weight: 700 !important;
            color: {$color_text} !important;
        }

        .email-content h1 { font-size: 28px !important; line-height: 32px !important; margin: 0 0 20px 0 !important; }
        .email-content h2 { font-size: 24px !important; line-height: 28px !important; margin: 0 0 20px 0 !important; }
        .email-content h3 { font-size: 20px !important; line-height: 24px !important; margin: 0 0 15px 0 !important; }
        .email-content h4 { font-size: 16px !important; line-height: 22px !important; margin: 0 0 15px 0 !important; }
        .email-content h5 { font-size: 14px !important; line-height: 18px !important; margin: 0 0 10px 0 !important; }
        .email-content h6 { font-size: 12px !important; line-height: 16px !important; margin: 0 0 10px 0 !important; }

        .email-content p {
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
            margin: 0 0 15px 0 !important;
        }

        .email-content a {
            font-family: {$font_body} !important;
            color: {$color_link} !important;
            text-decoration: underline !important;
        }

        .email-content a:hover {
            color: {$color_link_hover} !important;
        }

        .email-header {
            background-color: {$color_card} !important;
            color: {$color_text} !important;
        }

        .email-header a,
        .email-header .email-header-content a {
            color: {$color_link} !important;
            text-decoration: underline !important;
        }

        .email-header .email-header-content,
        .email-header .email-header-content p,
        .email-header .email-header-content div,
        .email-header .email-header-content span,
        .email-header .email-header-content li {
            color: {$color_text} !important;
            font-family: {$font_body} !important;
        }

        .email-header h1,
        .email-header h2,
        .email-header h3,
        .email-header h4,
        .email-header h5,
        .email-header h6,
        .email-header .email-header-content h1,
        .email-header .email-header-content h2,
        .email-header .email-header-content h3,
        .email-header .email-header-content h4,
        .email-header .email-header-content h5,
        .email-header .email-header-content h6 {
            font-family: {$font_heading} !important;
            font-weight: 700 !important;
            color: {$color_text} !important;
        }

        .email-header h1,
        .email-header .email-header-content h1 { font-size: 28px !important; line-height: 32px !important; margin: 0 0 20px 0 !important; }
        .email-header h2,
        .email-header .email-header-content h2 { font-size: 24px !important; line-height: 28px !important; margin: 0 0 20px 0 !important; }
        .email-header h3,
        .email-header .email-header-content h3 { font-size: 20px !important; line-height: 24px !important; margin: 0 0 15px 0 !important; }
        .email-header h4,
        .email-header .email-header-content h4 { font-size: 16px !important; line-height: 22px !important; margin: 0 0 15px 0 !important; }
        .email-header h5,
        .email-header .email-header-content h5 { font-size: 14px !important; line-height: 18px !important; margin: 0 0 10px 0 !important; }
        .email-header h6,
        .email-header .email-header-content h6 { font-size: 12px !important; line-height: 16px !important; margin: 0 0 10px 0 !important; }

        .email-footer {
            background-color: {$color_ftr_bg} !important;
            color: {$color_ftr_tx} !important;
        }

        .email-footer a,
        .email-footer .email-footer-content a {
            color: {$color_ftr_tx} !important;
            text-decoration: underline !important;
        }

        .email-footer .email-footer-content,
        .email-footer .email-footer-content p,
        .email-footer .email-footer-content div,
        .email-footer .email-footer-content span,
        .email-footer .email-footer-content li {
            color: {$color_ftr_tx} !important;
            font-family: {$font_body} !important;
        }

        .email-footer h1,
        .email-footer h2,
        .email-footer h3,
        .email-footer h4,
        .email-footer h5,
        .email-footer h6,
        .email-footer .email-footer-content h1,
        .email-footer .email-footer-content h2,
        .email-footer .email-footer-content h3,
        .email-footer .email-footer-content h4,
        .email-footer .email-footer-content h5,
        .email-footer .email-footer-content h6 {
            font-family: {$font_heading} !important;
            font-weight: 700 !important;
            color: {$color_ftr_tx} !important;
        }

        .email-footer h1,
        .email-footer .email-footer-content h1 { font-size: 28px !important; line-height: 32px !important; margin: 0 0 20px 0 !important; }
        .email-footer h2,
        .email-footer .email-footer-content h2 { font-size: 24px !important; line-height: 28px !important; margin: 0 0 20px 0 !important; }
        .email-footer h3,
        .email-footer .email-footer-content h3 { font-size: 20px !important; line-height: 24px !important; margin: 0 0 15px 0 !important; }
        .email-footer h4,
        .email-footer .email-footer-content h4 { font-size: 16px !important; line-height: 22px !important; margin: 0 0 15px 0 !important; }
        .email-footer h5,
        .email-footer .email-footer-content h5 { font-size: 14px !important; line-height: 18px !important; margin: 0 0 10px 0 !important; }
        .email-footer h6,
        .email-footer .email-footer-content h6 { font-size: 12px !important; line-height: 16px !important; margin: 0 0 10px 0 !important; }

        .email-content ul,
        .email-content ol {
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
            margin: 0 0 15px 0 !important;
            padding-left: 20px !important;
        }

        .email-content li {
            font-family: {$font_body} !important;
            font-size: 16px !important;
            line-height: 22px !important;
            color: {$color_text} !important;
            margin: 0 0 8px 0 !important;
        }

        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .email-content { padding: 20px 15px !important; }
            .email-header, .email-footer { padding: 15px !important; }
            .email-content h1, .email-header h1, .email-header .email-header-content h1 { font-size: 24px !important; line-height: 28px !important; }
            .email-content h2, .email-header h2, .email-header .email-header-content h2 { font-size: 22px !important; line-height: 26px !important; }
            .email-content h3, .email-header h3, .email-header .email-header-content h3 { font-size: 18px !important; line-height: 22px !important; }
        }
        ";
    }
    
    /**
     * Wrap content with CSS
     * 
     * @param string $content Email content
     * @param string $css Custom CSS
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with CSS
     */
    public function wrap_content_with_css(string $content, string $css = '', $subscriber = null): string {
        // Strip any slashes that might have been added during storage/retrieval.
        $css = stripslashes($css);

        // Always start from the brand-token default CSS, then append any custom CSS as overrides.
        $css = $this->get_default_css() . "\n" . $css;

        // Check if content already has HTML structure
        if (strpos($content, '<html') !== false || strpos($content, '<body') !== false) {
            if (strpos($content, '<head>') !== false) {
                $content = str_replace('<head>', '<head><style type="text/css">' . $css . '</style>', $content);
            } else {
                $content = '<head><style type="text/css">' . $css . '</style></head>' . $content;
            }
            return $content;
        }

        return $this->wrap_with_email_structure($content, $css, $subscriber);
    }
    
    /**
     * Wrap content with proper email structure
     * 
     * @param string $content Email content
     * @param string $css CSS styles
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Wrapped content with email structure
     */
    public function wrap_with_email_structure(string $content, string $css, $subscriber = null): string {
        // Convert plain text line breaks to HTML paragraphs.
        $content = wpautop($content);

        $t = $this->get_brand_tokens();
        $color_bg      = esc_attr($t['color_bg']);
        $color_card    = esc_attr($t['color_content']);
        $color_text    = esc_attr($t['color_text']);
        $font_body     = esc_attr($t['font_body']);
        $color_ftr_bg  = esc_attr($t['color_footer_bg']);
        $color_ftr_tx  = esc_attr($t['color_footer_tx']);
        $header_style  = 'padding: 20px; color: ' . $color_text . '; font-family: ' . $font_body . ';';
        $footer_style  = 'background-color: ' . $color_ftr_bg . '; color: ' . $color_ftr_tx . '; padding: 20px; text-align: center; font-size: 14px; font-family: ' . $font_body . ';';

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html(get_bloginfo('name')) . '</title>
    <style type="text/css">
        ' . $css . '
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ' . $color_bg . ';">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; background-color: ' . $color_card . ';">
                    <tr>
                        <td class="email-header" style="' . $header_style . '">
                            ' . $this->get_global_header_content($subscriber) . '
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 30px 20px; color: ' . $color_text . '; font-family: ' . $font_body . ';">
                            ' . $content . '
                        </td>
                    </tr>
                    <tr>
                        <td class="email-footer" style="' . $footer_style . '">
                            ' . $this->get_global_footer_content($subscriber) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Get global header content
     * 
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Header HTML
     */
    public function get_global_header_content($subscriber = null): string {
        $header_logo_id = subscriber_notifications_get_option('global_header_logo', '');
        $header_content = subscriber_notifications_get_option('global_header_content', '');
        
        $logo_html = '';
        if ($header_logo_id) {
            $logo_url = wp_get_attachment_url($header_logo_id);
            if ($logo_url) {
                $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; max-height: 100px; vertical-align: middle;" />';
            }
        }
        
        $content_html = '';
        if (!empty($header_content)) {
            // Process shortcodes in header content with subscriber context
            $shortcodes = new SubscriberNotifications_Shortcodes();
            $processed_content = $shortcodes->process_shortcodes($header_content, $subscriber);
            $t            = $this->get_brand_tokens();
            $color_text   = esc_attr($t['color_text']);
            $font_body    = esc_attr($t['font_body']);
            $content_html = '<div class="email-header-content" style="color: ' . $color_text . '; vertical-align: middle; text-align: left;">'
                . $processed_content
                . '</div>';
        }
        
        // If no logo and no content, show site name as fallback
        if (empty($logo_html) && empty($content_html)) {
            $t            = $this->get_brand_tokens();
            $font_heading = esc_attr($t['font_heading']);
            $color_text   = esc_attr($t['color_text']);
            return '<h1 style="margin: 0; font-family: ' . $font_heading . '; font-weight: bold; font-size: 24px; line-height: 28px; color: ' . $color_text . '; text-align: center;">' . esc_html(get_bloginfo('name')) . '</h1>';
        }
        
        // If only logo, center it
        if (!empty($logo_html) && empty($content_html)) {
            return '<div style="text-align: center;">' . $logo_html . '</div>';
        }
        
        // If only content, center it
        if (empty($logo_html) && !empty($content_html)) {
            return '<div style="text-align: center;">' . $content_html . '</div>';
        }
        
        // Both logo and content - create two-column layout (content left, logo right)
        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="width: 50%; vertical-align: middle; text-align: left;">
                            ' . $content_html . '
                        </td>
                        <td style="width: 50%; vertical-align: middle; text-align: right;">
                            ' . $logo_html . '
                        </td>
                    </tr>
                </table>';
    }
    
    /**
     * Get global footer content
     * 
     * @param object|null $subscriber Subscriber object for shortcode processing
     * @return string Footer HTML
     */
    public function get_global_footer_content($subscriber = null): string {
        $global_footer = subscriber_notifications_get_option('global_footer', '');
        
        if (empty($global_footer)) {
            return '';
        }
        
        // Process shortcodes in footer content with subscriber context
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_footer = $shortcodes->process_shortcodes($global_footer, $subscriber);

        $t            = $this->get_brand_tokens();
        $color_ftr_tx = esc_attr($t['color_footer_tx']);
        $font_body    = esc_attr($t['font_body']);

        return '<div class="email-footer-content" style="color: ' . $color_ftr_tx . '; font-family: ' . $font_body . ';">'
            . $processed_footer
            . '</div>';
    }
}


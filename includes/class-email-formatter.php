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
     * Get default CSS
     * 
     * @return string Default CSS for emails
     */
    public function get_default_css(): string {
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
     * Process CSS for email
     * 
     * Note: CSS is kept in <style> tags rather than inlined. Most modern email clients
     * support <style> tags. For clients that don't, the email will still be readable
     * but may not have custom styling.
     * 
     * @param string $html HTML content with CSS in <style> tag
     * @param string $css CSS styles (unused, kept for backward compatibility)
     * @return string HTML with CSS in <style> tag
     */
    private function inline_css(string $html, string $css = ''): string {
        // Simply return the HTML as-is - CSS is already in <style> tags
        // Most email clients support <style> tags, so inlining is not necessary
        return $html;
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
        // Strip any slashes that might have been added during storage/retrieval
        $css = stripslashes($css);
        
        // Get default CSS if no custom CSS is provided
        if (empty($css)) {
            $css = $this->get_default_css();
        }
        
        // Check if content already has HTML structure
        if (strpos($content, '<html') !== false || strpos($content, '<body') !== false) {
            // Content already has HTML structure, just add CSS to head
            if (strpos($content, '<head>') !== false) {
                $content = str_replace('<head>', '<head><style type="text/css">' . $css . '</style>', $content);
            } else {
                $content = '<head><style type="text/css">' . $css . '</style></head>' . $content;
            }
            
            // Process CSS (kept in <style> tags for email client compatibility)
            $content = $this->inline_css($content, $css);
        } else {
            // Wrap plain content with proper email structure and CSS
            $content = $this->wrap_with_email_structure($content, $css, $subscriber);
            
            // Process CSS (kept in <style> tags for email client compatibility)
            $content = $this->inline_css($content, $css);
        }

        return $content;
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
        // Convert plain text line breaks to HTML paragraphs
        // wpautop handles both plain text and existing HTML properly
        // It won't double-wrap content that already has <p> tags
        $content = wpautop($content);
        
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
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px;">
                    <!-- Header -->
                    <tr>
                        <td class="email-header" style="padding: 20px;">
                            ' . $this->get_global_header_content($subscriber) . '
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td class="email-content" style="padding: 30px 20px;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" style="background-color: #010371; color: #ffffff; padding: 20px; text-align: center; font-size: 14px;">
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
        $header_logo_id = get_option('global_header_logo', '');
        $header_content = get_option('global_header_content', '');
        
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
            $content_html = '<div style="vertical-align: middle; text-align: left;">' . $processed_content . '</div>';
        }
        
        // If no logo and no content, show site name as fallback
        if (empty($logo_html) && empty($content_html)) {
            return '<h1 style="margin: 0; font-family: \'Montserrat\', Arial, sans-serif; font-weight: bold; font-size: 24px; line-height: 28px; color: #000000; text-align: center;">' . esc_html(get_bloginfo('name')) . '</h1>';
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
        $global_footer = get_option('global_footer', '');
        
        if (empty($global_footer)) {
            return '';
        }
        
        // Process shortcodes in footer content with subscriber context
        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_footer = $shortcodes->process_shortcodes($global_footer, $subscriber);
        
        // Wrap footer content with white text styling
        return '<div style="color: #ffffff !important; font-family: \'Montserrat\', Arial, sans-serif;">
                    ' . $processed_footer . '
                </div>';
    }
}


<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Subscriber Notifications Settings', 'subscriber-notifications'); ?></h1>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=subscriber-notifications-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'subscriber-notifications'); ?>
        </a>
        <a href="?page=subscriber-notifications-settings&tab=email-templates" class="nav-tab <?php echo $active_tab == 'email-templates' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Email Templates', 'subscriber-notifications'); ?>
        </a>
        <a href="?page=subscriber-notifications-settings&tab=scheduling" class="nav-tab <?php echo $active_tab == 'scheduling' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Scheduling', 'subscriber-notifications'); ?>
        </a>
        <a href="?page=subscriber-notifications-settings&tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Security', 'subscriber-notifications'); ?>
        </a>
        <a href="?page=subscriber-notifications-settings&tab=email-design" class="nav-tab <?php echo $active_tab == 'email-design' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Email Design', 'subscriber-notifications'); ?>
        </a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_settings', 'settings_nonce'); ?>
        
        <!-- General Tab -->
        <?php if ($active_tab == 'general'): ?>
            <?php settings_fields('subscriber_notifications_general'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mail_method"><?php _e('Mail Delivery Method', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_mail_method_field(); ?>
                    </td>
                </tr>
            </table>
            <table class="form-table" id="sendgrid-settings">
                <tr>
                    <th scope="row">
                        <label for="sendgrid_api_key"><?php _e('SendGrid API Key', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_sendgrid_api_key_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sendgrid_from_email"><?php _e('From Email', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_sendgrid_from_email_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sendgrid_from_name"><?php _e('From Name', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_sendgrid_from_name_field(); ?>
                    </td>
                </tr>
            </table>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email"><?php _e('Test Email Address', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_test_email_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="delete_data_on_uninstall"><?php _e('Delete Data on Uninstall', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_delete_data_on_uninstall_field(); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <!-- Email Templates Tab -->
        <?php if ($active_tab == 'email-templates'): ?>
            <?php settings_fields('subscriber_notifications_email-templates'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="welcome_email_subject"><?php _e('Welcome Email Subject', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_welcome_email_subject_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="welcome_email_content"><?php _e('Welcome Email Content', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_welcome_email_content_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="welcome_back_email_subject"><?php _e('Welcome Back Email Subject', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_welcome_back_email_subject_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="welcome_back_email_content"><?php _e('Welcome Back Email Content', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_welcome_back_email_content_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="preferences_update_email_subject"><?php _e('Preferences Updated Email Subject', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_preferences_update_email_subject_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="preferences_update_email_content"><?php _e('Preferences Updated Email Content', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_preferences_update_email_content_field(); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <!-- Scheduling Tab -->
        <?php if ($active_tab == 'scheduling'): ?>
            <?php settings_fields('subscriber_notifications_scheduling'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="daily_send_time"><?php _e('Daily Email Time', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_daily_send_time_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="weekly_send_day"><?php _e('Weekly Email Day', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_weekly_send_day_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="weekly_send_time"><?php _e('Weekly Email Time', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_weekly_send_time_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="monthly_send_day"><?php _e('Monthly Email Day', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_monthly_send_day_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="monthly_send_time"><?php _e('Monthly Email Time', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_monthly_send_time_field(); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <!-- Security Tab -->
        <?php if ($active_tab == 'security'): ?>
            <?php settings_fields('subscriber_notifications_security'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="captcha_site_key"><?php _e('reCAPTCHA Site Key', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_captcha_site_key_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="captcha_secret_key"><?php _e('reCAPTCHA Secret Key', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_captcha_secret_key_field(); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <!-- Email Design Tab -->
        <?php if ($active_tab == 'email-design'): ?>
            <?php settings_fields('subscriber_notifications_email-design'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="global_header_logo"><?php _e('Global Header Logo', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_global_header_logo_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="global_header_content"><?php _e('Global Header Content', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_global_header_content_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="global_footer"><?php _e('Global Footer Content', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_global_footer_field(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_css"><?php _e('Custom Email CSS', 'subscriber-notifications'); ?></label>
                    </th>
                    <td>
                        <?php $admin->render_email_css_field(); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('Save Settings', 'subscriber-notifications'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle mail method change
    $('#mail_method').on('change', function() {
        var method = $(this).val();
        var $status = $('#current-method-status');
        var $sendgridSettings = $('#sendgrid-settings');
        
        if (method === 'wp_mail') {
            $status.text('<?php _e('Using WordPress Default Mail', 'subscriber-notifications'); ?>');
            $sendgridSettings.hide();
        } else {
            $status.text('<?php _e('Using SendGrid', 'subscriber-notifications'); ?>');
            $sendgridSettings.show();
        }
    });
    
    // Test SendGrid connection
    $('#test-sendgrid').on('click', function() {
        var $button = $(this);
        var $result = $('#sendgrid-test-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_sendgrid_connection',
                nonce: '<?php echo wp_create_nonce('test_sendgrid'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error inline"><p>Connection test failed.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });
    
    // Test WordPress mail
    $('#test-wp-mail').on('click', function() {
        var $button = $(this);
        var $result = $('#wp-mail-test-result');
        var testEmail = $('#test_email').val();
        
        if (!testEmail) {
            $result.html('<div class="notice notice-error inline"><p>Please enter a test email address.</p></div>');
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_wp_mail',
                test_email: testEmail,
                nonce: '<?php echo wp_create_nonce('test_wp_mail'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error inline"><p>WordPress mail test failed.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test WordPress Mail');
            }
        });
    });
    
    // Initialize on page load
    $('#mail_method').trigger('change');
    
    // Media uploader for header logo
    var mediaUploader;
    
    $('.upload-logo').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '<?php _e('Select Header Logo', 'subscriber-notifications'); ?>',
            button: {
                text: '<?php _e('Use This Logo', 'subscriber-notifications'); ?>'
            },
            multiple: false,
            library: {
                type: 'image',
                uploadedTo: null
            },
            filterable: 'uploaded'
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Validate file type on client side
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (allowedTypes.indexOf(attachment.mime) === -1) {
                alert('<?php _e('Please select a valid image file (JPG, PNG, or GIF only). SVG files are not supported for email headers.', 'subscriber-notifications'); ?>');
                return;
            }
            
            // Validate file size (200KB limit)
            if (attachment.filesizeInBytes && attachment.filesizeInBytes > 200 * 1024) {
                alert('<?php _e('Image file size must be 200KB or smaller. Please choose a smaller image.', 'subscriber-notifications'); ?>');
                return;
            }
            
            $('#global_header_logo').val(attachment.id);
            $('.logo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd;" /><br><button type="button" class="button remove-logo" style="margin-top: 5px;"><?php _e('Remove Logo', 'subscriber-notifications'); ?></button>');
        });
        
        mediaUploader.open();
    });
    
    // Remove logo
    $(document).on('click', '.remove-logo', function(e) {
        e.preventDefault();
        $('#global_header_logo').val('');
        $('.logo-preview').html('<div class="no-logo" style="width: 200px; height: 100px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #666;"><?php _e('No logo selected', 'subscriber-notifications'); ?></div>');
    });
    
    // Scroll to anchor on page load if hash is present
    if (window.location.hash) {
        setTimeout(function() {
            var target = $(window.location.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 50
                }, 500);
            }
        }, 100);
    }
});
</script>

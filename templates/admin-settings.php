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
        <a href="?page=subscriber-notifications-settings&tab=shortcodes" class="nav-tab <?php echo $active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Shortcodes', 'subscriber-notifications'); ?>
        </a>
    </h2>
    
    <?php
    $settings_tabs = array(
        'general' => array(
            'page'  => 'subscriber-notifications-settings-general',
            'group' => 'subscriber_notifications_general',
        ),
        'email-templates' => array(
            'page'  => 'subscriber-notifications-settings-email-templates',
            'group' => 'subscriber_notifications_email-templates',
        ),
        'scheduling' => array(
            'page'  => 'subscriber-notifications-settings-scheduling',
            'group' => 'subscriber_notifications_scheduling',
        ),
        'security' => array(
            'page'  => 'subscriber-notifications-settings-security',
            'group' => 'subscriber_notifications_security',
        ),
        'email-design' => array(
            'page'  => 'subscriber-notifications-settings-email-design',
            'group' => 'subscriber_notifications_email-design',
        ),
    );
    ?>

    <?php if (isset($settings_tabs[$active_tab])) : ?>
        <?php settings_errors(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
            <?php settings_fields($settings_tabs[$active_tab]['group']); ?>
            <?php do_settings_sections($settings_tabs[$active_tab]['page']); ?>
            <?php submit_button(); ?>
        </form>
    <?php elseif ($active_tab === 'shortcodes') : ?>
        <?php include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/admin-settings-shortcodes.php'; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Test WordPress mail (wp_mail via plugin mail helper)
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
                $button.prop('disabled', false).text('<?php echo esc_js(__('Send Test Email', 'subscriber-notifications')); ?>');
            }
        });
    });
    
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

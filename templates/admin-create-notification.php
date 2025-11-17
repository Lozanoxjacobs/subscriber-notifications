<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Create Notification', 'subscriber-notifications'); ?></h1>
    
    <form method="post" action="" class="notification-form">
        <?php wp_nonce_field('create_notification', 'notification_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="notification_title"><?php _e('Notification Title', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="text" id="notification_title" name="notification_title" class="regular-text" required>
                    <p class="description"><?php _e('Internal title for this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_subject"><?php _e('Email Subject', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="text" id="notification_subject" name="notification_subject" class="regular-text" required>
                    <p class="description">
                        <?php _e('Email subject line. You can use shortcodes like [subscriber_name], [selected_news_categories], etc.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_content"><?php _e('Email Content', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor('', 'notification_content', array(
                        'textarea_name' => 'notification_content',
                        'media_buttons' => false,
                        'textarea_rows' => 15,
                        'teeny' => false
                    ));
                    ?>
                    <p class="description">
                        <?php _e('Available shortcodes:', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_email]</code> - <?php _e('Subscriber\'s email', 'subscriber-notifications'); ?><br>
                        <code>[selected_news_categories]</code> - <?php _e('Selected news categories', 'subscriber-notifications'); ?><br>
                        <code>[selected_meeting_categories]</code> - <?php _e('Selected meeting categories', 'subscriber-notifications'); ?><br>
                        <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
                        <code>[news_feed duration="1day|1week|1month"]</code> - <?php _e('Personalized news feed (shows only subscriber\'s selected categories)', 'subscriber-notifications'); ?><br>
                        <code>[meetings_feed duration="1day|1week|1month"]</code> - <?php _e('Personalized events feed (shows only subscriber\'s selected categories)', 'subscriber-notifications'); ?><br>
                        <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="news_categories"><?php _e('Target News Categories', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php if (!empty($news_categories)): ?>
                        <label class="select-all-label" style="display: block; margin-bottom: 10px; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                            <input type="checkbox" id="select-all-news-admin" class="select-all-checkbox" data-target="news_categories">
                            <strong><?php _e('Select All News Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes" style="margin-left: 20px; border-left: 2px solid #e0e0e0; padding-left: 15px;">
                            <?php foreach ($news_categories as $category): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="news_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="news-category-checkbox">
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No news categories found.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="meeting_categories"><?php _e('Target Meeting Categories', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php if (!empty($meeting_categories)): ?>
                        <label class="select-all-label" style="display: block; margin-bottom: 10px; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                            <input type="checkbox" id="select-all-meetings-admin" class="select-all-checkbox" data-target="meeting_categories">
                            <strong><?php _e('Select All Meeting Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes" style="margin-left: 20px; border-left: 2px solid #e0e0e0; padding-left: 15px;">
                            <?php foreach ($meeting_categories as $category): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="meeting_categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="meeting-category-checkbox">
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No meeting categories found. Make sure The Events Calendar is active and you have a "meetings" category with child categories.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="frequency_target"><?php _e('Target Frequency', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <select name="frequency_target" id="frequency_target">
                        <option value=""><?php _e('All Frequencies', 'subscriber-notifications'); ?></option>
                        <option value="daily"><?php _e('Daily', 'subscriber-notifications'); ?></option>
                        <option value="weekly"><?php _e('Weekly', 'subscriber-notifications'); ?></option>
                        <option value="monthly"><?php _e('Monthly', 'subscriber-notifications'); ?></option>
                    </select>
                    <p class="description"><?php _e('Target subscribers with specific frequency preferences.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="is_recurring"><?php _e('Recurring Notification', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_recurring" id="is_recurring" value="1">
                        <?php _e('Make this notification recurring', 'subscriber-notifications'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Check this box to send this notification repeatedly based on the target frequency schedule. Unchecked notifications are sent only once.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <div class="notification-actions">
            <input type="submit" name="create_notification" class="button button-primary" value="<?php _e('Create Notification', 'subscriber-notifications'); ?>">
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications'); ?>" class="button"><?php _e('Cancel', 'subscriber-notifications'); ?></a>
        </div>
    </form>
    
    <div class="notification-preview-email" style="margin-top: 30px;">
        <h3><?php _e('Send Preview Email', 'subscriber-notifications'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="preview_email"><?php _e('Preview Email Address', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="email" id="preview_email" name="preview_email" class="regular-text" placeholder="test@example.com">
                    <button type="button" id="send-preview-email" class="button button-secondary">
                        <?php _e('Send Preview Email', 'subscriber-notifications'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Send a test email to see how the notification will look. This email will only be sent to the address you specify.', 'subscriber-notifications'); ?>
                    </p>
                    <div id="preview-email-result" style="margin-top: 10px;"></div>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Send preview email functionality
    $('#send-preview-email').on('click', function(e) {
        e.preventDefault();
        
        var email = $('#preview_email').val();
        var subject = $('#notification_subject').val();
        var content = '';
        
        // Get content from TinyMCE editor if it exists, otherwise from textarea
        if (typeof tinymce !== 'undefined' && tinymce.get('notification_content')) {
            content = tinymce.get('notification_content').getContent();
        } else {
            content = $('#notification_content').val();
        }
        
        if (!email) {
            alert('<?php _e('Please enter an email address.', 'subscriber-notifications'); ?>');
            return;
        }
        
        if (!subject) {
            alert('<?php _e('Please enter a subject.', 'subscriber-notifications'); ?>');
            return;
        }
        
        if (!content) {
            alert('<?php _e('Please enter content.', 'subscriber-notifications'); ?>');
            return;
        }
        
        var button = $(this);
        var resultDiv = $('#preview-email-result');
        
        button.prop('disabled', true).text('<?php _e('Sending...', 'subscriber-notifications'); ?>');
        resultDiv.html('<p style="color: #666;"><?php _e('Sending preview email...', 'subscriber-notifications'); ?></p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_preview_email',
                nonce: '<?php echo wp_create_nonce('send_preview_email'); ?>',
                email: email,
                subject: subject,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p style="color: #46b450;"><?php _e('Preview email sent successfully!', 'subscriber-notifications'); ?></p>');
                } else {
                    resultDiv.html('<p style="color: #dc3232;"><?php _e('Failed to send preview email: ', 'subscriber-notifications'); ?>' + response.data + '</p>');
                }
            },
            error: function() {
                resultDiv.html('<p style="color: #dc3232;"><?php _e('Failed to send preview email due to an error.', 'subscriber-notifications'); ?></p>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Send Preview Email', 'subscriber-notifications'); ?>');
            }
        });
    });
});
</script>

<style>
.notification-form .form-table th {
    width: 200px;
}

.notification-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.notification-actions .button {
    margin-right: 10px;
}

.notification-preview h3 {
    margin-bottom: 10px;
}

#preview-content {
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}
</style>

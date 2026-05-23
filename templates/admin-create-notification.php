<?php
if (!defined('ABSPATH')) {
    exit;
}

$notification_form = isset($notification_form) ? $notification_form : array(
    'title'            => '',
    'subject'          => '',
    'content'          => '',
    'frequency_target' => '',
    'is_recurring'     => 0,
    'selected_targets' => array(),
);
$selected_targets = isset($selected_targets) ? $selected_targets : $notification_form['selected_targets'];
?>

<div class="wrap">
    <h1><?php _e('Create Notification', 'subscriber-notifications'); ?></h1>

    <?php settings_errors('subscriber_notifications'); ?>
    
    <form method="post" action="" id="create-notification-form" class="notification-form">
        <?php wp_nonce_field('create_notification', 'notification_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="notification_title"><?php _e('Notification Title', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="notification_title" name="notification_title" class="regular-text" value="<?php echo esc_attr($notification_form['title']); ?>" required>
                    <p class="description"><?php _e('Internal title for this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_subject"><?php _e('Email Subject', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="notification_subject" name="notification_subject" class="regular-text" value="<?php echo esc_attr($notification_form['subject']); ?>" required>
                    <p class="description"><?php _e('Email subject line. Shortcodes are supported.', 'subscriber-notifications'); ?></p>
                    <?php SubscriberNotifications_Admin::render_shortcode_reference_description(); ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_content"><?php _e('Email Content', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $notification_form['content'],
                        'notification_content',
                        array(
                            'textarea_name' => 'notification_content',
                            'media_buttons' => false,
                            'textarea_rows' => 15,
                            'teeny'         => false,
                        )
                    );
                    ?>
                    <?php SubscriberNotifications_Admin::render_shortcode_reference_description(); ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Target Content', 'subscriber-notifications'); ?> <span class="required">*</span>
                </th>
                <td>
                    <?php
                    if (empty($is_configured)) {
                        echo '<p>' . esc_html__('Content Types are not configured. Set them up to define notification targets.', 'subscriber-notifications') . '</p>';
                    } else {
                        require SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/admin-notification-targets.php';
                    }
                    ?>
                </td>
            </tr>

            
            <tr>
                <th scope="row">
                    <label for="frequency_target"><?php _e('Target Frequency', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="frequency_target" id="frequency_target" required>
                        <option value=""><?php esc_html_e('Select frequency', 'subscriber-notifications'); ?></option>
                        <option value="daily" <?php selected($notification_form['frequency_target'], 'daily'); ?>><?php esc_html_e('Daily', 'subscriber-notifications'); ?></option>
                        <option value="weekly" <?php selected($notification_form['frequency_target'], 'weekly'); ?>><?php esc_html_e('Weekly', 'subscriber-notifications'); ?></option>
                        <option value="monthly" <?php selected($notification_form['frequency_target'], 'monthly'); ?>><?php esc_html_e('Monthly', 'subscriber-notifications'); ?></option>
                    </select>
                    <p class="description"><?php _e('Only subscribers with this frequency preference will receive this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="is_recurring"><?php _e('Recurring Notification', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_recurring" id="is_recurring" value="1" <?php checked(!empty($notification_form['is_recurring'])); ?>>
                        <?php _e('Make this notification recurring', 'subscriber-notifications'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Check this box to send this notification repeatedly based on the target frequency schedule. Unchecked notifications are sent only once.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <div class="notification-actions">
            <input type="submit" name="create_notification" class="button button-primary" value="<?php esc_attr_e('Create Notification', 'subscriber-notifications'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications')); ?>" class="button"><?php esc_html_e('Cancel', 'subscriber-notifications'); ?></a>
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
                    <div id="preview-email-result"></div>
                </td>
            </tr>
        </table>
    </div>
</div>


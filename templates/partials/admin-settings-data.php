<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var SubscriberNotifications_Admin $admin */

$data_settings_page = 'subscriber-notifications-settings-data';
?>

<div class="sn-settings-data">
    <?php settings_errors(); ?>

    <?php
    $admin->render_settings_section($data_settings_page, 'subscriber_notifications_data_logs_section');
    ?>

    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="sn-settings-data-form">
        <?php settings_fields('subscriber_notifications_data'); ?>
        <?php $admin->render_settings_section($data_settings_page, 'subscriber_notifications_data_uninstall_section'); ?>
        <?php submit_button(); ?>
    </form>
</div>

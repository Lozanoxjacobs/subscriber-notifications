<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Subscriber Notifications Settings', 'subscriber-notifications'); ?></h1>
    <hr class="wp-header-end">

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
        <a href="?page=subscriber-notifications-settings&tab=data" class="nav-tab <?php echo $active_tab == 'data' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Data', 'subscriber-notifications'); ?>
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
        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="sn-settings-form">
            <?php settings_fields($settings_tabs[$active_tab]['group']); ?>
            <?php do_settings_sections($settings_tabs[$active_tab]['page']); ?>
            <?php submit_button(); ?>
        </form>
    <?php elseif ($active_tab === 'shortcodes') : ?>
        <?php include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/admin-settings-shortcodes.php'; ?>
    <?php elseif ($active_tab === 'data') : ?>
        <?php include SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/admin-settings-data.php'; ?>
    <?php endif; ?>
</div>


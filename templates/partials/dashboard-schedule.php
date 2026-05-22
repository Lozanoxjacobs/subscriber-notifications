<?php
if (!defined('ABSPATH')) {
    exit;
}

$schedule = $snapshot['schedule'] ?? array();
$urls     = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-schedule" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Email schedule', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-schedule-list">
            <li>
                <strong><?php esc_html_e('Daily:', 'subscriber-notifications'); ?></strong>
                <?php echo esc_html($schedule['daily'] ?? ''); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Weekly:', 'subscriber-notifications'); ?></strong>
                <?php echo esc_html($schedule['weekly'] ?? ''); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Monthly:', 'subscriber-notifications'); ?></strong>
                <?php echo esc_html($schedule['monthly'] ?? ''); ?>
            </li>
        </ul>
        <p>
            <a href="<?php echo esc_url($urls['settings_scheduling'] ?? '#'); ?>" class="button">
                <?php esc_html_e('Change schedule', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>

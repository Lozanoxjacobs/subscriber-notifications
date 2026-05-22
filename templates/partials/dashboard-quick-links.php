<?php
if (!defined('ABSPATH')) {
    exit;
}

$urls = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-quick-links" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Quick links', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-quick-links">
            <li><a href="<?php echo esc_url($urls['import_export'] ?? '#'); ?>"><?php esc_html_e('Import / Export', 'subscriber-notifications'); ?></a></li>
            <li><a href="<?php echo esc_url($urls['settings_design'] ?? '#'); ?>"><?php esc_html_e('Email Design', 'subscriber-notifications'); ?></a></li>
            <li><a href="<?php echo esc_url($urls['settings_shortcodes'] ?? '#'); ?>"><?php esc_html_e('Shortcodes reference', 'subscriber-notifications'); ?></a></li>
        </ul>
    </div>
</div>

<?php
if (!defined('ABSPATH')) {
    exit;
}

$health = $snapshot['health'] ?? array();
$items  = $health['items'] ?? array();
$cron   = $snapshot['cron'] ?? array();
$urls   = $snapshot['urls'] ?? array();
$hide_when_ok = !empty($health['all_required_ok']);
?>

<div id="sn-dashboard-health" class="postbox<?php echo $hide_when_ok ? ' sn-postbox-collapsed-ok' : ''; ?>">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Setup & health', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-health-checklist">
            <?php foreach ($items as $item) : ?>
                <?php
                $ok   = !empty($item['ok']);
                $soft = !empty($item['soft']);
                $icon = $ok ? 'yes-alt' : ($soft ? 'warning' : 'dismiss');
                $class = $ok ? 'sn-health-ok' : ($soft ? 'sn-health-soft' : 'sn-health-warn');
                ?>
                <li class="<?php echo esc_attr($class); ?>">
                    <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                    <span class="sn-health-label">
                        <?php if (!empty($item['url'])) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($item['label']); ?>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($item['message'])) : ?>
                        <span class="sn-health-message description"><?php echo esc_html($item['message']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!empty($snapshot['wp_cron_disabled'])) : ?>
            <p class="description sn-cron-note">
                <?php esc_html_e('DISABLE_WP_CRON is enabled. Use a system cron job to hit wp-cron.php on a one-minute interval for reliable delivery.', 'subscriber-notifications'); ?>
                <a href="<?php echo esc_url($urls['settings_scheduling'] ?? '#'); ?>"><?php esc_html_e('Scheduling settings', 'subscriber-notifications'); ?></a>
            </p>
        <?php elseif (empty($cron['all_ok'])) : ?>
            <details class="sn-cron-details">
                <summary><?php esc_html_e('Cron hook details', 'subscriber-notifications'); ?></summary>
                <table class="widefat striped sn-cron-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Hook', 'subscriber-notifications'); ?></th>
                            <th><?php esc_html_e('Scheduled', 'subscriber-notifications'); ?></th>
                            <th><?php esc_html_e('Interval', 'subscriber-notifications'); ?></th>
                            <th><?php esc_html_e('Next run', 'subscriber-notifications'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($cron['hooks'] ?? array()) as $hook_name => $info) : ?>
                            <tr>
                                <td><code><?php echo esc_html($hook_name); ?></code></td>
                                <td><?php echo !empty($info['scheduled']) ? esc_html__('Yes', 'subscriber-notifications') : esc_html__('No', 'subscriber-notifications'); ?></td>
                                <td><?php echo esc_html($info['schedule'] ?: '—'); ?></td>
                                <td><?php echo esc_html($info['next_run'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </div>
</div>

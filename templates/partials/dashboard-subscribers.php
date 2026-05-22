<?php
if (!defined('ABSPATH')) {
    exit;
}

$subs = $snapshot['subscribers'] ?? array();
$urls = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-subscribers" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Subscribers', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-stat-list">
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($subs['active'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Active', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($subs['inactive'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Inactive', 'subscriber-notifications'); ?></span>
            </li>
        </ul>
        <p class="description">
            <?php
            printf(
                /* translators: 1: daily count, 2: weekly count, 3: monthly count */
                esc_html__('By frequency (active): Daily %1$s · Weekly %2$s · Monthly %3$s', 'subscriber-notifications'),
                esc_html(number_format_i18n((int) ($subs['daily'] ?? 0))),
                esc_html(number_format_i18n((int) ($subs['weekly'] ?? 0))),
                esc_html(number_format_i18n((int) ($subs['monthly'] ?? 0)))
            );
            ?>
        </p>
        <p class="description">
            <?php
            printf(
                /* translators: 1: WP-linked count, 2: never notified count */
                esc_html__('WP accounts linked: %1$s · Never notified: %2$s', 'subscriber-notifications'),
                esc_html(number_format_i18n((int) ($subs['linked_wp_user'] ?? 0))),
                esc_html(number_format_i18n((int) ($subs['never_notified'] ?? 0)))
            );
            ?>
        </p>
        <p>
            <a href="<?php echo esc_url($urls['subscribers'] ?? '#'); ?>" class="button">
                <?php esc_html_e('Manage subscribers', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>

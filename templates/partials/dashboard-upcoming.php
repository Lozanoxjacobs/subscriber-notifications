<?php
if (!defined('ABSPATH')) {
    exit;
}

$upcoming = $snapshot['upcoming'] ?? array();
$urls     = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-upcoming" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Upcoming sends', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <?php if (empty($upcoming)) : ?>
            <p class="description"><?php esc_html_e('No pending notifications with a scheduled send time.', 'subscriber-notifications'); ?></p>
        <?php else : ?>
            <table class="widefat striped sn-dashboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Frequency', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Type', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Next send', 'subscriber-notifications'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $row) : ?>
                        <?php
                        $edit_url = admin_url('admin.php?page=subscriber-notifications-edit&id=' . (int) $row->id);
                        $next_label = '—';
                        if (!empty($row->next_send_date)) {
                            try {
                                $dt = new DateTimeImmutable($row->next_send_date, wp_timezone());
                                $next_label = wp_date('M j, Y g:i A', $dt->getTimestamp());
                            } catch (Exception $e) {
                                $next_label = esc_html($row->next_send_date);
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($row->title); ?></a>
                            </td>
                            <td><?php echo esc_html($row->frequency_target ? ucfirst($row->frequency_target) : '—'); ?></td>
                            <td>
                                <?php echo (int) $row->is_recurring === 1
                                    ? esc_html__('Recurring', 'subscriber-notifications')
                                    : esc_html__('One-time', 'subscriber-notifications'); ?>
                            </td>
                            <td><?php echo esc_html($next_label); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p>
            <a href="<?php echo esc_url($urls['notifications_pending'] ?? '#'); ?>" class="button">
                <?php esc_html_e('View pending notifications', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>

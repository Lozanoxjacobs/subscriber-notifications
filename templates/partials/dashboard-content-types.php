<?php
if (!defined('ABSPATH')) {
    exit;
}

$ct   = $snapshot['content_types'] ?? array();
$urls = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-content-types" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Content types', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <?php if (empty($ct['configured'])) : ?>
            <p><?php esc_html_e('Content Types are not configured yet. Set up post types and taxonomies before publishing the subscription form.', 'subscriber-notifications'); ?></p>
            <p>
                <a href="<?php echo esc_url($urls['content_types'] ?? '#'); ?>" class="button">
                    <?php esc_html_e('Set up Content Types', 'subscriber-notifications'); ?>
                </a>
            </p>
        <?php else : ?>
            <ul class="sn-content-types-summary">
                <?php foreach (($ct['lines'] ?? array()) as $line) : ?>
                    <li><?php echo esc_html($line); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>
                <a href="<?php echo esc_url($urls['content_types'] ?? '#'); ?>" class="button">
                    <?php esc_html_e('Manage Content Types', 'subscriber-notifications'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>

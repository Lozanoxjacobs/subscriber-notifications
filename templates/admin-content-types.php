<?php
/**
 * Content Types admin page template.
 *
 * Variables available:
 *
 * @var array $config              Stored content config.
 * @var array $available_post_types Map of slug => WP_Post_Type for public post types (excludes attachment).
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$option_key   = SubscriberNotifications_Content_Config::OPTION_KEY;
$option_group = SubscriberNotifications_Content_Config::OPTION_GROUP;
$term_display_modes = array(
    'all'         => __('Show all terms', 'subscriber-notifications'),
    'children_of' => __('Show only children of a parent term (hierarchical taxonomies)', 'subscriber-notifications'),
    'include'     => __('Show only selected term IDs', 'subscriber-notifications'),
    'exclude'     => __('Show all terms except the selected IDs', 'subscriber-notifications'),
);
?>
<div class="wrap subscriber-notifications-content-types">
    <h1 class="wp-heading-inline"><?php esc_html_e('Content Types', 'subscriber-notifications'); ?></h1>
    <hr class="wp-header-end">
    <p class="description">
        <?php esc_html_e('Choose which public post types and taxonomies appear on the subscription form. Subscribers will only see the post types and taxonomies you enable here.', 'subscriber-notifications'); ?>
    </p>

    <?php settings_errors(); ?>

    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" id="sn-content-types-form">
        <?php settings_fields($option_group); ?>

        <?php if (empty($available_post_types)) : ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No public post types are registered on this site. Register at least one public post type before configuring Content Types.', 'subscriber-notifications'); ?></p>
            </div>
        <?php else : ?>

            <div class="metabox-holder">
                <?php foreach ($available_post_types as $post_type_slug => $post_type_object) :
                    $pt_config = isset($config[$post_type_slug]) ? $config[$post_type_slug] : array();
                    $pt_enabled = !empty($pt_config['enabled']);
                    $pt_label = isset($pt_config['label']) ? (string) $pt_config['label'] : '';
                    $name_prefix = $option_key . '[' . $post_type_slug . ']';
                    $available_taxonomies = SubscriberNotifications_Content_Config::get_available_taxonomies($post_type_slug);
                    $box_id = 'sn-pt-' . sanitize_html_class($post_type_slug);
                ?>
                <div class="postbox" id="<?php echo esc_attr($box_id); ?>">
                    <button type="button" class="handlediv" aria-expanded="true">
                        <span class="screen-reader-text"><?php
                            /* translators: %s: post type label */
                            echo esc_html(sprintf(__('Toggle panel: %s', 'subscriber-notifications'), $post_type_object->labels->name));
                        ?></span>
                        <span class="toggle-indicator" aria-hidden="true"></span>
                    </button>
                    <h2 class="hndle"><span><?php echo esc_html($post_type_object->labels->name); ?> <code><?php echo esc_html($post_type_slug); ?></code></span></h2>
                    <div class="inside">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($box_id . '-enabled'); ?>">
                                        <?php esc_html_e('Enable on subscription form', 'subscriber-notifications'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="<?php echo esc_attr($box_id . '-enabled'); ?>"
                                            name="<?php echo esc_attr($name_prefix . '[enabled]'); ?>"
                                            value="1"
                                            <?php checked($pt_enabled); ?> />
                                        <?php
                                        /* translators: %s: post type label */
                                        printf(esc_html__('Offer subscriptions for %s.', 'subscriber-notifications'), esc_html($post_type_object->labels->name));
                                        ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($box_id . '-label'); ?>">
                                        <?php esc_html_e('Display label (optional)', 'subscriber-notifications'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text"
                                        class="regular-text"
                                        id="<?php echo esc_attr($box_id . '-label'); ?>"
                                        name="<?php echo esc_attr($name_prefix . '[label]'); ?>"
                                        value="<?php echo esc_attr($pt_label); ?>"
                                        placeholder="<?php echo esc_attr($post_type_object->labels->name); ?>" />
                                </td>
                            </tr>
                        </table>

                        <?php if (empty($available_taxonomies)) : ?>
                            <p class="description"><?php esc_html_e('No public taxonomies are registered for this post type.', 'subscriber-notifications'); ?></p>
                        <?php else : ?>
                            <h3><?php esc_html_e('Taxonomies', 'subscriber-notifications'); ?></h3>
                            <?php foreach ($available_taxonomies as $tax_slug => $tax_object) :
                                $tax_config = isset($pt_config['taxonomies'][$tax_slug]) ? $pt_config['taxonomies'][$tax_slug] : array();
                                $tax_enabled = !empty($tax_config['enabled_on_form']);
                                $tax_label = isset($tax_config['label']) ? (string) $tax_config['label'] : '';
                                $term_display = isset($tax_config['term_display']) ? (string) $tax_config['term_display'] : 'all';
                                $parent_term_id = isset($tax_config['parent_term_id']) ? (int) $tax_config['parent_term_id'] : 0;
                                $include_ids = isset($tax_config['include_term_ids']) ? (array) $tax_config['include_term_ids'] : array();
                                $exclude_ids = isset($tax_config['exclude_term_ids']) ? (array) $tax_config['exclude_term_ids'] : array();
                                $tax_name_prefix = $name_prefix . '[taxonomies][' . $tax_slug . ']';
                                $tax_id = $box_id . '-tax-' . sanitize_html_class($tax_slug);
                            ?>
                            <fieldset class="sn-taxonomy-block" data-hierarchical="<?php echo !empty($tax_object->hierarchical) ? '1' : '0'; ?>">
                                <legend>
                                    <strong><?php echo esc_html($tax_object->labels->name); ?></strong>
                                    <code><?php echo esc_html($tax_slug); ?></code>
                                </legend>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($tax_id . '-enabled'); ?>">
                                                <?php esc_html_e('Show on form', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <label>
                                                <input type="checkbox"
                                                    id="<?php echo esc_attr($tax_id . '-enabled'); ?>"
                                                    name="<?php echo esc_attr($tax_name_prefix . '[enabled_on_form]'); ?>"
                                                    value="1"
                                                    <?php checked($tax_enabled); ?> />
                                                <?php esc_html_e('Let subscribers pick terms from this taxonomy.', 'subscriber-notifications'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($tax_id . '-label'); ?>">
                                                <?php esc_html_e('Display label (optional)', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                class="regular-text"
                                                id="<?php echo esc_attr($tax_id . '-label'); ?>"
                                                name="<?php echo esc_attr($tax_name_prefix . '[label]'); ?>"
                                                value="<?php echo esc_attr($tax_label); ?>"
                                                placeholder="<?php echo esc_attr($tax_object->labels->name); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($tax_id . '-display'); ?>">
                                                <?php esc_html_e('Which terms to show', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <select id="<?php echo esc_attr($tax_id . '-display'); ?>"
                                                class="sn-term-display"
                                                name="<?php echo esc_attr($tax_name_prefix . '[term_display]'); ?>">
                                                <?php foreach ($term_display_modes as $mode_key => $mode_label) :
                                                    if ($mode_key === 'children_of' && empty($tax_object->hierarchical)) {
                                                        continue;
                                                    }
                                                ?>
                                                    <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($term_display, $mode_key); ?>>
                                                        <?php echo esc_html($mode_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php if (!empty($tax_object->hierarchical)) : ?>
                                    <tr class="sn-mode-row sn-mode-row-children_of" data-mode="children_of">
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($tax_id . '-parent'); ?>">
                                                <?php esc_html_e('Parent term', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <?php
                                            wp_dropdown_categories(array(
                                                'taxonomy'         => $tax_slug,
                                                'hide_empty'       => false,
                                                'name'             => $tax_name_prefix . '[parent_term_id]',
                                                'id'               => $tax_id . '-parent',
                                                'selected'         => $parent_term_id,
                                                'show_option_none' => __('— Select a parent term —', 'subscriber-notifications'),
                                                'option_none_value' => 0,
                                                'orderby'          => 'name',
                                                'order'            => 'ASC',
                                                'hierarchical'     => true,
                                            ));
                                            ?>
                                            <p class="description"><?php esc_html_e('Only children of this term will appear on the subscription form.', 'subscriber-notifications'); ?></p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="sn-mode-row sn-mode-row-include" data-mode="include">
                                        <th scope="row">
                                            <label><?php esc_html_e('Include these terms', 'subscriber-notifications'); ?></label>
                                        </th>
                                        <td>
                                            <?php $this_render_term_checklist = static function ($post_type_slug, $tax_slug, $tax_object, $field_name, $selected_ids) {
                                                $terms = get_terms(array(
                                                    'taxonomy'   => $tax_slug,
                                                    'hide_empty' => false,
                                                    'orderby'    => 'name',
                                                    'order'      => 'ASC',
                                                ));
                                                if (is_wp_error($terms) || empty($terms)) {
                                                    echo '<p class="description">' . esc_html__('No terms found.', 'subscriber-notifications') . '</p>';
                                                    return;
                                                }
                                                echo '<ul class="sn-term-checklist" style="max-height:180px;overflow:auto;border:1px solid #ddd;padding:6px 10px;background:#fff;">';
                                                foreach ($terms as $term) {
                                                    $checked = in_array((int) $term->term_id, array_map('intval', (array) $selected_ids), true);
                                                    printf(
                                                        '<li><label><input type="checkbox" name="%1$s" value="%2$d" %3$s /> %4$s</label></li>',
                                                        esc_attr($field_name . '[]'),
                                                        (int) $term->term_id,
                                                        $checked ? 'checked="checked"' : '',
                                                        esc_html($term->name)
                                                    );
                                                }
                                                echo '</ul>';
                                            };
                                            $this_render_term_checklist($post_type_slug, $tax_slug, $tax_object, $tax_name_prefix . '[include_term_ids]', $include_ids);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr class="sn-mode-row sn-mode-row-exclude" data-mode="exclude">
                                        <th scope="row">
                                            <label><?php esc_html_e('Exclude these terms', 'subscriber-notifications'); ?></label>
                                        </th>
                                        <td>
                                            <?php $this_render_term_checklist($post_type_slug, $tax_slug, $tax_object, $tax_name_prefix . '[exclude_term_ids]', $exclude_ids); ?>
                                        </td>
                                    </tr>
                                </table>
                            </fieldset>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <?php submit_button(__('Save Content Types', 'subscriber-notifications')); ?>
    </form>
</div>

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
        <?php esc_html_e('Configure how each post type works with Subscriber Notifications: the public subscription form, per-post on-page subscribe widgets, and the taxonomies that scope both.', 'subscriber-notifications'); ?>
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
                    $pt_single_item = !empty($pt_config['allow_single_item_subscriptions']);
                    $pt_label = isset($pt_config['label']) ? (string) $pt_config['label'] : '';
                    $single_item_include_ids = isset($pt_config['single_item_include_post_ids']) ? (array) $pt_config['single_item_include_post_ids'] : array();
                    $single_item_exclude_ids = isset($pt_config['single_item_exclude_post_ids']) ? (array) $pt_config['single_item_exclude_post_ids'] : array();
                    $single_item_visibility_mode = SubscriberNotifications_Content_Config::get_single_item_visibility_mode($post_type_slug);
                    $single_item_rules_mode = ($single_item_visibility_mode === SubscriberNotifications_Content_Config::SINGLE_ITEM_VISIBILITY_RULES);
                    $rest_base = !empty($post_type_object->rest_base) ? (string) $post_type_object->rest_base : $post_type_slug;
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
                                    <label for="<?php echo esc_attr($box_id . '-single-item'); ?>">
                                        <?php esc_html_e('Allow on-page subscriptions', 'subscriber-notifications'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="<?php echo esc_attr($box_id . '-single-item'); ?>"
                                            name="<?php echo esc_attr($name_prefix . '[allow_single_item_subscriptions]'); ?>"
                                            value="1"
                                            <?php checked($pt_single_item); ?> />
                                        <?php esc_html_e('Let visitors subscribe to updates for a specific page via a shortcode on that page.', 'subscriber-notifications'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Independent of the subscription form. For example, you can disable Pages on the global form but still allow subscriptions on a Careers page.', 'subscriber-notifications'); ?>
                                    </p>
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
                            <div class="sn-taxonomies-section">
                            <h3><?php esc_html_e('Taxonomies & term rules', 'subscriber-notifications'); ?></h3>
                            <p class="description">
                                <?php esc_html_e('Term rules here affect two things: which terms subscribers can choose on the subscription form, and — when widget visibility is “By content rules” — which posts show the on-page subscribe widget.', 'subscriber-notifications'); ?>
                            </p>
                            <p class="description sn-taxonomies-pick-list-note" <?php echo $single_item_rules_mode ? 'style="display:none;"' : ''; ?>>
                                <?php esc_html_e('Not used for on-page widget eligibility while visibility is “Only specific posts I choose”.', 'subscriber-notifications'); ?>
                            </p>
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
                                                <?php esc_html_e('Offer on subscription form', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <label>
                                                <input type="checkbox"
                                                    id="<?php echo esc_attr($tax_id . '-enabled'); ?>"
                                                    name="<?php echo esc_attr($tax_name_prefix . '[enabled_on_form]'); ?>"
                                                    value="1"
                                                    <?php checked($tax_enabled); ?> />
                                                <?php esc_html_e('Subscribers can select terms from this taxonomy when signing up.', 'subscriber-notifications'); ?>
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
                                                <?php esc_html_e('Which terms apply', 'subscriber-notifications'); ?>
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
                                            <p class="description"><?php esc_html_e('Subscription form: limits terms subscribers can select. On-page widget: limits which posts qualify (combined with OR logic across taxonomies).', 'subscriber-notifications'); ?></p>
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
                            </div>
                        <?php endif; ?>

                        <div class="sn-single-item-eligibility" data-post-type="<?php echo esc_attr($post_type_slug); ?>" data-rest-base="<?php echo esc_attr($rest_base); ?>" <?php echo $pt_single_item ? '' : 'style="display:none;"'; ?>>
                            <h3><?php esc_html_e('On-page subscribe widget — where to show it', 'subscriber-notifications'); ?></h3>

                            <fieldset class="sn-eligibility-mode-fieldset">
                                <legend class="screen-reader-text"><?php esc_html_e('Widget visibility mode', 'subscriber-notifications'); ?></legend>
                                <p class="sn-eligibility-mode-label"><strong><?php esc_html_e('Widget visibility', 'subscriber-notifications'); ?></strong></p>
                                <ul class="sn-eligibility-mode-options">
                                    <li>
                                        <label>
                                            <input type="radio"
                                                class="sn-eligibility-mode-radio"
                                                name="<?php echo esc_attr($name_prefix . '[single_item_visibility_mode]'); ?>"
                                                value="<?php echo esc_attr(SubscriberNotifications_Content_Config::SINGLE_ITEM_VISIBILITY_RULES); ?>"
                                                <?php checked($single_item_rules_mode); ?> />
                                            <?php esc_html_e('By content rules', 'subscriber-notifications'); ?>
                                        </label>
                                        <span class="description"><?php esc_html_e('Match posts by taxonomy; add exceptions in “Except on these posts”.', 'subscriber-notifications'); ?></span>
                                    </li>
                                    <li>
                                        <label>
                                            <input type="radio"
                                                class="sn-eligibility-mode-radio"
                                                name="<?php echo esc_attr($name_prefix . '[single_item_visibility_mode]'); ?>"
                                                value="<?php echo esc_attr(SubscriberNotifications_Content_Config::SINGLE_ITEM_VISIBILITY_PICK_LIST); ?>"
                                                <?php checked(!$single_item_rules_mode); ?> />
                                            <?php esc_html_e('Only specific posts I choose', 'subscriber-notifications'); ?>
                                        </label>
                                        <span class="description"><?php esc_html_e('Ignore taxonomy rules; show the widget only on posts you pick.', 'subscriber-notifications'); ?></span>
                                    </li>
                                </ul>
                            </fieldset>

                            <div class="sn-eligibility-rules-mode" <?php echo $single_item_rules_mode ? '' : 'style="display:none;"'; ?>>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($box_id . '-exclude-post-search'); ?>">
                                                <?php esc_html_e('Except on these posts', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="sn-post-picker" data-list="exclude">
                                                <p>
                                                    <input type="search"
                                                        id="<?php echo esc_attr($box_id . '-exclude-post-search'); ?>"
                                                        class="sn-post-picker-search regular-text"
                                                        placeholder="<?php esc_attr_e('Search by title…', 'subscriber-notifications'); ?>"
                                                        autocomplete="off" />
                                                </p>
                                                <ul class="sn-post-picker-results" aria-live="polite"></ul>
                                                <ul class="sn-post-picker-selected">
                                                    <?php foreach ($single_item_exclude_ids as $picked_id) :
                                                        $picked_post = get_post((int) $picked_id);
                                                        if (!$picked_post) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <li data-post-id="<?php echo esc_attr((string) $picked_post->ID); ?>">
                                                            <span class="sn-post-picker-title"><?php echo esc_html(get_the_title($picked_post)); ?></span>
                                                            <button type="button" class="button-link sn-post-picker-remove" aria-label="<?php esc_attr_e('Remove', 'subscriber-notifications'); ?>">&times;</button>
                                                            <input type="hidden" name="<?php echo esc_attr($name_prefix . '[single_item_exclude_post_ids][]'); ?>" value="<?php echo esc_attr((string) $picked_post->ID); ?>" />
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p class="description">
                                                    <?php esc_html_e('Use this to carve out exceptions — e.g. hide the widget on one FAQ that is otherwise in an allowed category.', 'subscriber-notifications'); ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="sn-eligibility-pick-list-mode" <?php echo $single_item_rules_mode ? 'style="display:none;"' : ''; ?>>
                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($box_id . '-include-post-search'); ?>">
                                                <?php esc_html_e('Limit widget to these posts', 'subscriber-notifications'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div class="sn-post-picker" data-list="include">
                                                <p>
                                                    <input type="search"
                                                        id="<?php echo esc_attr($box_id . '-include-post-search'); ?>"
                                                        class="sn-post-picker-search regular-text"
                                                        placeholder="<?php esc_attr_e('Search by title…', 'subscriber-notifications'); ?>"
                                                        autocomplete="off" />
                                                </p>
                                                <ul class="sn-post-picker-results" aria-live="polite"></ul>
                                                <ul class="sn-post-picker-selected">
                                                    <?php foreach ($single_item_include_ids as $picked_id) :
                                                        $picked_post = get_post((int) $picked_id);
                                                        if (!$picked_post) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <li data-post-id="<?php echo esc_attr((string) $picked_post->ID); ?>">
                                                            <span class="sn-post-picker-title"><?php echo esc_html(get_the_title($picked_post)); ?></span>
                                                            <button type="button" class="button-link sn-post-picker-remove" aria-label="<?php esc_attr_e('Remove', 'subscriber-notifications'); ?>">&times;</button>
                                                            <input type="hidden" name="<?php echo esc_attr($name_prefix . '[single_item_include_post_ids][]'); ?>" value="<?php echo esc_attr((string) $picked_post->ID); ?>" />
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p class="description">
                                                    <?php esc_html_e('The on-page subscribe widget appears only on these posts. Taxonomy rules are not used in this mode.', 'subscriber-notifications'); ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <?php submit_button(__('Save Content Types', 'subscriber-notifications')); ?>
    </form>
</div>

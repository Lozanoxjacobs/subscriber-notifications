<?php
/**
 * Reusable Target Content fieldset for the create / edit notification screens.
 *
 * Expects:
 *
 * @var array $enabled_post_types Slugs of post types enabled in Content Types.
 * @var array $selected_targets   Existing notification target preferences (preferences shape).
 *
 * @package SubscriberNotifications
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$enabled_post_types = isset($enabled_post_types) ? (array) $enabled_post_types : array();
$selected_targets   = isset($selected_targets) ? (array) $selected_targets : array();
?>
<div class="sn-targets">
    <?php foreach ($enabled_post_types as $post_type) :
        $form_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
        if (empty($form_taxonomies)) {
            continue;
        }
        $post_type_label = SubscriberNotifications_Content_Config::get_post_type_label($post_type);
    ?>
        <details class="sn-section">
            <summary><strong><?php echo esc_html($post_type_label); ?></strong></summary>
            <div class="sn-section__body">
                <?php foreach ($form_taxonomies as $taxonomy) :
                    $tax_label = SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy);
                    $terms     = SubscriberNotifications_Term_Resolver::get_terms_for_form($post_type, $taxonomy);
                    if (empty($terms)) {
                        continue;
                    }
                    $field_name        = 'target_preferences[' . $post_type . '][' . $taxonomy . '][]';
                    $select_all_target = 'target_preferences[' . $post_type . '][' . $taxonomy . ']';
                    $selected_ids      = isset($selected_targets[$post_type][$taxonomy]) && is_array($selected_targets[$post_type][$taxonomy])
                        ? array_map('intval', $selected_targets[$post_type][$taxonomy])
                        : array();
                ?>
                    <fieldset class="sn-taxonomy">
                        <legend><?php echo esc_html($tax_label); ?></legend>
                        <label class="sn-select-all-label">
                            <input type="checkbox" class="sn-select-all" data-target="<?php echo esc_attr($select_all_target); ?>" />
                            <?php
                            /* translators: %s: Taxonomy label */
                            printf(esc_html__('Select all %s', 'subscriber-notifications'), esc_html($tax_label));
                            ?>
                        </label>
                        <?php
                        SubscriberNotifications_Term_Checklist::render(
                            $terms,
                            $field_name,
                            $selected_ids,
                            $taxonomy
                        );
                        ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>

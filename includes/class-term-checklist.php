<?php
/**
 * Renders taxonomy term checklists for subscribe, preferences, and admin targets.
 *
 * Non-hierarchical taxonomies: flat list sorted A–Z.
 * Hierarchical taxonomies: nested tree with siblings sorted A–Z at each level.
 *
 * @package SubscriberNotifications
 * @since 3.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Term checklist markup helper.
 */
class SubscriberNotifications_Term_Checklist {

    /**
     * Echo a term checklist (`<ul class="sn-term-list">` …).
     *
     * @param WP_Term[] $terms          Terms to display (subset allowed).
     * @param string    $field_name     Checkbox `name` (e.g. `preferences[post][category][]`).
     * @param int[]     $selected_ids   Checked term IDs.
     * @param string    $taxonomy_slug  Taxonomy slug (determines flat vs nested layout).
     */
    public static function render(array $terms, $field_name, array $selected_ids, $taxonomy_slug) {
        if (empty($terms)) {
            return;
        }

        $taxonomy_slug = (string) $taxonomy_slug;
        $field_name    = (string) $field_name;
        $selected_ids  = array_map('intval', $selected_ids);

        $tax_object     = get_taxonomy($taxonomy_slug);
        $is_hierarchical = $tax_object && !empty($tax_object->hierarchical);

        if (!$is_hierarchical) {
            $terms = self::sort_terms_by_name($terms);
            echo '<ul class="sn-term-list">';
            foreach ($terms as $term) {
                self::render_term_item($term, $field_name, $selected_ids);
            }
            echo '</ul>';
            return;
        }

        list($children_map, $by_id) = self::build_children_map($terms);
        $roots                      = self::find_root_term_ids($terms, $by_id);
        self::sort_term_ids_by_name($roots, $by_id);

        echo '<ul class="sn-term-list">';
        foreach ($roots as $term_id) {
            self::render_term_branch($term_id, $children_map, $by_id, $field_name, $selected_ids);
        }
        echo '</ul>';
    }

    /**
     * @param WP_Term[] $terms
     * @return WP_Term[]
     */
    private static function sort_terms_by_name(array $terms) {
        usort(
            $terms,
            function ($a, $b) {
                return strcasecmp($a->name, $b->name);
            }
        );
        return $terms;
    }

    /**
     * @param WP_Term[] $terms
     * @return array{0: array<int, int[]>, 1: array<int, WP_Term>}
     */
    private static function build_children_map(array $terms) {
        $by_id         = array();
        $children_map  = array();
        $term_ids      = array();

        foreach ($terms as $term) {
            $by_id[(int) $term->term_id] = $term;
            $term_ids[]                  = (int) $term->term_id;
        }

        $term_id_set = array_fill_keys($term_ids, true);

        foreach ($terms as $term) {
            $parent_id = (int) $term->parent;
            if ($parent_id > 0 && !isset($term_id_set[$parent_id])) {
                $parent_id = 0;
            }
            if (!isset($children_map[$parent_id])) {
                $children_map[$parent_id] = array();
            }
            $children_map[$parent_id][] = (int) $term->term_id;
        }

        return array($children_map, $by_id);
    }

    /**
     * Root terms: parent is 0 or parent is outside the displayed set.
     *
     * @param WP_Term[]              $terms
     * @param array<int, WP_Term>    $by_id
     * @return int[]
     */
    private static function find_root_term_ids(array $terms, array $by_id) {
        $term_id_set = array();
        foreach ($by_id as $term_id => $term) {
            $term_id_set[$term_id] = true;
        }

        $roots = array();
        foreach ($terms as $term) {
            $parent_id = (int) $term->parent;
            if ($parent_id === 0 || !isset($term_id_set[$parent_id])) {
                $roots[] = (int) $term->term_id;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @param int[]               $term_ids
     * @param array<int, WP_Term> $by_id
     */
    private static function sort_term_ids_by_name(array &$term_ids, array $by_id) {
        usort(
            $term_ids,
            function ($a_id, $b_id) use ($by_id) {
                $a_name = isset($by_id[$a_id]) ? $by_id[$a_id]->name : '';
                $b_name = isset($by_id[$b_id]) ? $by_id[$b_id]->name : '';
                return strcasecmp($a_name, $b_name);
            }
        );
    }

    /**
     * @param int[]               $children_map Keys are parent term IDs; values are child term ID lists.
     * @param array<int, WP_Term> $by_id
     */
    private static function render_term_branch($term_id, array $children_map, array $by_id, $field_name, array $selected_ids) {
        if (!isset($by_id[$term_id])) {
            return;
        }

        $term = $by_id[$term_id];
        echo '<li class="sn-term-item">';
        self::render_checkbox_label($term, $field_name, $selected_ids);

        $child_ids = isset($children_map[$term_id]) ? $children_map[$term_id] : array();
        if (!empty($child_ids)) {
            self::sort_term_ids_by_name($child_ids, $by_id);
            echo '<ul class="sn-term-children">';
            foreach ($child_ids as $child_id) {
                self::render_term_branch($child_id, $children_map, $by_id, $field_name, $selected_ids);
            }
            echo '</ul>';
        }

        echo '</li>';
    }

    /**
     * @param WP_Term $term
     */
    private static function render_term_item($term, $field_name, array $selected_ids) {
        echo '<li class="sn-term-item">';
        self::render_checkbox_label($term, $field_name, $selected_ids);
        echo '</li>';
    }

    /**
     * @param WP_Term $term
     */
    private static function render_checkbox_label($term, $field_name, array $selected_ids) {
        ?>
        <label>
            <input type="checkbox"
                name="<?php echo esc_attr($field_name); ?>"
                value="<?php echo esc_attr((int) $term->term_id); ?>"
                <?php checked(in_array((int) $term->term_id, $selected_ids, true)); ?> />
            <?php echo esc_html($term->name); ?>
        </label>
        <?php
    }
}

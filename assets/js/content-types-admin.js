/**
 * Content Types admin page interactions.
 *
 * - Toggles visibility of the parent/include/exclude rows based on the selected
 *   `term_display` mode for each taxonomy block.
 * - Relies on core postbox toggling (loaded as a dependency) for the per-post-type
 *   collapsible panels.
 */
(function ($) {
    'use strict';

    function syncModeRows($block) {
        var mode = $block.find('select.sn-term-display').val();
        $block.find('.sn-mode-row').each(function () {
            var $row = $(this);
            $row.toggle($row.data('mode') === mode);
        });
    }

    function init() {
        $('.sn-taxonomy-block').each(function () {
            syncModeRows($(this));
        });

        $(document).on('change', 'select.sn-term-display', function () {
            syncModeRows($(this).closest('.sn-taxonomy-block'));
        });

        if (typeof postboxes !== 'undefined' && postboxes && typeof postboxes.add_postbox_toggles === 'function') {
            postboxes.add_postbox_toggles('toplevel_page_subscriber-notifications-content-types');
        }
    }

    $(init);
})(jQuery);

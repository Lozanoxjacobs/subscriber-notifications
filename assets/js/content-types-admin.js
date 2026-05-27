/**
 * Content Types admin page interactions.
 *
 * - Toggles term_display mode rows per taxonomy block.
 * - On-page subscribe widget visibility mode (rules vs pick list) with conditional fields.
 * - Post pickers for except/include lists (REST search).
 */
(function ($) {
    'use strict';

    var searchTimers = {};
    var MODE_RULES = 'rules';
    var MODE_PICK_LIST = 'pick_list';

    function syncModeRows($block) {
        var mode = $block.find('select.sn-term-display').val();
        $block.find('.sn-mode-row').each(function () {
            var $row = $(this);
            $row.toggle($row.data('mode') === mode);
        });
    }

    function syncSingleItemPanel($box) {
        var enabled = $box.find('input[id$="-single-item"]').is(':checked');
        $box.find('.sn-single-item-eligibility').toggle(enabled);
    }

    function getEligibilityMode($eligibility) {
        var $checked = $eligibility.find('input.sn-eligibility-mode-radio:checked');
        return $checked.length ? $checked.val() : MODE_RULES;
    }

    function syncEligibilityMode($eligibility) {
        var mode = getEligibilityMode($eligibility);
        var isRules = mode === MODE_RULES;

        $eligibility.find('.sn-eligibility-rules-mode').toggle(isRules);
        $eligibility.find('.sn-eligibility-pick-list-mode').toggle(!isRules);

        var $box = $eligibility.closest('.inside');
        $box.find('.sn-taxonomies-pick-list-note').toggle(!isRules);
    }

    function disableInactiveEligibilityInputs($form) {
        $form.find('.sn-single-item-eligibility').each(function () {
            var $eligibility = $(this);
            var mode = getEligibilityMode($eligibility);
            var isRules = mode === MODE_RULES;

            $eligibility.find('.sn-eligibility-rules-mode :input').prop('disabled', !isRules);
            $eligibility.find('.sn-eligibility-pick-list-mode :input').prop('disabled', isRules);
        });
    }

    function getSelectedIds($picker) {
        var ids = [];
        $picker.find('.sn-post-picker-selected li').each(function () {
            ids.push(parseInt($(this).data('post-id'), 10));
        });
        return ids;
    }

    function ensureApiFetch() {
        if (typeof wp === 'undefined' || !wp.apiFetch) {
            return false;
        }
        if (!window.snContentTypesApiFetchReady && window.snContentTypesAdmin) {
            wp.apiFetch.use(wp.apiFetch.createRootURLMiddleware(snContentTypesAdmin.restRoot));
            wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(snContentTypesAdmin.nonce));
            window.snContentTypesApiFetchReady = true;
        }
        return true;
    }

    function renderResults($picker, posts, selectedIds) {
        var $results = $picker.find('.sn-post-picker-results');
        $results.empty();
        if (!posts.length) {
            $results.append('<li class="sn-post-picker-empty">' + snContentTypesAdmin.i18n.noResults + '</li>');
            return;
        }
        posts.forEach(function (post) {
            if (selectedIds.indexOf(post.id) !== -1) {
                return;
            }
            var title = post.title && post.title.rendered ? post.title.rendered : ('#' + post.id);
            var $item = $('<li><button type="button" class="button button-small sn-post-picker-add"></button></li>');
            $item.find('button').text(snContentTypesAdmin.i18n.add + ': ' + $('<div>').html(title).text());
            $item.find('button').data('post-id', post.id).data('post-title', $('<div>').html(title).text());
            $results.append($item);
        });
    }

    function resolveFieldName($picker) {
        var $hidden = $picker.find('.sn-post-picker-selected li input[type="hidden"]').first();
        if ($hidden.length) {
            return $hidden.attr('name');
        }
        var listType = $picker.data('list');
        var namePrefix = $picker.closest('.postbox').find('input[name*="[enabled]"]').attr('name');
        if (namePrefix) {
            namePrefix = namePrefix.replace('[enabled]', '');
            return namePrefix + '[single_item_' + listType + '_post_ids][]';
        }
        return '';
    }

    function addSelectedPost($picker, postId, postTitle, fieldName) {
        var $selected = $picker.find('.sn-post-picker-selected');
        if ($selected.find('li[data-post-id="' + postId + '"]').length) {
            return;
        }
        var $li = $('<li></li>').attr('data-post-id', postId);
        $li.append('<span class="sn-post-picker-title"></span>');
        $li.find('.sn-post-picker-title').text(postTitle);
        $li.append('<button type="button" class="button-link sn-post-picker-remove" aria-label="Remove">&times;</button>');
        $li.append('<input type="hidden" />');
        $li.find('input').attr('name', fieldName).val(postId);
        $selected.append($li);
    }

    function searchPosts($input) {
        var query = $.trim($input.val());
        var $picker = $input.closest('.sn-post-picker');
        var $eligibility = $picker.closest('.sn-single-item-eligibility');
        var restBase = $eligibility.data('rest-base');
        var $results = $picker.find('.sn-post-picker-results');

        if (query.length < 2) {
            $results.empty();
            return;
        }

        if (!ensureApiFetch()) {
            return;
        }

        $results.html('<li class="sn-post-picker-empty">' + snContentTypesAdmin.i18n.searching + '</li>');

        var path = '/wp/v2/' + restBase + '?search=' + encodeURIComponent(query) + '&status=publish&per_page=20&_fields=id,title';
        wp.apiFetch({ path: path }).then(function (posts) {
            renderResults($picker, posts || [], getSelectedIds($picker));
        }).catch(function () {
            $results.html('<li class="sn-post-picker-empty">' + snContentTypesAdmin.i18n.noResults + '</li>');
        });
    }

    function confirmModeSwitch($eligibility, nextMode) {
        if (!window.snContentTypesAdmin || !snContentTypesAdmin.i18n) {
            return true;
        }

        var currentMode = getEligibilityMode($eligibility);
        if (currentMode === nextMode) {
            return true;
        }

        if (nextMode === MODE_PICK_LIST) {
            var excludeCount = $eligibility.find('.sn-eligibility-rules-mode .sn-post-picker-selected li').length;
            if (excludeCount > 0 && !window.confirm(snContentTypesAdmin.i18n.confirmPickList)) {
                return false;
            }
        }

        if (nextMode === MODE_RULES) {
            var includeCount = $eligibility.find('.sn-eligibility-pick-list-mode .sn-post-picker-selected li').length;
            if (includeCount > 0 && !window.confirm(snContentTypesAdmin.i18n.confirmRules)) {
                return false;
            }
        }

        return true;
    }

    function initEligibilityModes() {
        $('.sn-single-item-eligibility').each(function () {
            syncEligibilityMode($(this));
        });
    }

    function init() {
        $('.sn-taxonomy-block').each(function () {
            syncModeRows($(this));
        });

        $('.postbox[id^="sn-pt-"]').each(function () {
            syncSingleItemPanel($(this));
        });

        initEligibilityModes();

        $(document).on('change', 'select.sn-term-display', function () {
            syncModeRows($(this).closest('.sn-taxonomy-block'));
        });

        $(document).on('change', 'input[id$="-single-item"]', function () {
            syncSingleItemPanel($(this).closest('.postbox'));
        });

        $(document).on('change', 'input.sn-eligibility-mode-radio', function () {
            var $radio = $(this);
            var $eligibility = $radio.closest('.sn-single-item-eligibility');
            var nextMode = $radio.val();

            if (!confirmModeSwitch($eligibility, nextMode)) {
                var previousMode = nextMode === MODE_PICK_LIST ? MODE_RULES : MODE_PICK_LIST;
                $eligibility.find('input.sn-eligibility-mode-radio[value="' + previousMode + '"]').prop('checked', true);
                return;
            }

            syncEligibilityMode($eligibility);
        });

        $(document).on('input', '.sn-post-picker-search', function () {
            var $input = $(this);
            var key = $input.attr('id') || 'picker';
            clearTimeout(searchTimers[key]);
            searchTimers[key] = setTimeout(function () {
                searchPosts($input);
            }, 300);
        });

        $(document).on('click', '.sn-post-picker-add', function () {
            var $btn = $(this);
            var $picker = $btn.closest('.sn-post-picker');
            var fieldName = resolveFieldName($picker);
            addSelectedPost($picker, $btn.data('post-id'), $btn.data('post-title'), fieldName);
            $picker.find('.sn-post-picker-results').empty();
            $picker.find('.sn-post-picker-search').val('');
        });

        $(document).on('click', '.sn-post-picker-remove', function () {
            $(this).closest('li').remove();
        });

        $('#sn-content-types-form').on('submit', function () {
            disableInactiveEligibilityInputs($(this));
        });

        if (typeof postboxes !== 'undefined' && postboxes && typeof postboxes.add_postbox_toggles === 'function') {
            postboxes.add_postbox_toggles('toplevel_page_subscriber-notifications-content-types');
        }
    }

    $(init);
})(jQuery);

/*
 * Subscriber Notifications — frontend interactions (v3).
 *
 * - Submits the subscribe and preferences forms via AJAX.
 * - Validates: name length, email, frequency selection, and at least one term
 *   across all post type / taxonomy sections.
 * - Drives the generic "select all" checkbox per taxonomy block using a
 *   data-target attribute that names the input prefix (e.g.
 *   "preferences[post][category]").
 */
(function ($) {
    'use strict';

    var i18n = (window.subscriberNotifications && window.subscriberNotifications.i18n) || {};

    function findTermInputs($scope, target) {
        return $scope.find('input[type="checkbox"]').filter(function () {
            var name = $(this).attr('name') || '';
            return name.indexOf(target + '[') === 0;
        });
    }

    function syncSelectAll($scope) {
        $scope.find('.sn-select-all').each(function () {
            var $box = $(this);
            var target = $box.data('target');
            if (!target) {
                return;
            }
            var $terms = findTermInputs($scope, target);
            var $checked = $terms.filter(':checked');
            if ($terms.length === 0) {
                return;
            }
            if ($checked.length === 0) {
                $box.prop('indeterminate', false).prop('checked', false);
            } else if ($checked.length === $terms.length) {
                $box.prop('indeterminate', false).prop('checked', true);
            } else {
                $box.prop('indeterminate', true);
            }
        });
    }

    function selectionHasTerm($form) {
        return $form.find('input[type="checkbox"]').filter(function () {
            return /^preferences\[/.test($(this).attr('name') || '');
        }).filter(':checked').length > 0;
    }

    function isLockedContact($form) {
        if (window.subscriberNotifications && window.subscriberNotifications.preferencesProfileLocked) {
            return true;
        }
        if ($form && $form.length && $form.attr('data-profile-locked') === '1') {
            return true;
        }
        return !!(window.subscriberNotifications && window.subscriberNotifications.isLoggedIn);
    }

    function getFrequencyRadios($form) {
        return $form.find('input[name="frequency"]');
    }

    function clearFrequencyValidity($form) {
        var $fieldset = $form.find('.sn-frequency');
        $fieldset.removeClass('sn-field-invalid');
        getFrequencyRadios($form).each(function () {
            this.setCustomValidity('');
        });
    }

    function reportFrequencyRequired($form) {
        var message = i18n.errorFrequency || 'Please select a frequency preference.';
        var $fieldset = $form.find('.sn-frequency');
        var $first = getFrequencyRadios($form).filter('[required]').first();

        $fieldset.addClass('sn-field-invalid');
        if ($first.length) {
            $first[0].setCustomValidity(message);
            if (typeof $first[0].reportValidity === 'function') {
                $first[0].reportValidity();
            }
            $first.trigger('focus');
        }
    }

    function validateForm($form) {
        var $name = $form.find('#subscriber_name');
        var $email = $form.find('#subscriber_email');
        var $frequencyChecked = $form.find('input[name="frequency"]:checked');
        var errors = [];

        clearFrequencyValidity($form);

        if (!isLockedContact($form)) {
            if (($name.val() || '').trim().length < 2) {
                errors.push(i18n.errorNameLength || 'Name must be at least 2 characters long.');
            }
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if ($email.length && !emailRegex.test($email.val() || '')) {
                errors.push(i18n.errorEmail || 'Please enter a valid email address.');
            }
        } else if (!window.subscriberNotifications.lockedName) {
            errors.push(i18n.errorMissingProfileName || 'Please add your name to your account profile before subscribing.');
        }

        if ($frequencyChecked.length === 0) {
            errors.push(i18n.errorFrequency || 'Please select a frequency preference.');
            reportFrequencyRequired($form);
        }

        var hasPreferenceInputs = $form.find('input[name^="preferences["]').length > 0;
        if (!selectionHasTerm($form) && hasPreferenceInputs) {
            errors.push(i18n.errorAtLeastOneTerm || 'Please select at least one option to subscribe to.');
        }

        var $errorContainer = $form.find('.form-errors');
        if (errors.length > 0) {
            if ($errorContainer.length === 0) {
                $errorContainer = $('<div class="form-errors"></div>');
                $form.prepend($errorContainer);
            }
            $errorContainer.html('<ul><li>' + errors.join('</li><li>') + '</li></ul>').show();
        } else if ($errorContainer.length) {
            $errorContainer.hide();
        }

        return errors.length === 0;
    }

    $(function () {
        // Subscribe form submit.
        $('#subscriber-notifications-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            if (!validateForm($form)) {
                return;
            }

            var $button = $form.find('button[type="submit"]');
            var $message = $('#subscriber-message');
            var originalText = $button.text();

            $button.prop('disabled', true).text(i18n.subscribing || 'Subscribing...');
            $message.hide();

            var formData = $form.serialize();
            formData += '&action=subscriber_notifications_subscribe';

            $.ajax({
                url: window.subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response && response.success) {
                        $message.removeClass('error').addClass('success').text(response.data).show();
                        $form[0].reset();
                        if (window.subscriberNotifications.isLoggedIn) {
                            $('#subscriber_name').val(window.subscriberNotifications.lockedName || '');
                            $('#subscriber_email').val(window.subscriberNotifications.lockedEmail || '');
                        }
                        syncSelectAll($form);
                    } else {
                        $message.removeClass('success').addClass('error').text((response && response.data) || i18n.genericError).show();
                    }
                },
                error: function () {
                    $message.removeClass('success').addClass('error').text(i18n.genericError || 'An error occurred. Please try again.').show();
                },
                complete: function () {
                    $button.prop('disabled', false).text(originalText || i18n.subscribe || 'Subscribe');
                }
            });
        });

        // Preferences form submit.
        $('#subscriber-preferences-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            if (!validateForm($form)) {
                return;
            }

            var $button = $form.find('button[type="submit"]');
            var $message = $('#preferences-message');
            var originalText = $button.text();

            $button.prop('disabled', true).text(i18n.updating || 'Updating...');
            $message.hide();

            var formData = $form.serialize();
            formData += '&action=subscriber_notifications_update_preferences';

            $.ajax({
                url: window.subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response && response.success) {
                        var url = new URL(window.location.href);
                        var isReactivating = $form.data('reactivating') === 1 || $form.attr('data-reactivating') === '1';

                        if (isReactivating || url.searchParams.get('unsubscribed') === '1') {
                            url.searchParams.delete('unsubscribed');
                            url.searchParams.set('reactivated', '1');
                            window.location.assign(url.toString());
                            return;
                        }

                        $message.removeClass('error').addClass('success').text(response.data).show();
                    } else {
                        $message.removeClass('success').addClass('error').text((response && response.data) || i18n.genericError).show();
                    }
                },
                error: function () {
                    $message.removeClass('success').addClass('error').text(i18n.genericError || 'An error occurred. Please try again.').show();
                },
                complete: function () {
                    $button.prop('disabled', false).text(originalText || i18n.update || 'Update Preferences');
                }
            });
        });

        // Unsubscribe button.
        $('#unsubscribe-button').on('click', function (e) {
            e.preventDefault();
            if (!window.confirm(i18n.confirmUnsubscribe || 'Are you sure you want to unsubscribe?')) {
                return;
            }
            var $button = $(this);
            var $form = $('#subscriber-preferences-form');
            var token = $form.find('input[name="token"]').val();
            var originalText = $button.text();
            $button.prop('disabled', true).text(i18n.unsubscribing || 'Unsubscribing...');
            $.ajax({
                url: window.subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'subscriber_notifications_unsubscribe',
                    token: token,
                    unsubscribe_nonce: window.subscriberNotifications.unsubscribeNonce
                },
                success: function (response) {
                    if (response && response.success) {
                        var url = new URL(window.location.href);
                        url.searchParams.set('unsubscribed', '1');
                        window.location.assign(url.toString());
                    } else {
                        window.alert((response && response.data) || (i18n.genericError || 'An error occurred. Please try again.'));
                        $button.prop('disabled', false).text(originalText || i18n.unsubscribe || 'Unsubscribe');
                    }
                },
                error: function () {
                    window.alert(i18n.genericError || 'An error occurred. Please try again.');
                    $button.prop('disabled', false).text(originalText || i18n.unsubscribe || 'Unsubscribe');
                }
            });
        });

        // Dynamic select-all per taxonomy block.
        $(document).on('change', '.sn-select-all', function () {
            var $box = $(this);
            var target = $box.data('target');
            if (!target) {
                return;
            }
            var checked = $box.prop('checked');
            var $scope = $box.closest('form');
            findTermInputs($scope, target).prop('checked', checked);
            $box.prop('indeterminate', false);
        });

        // Update select-all state when individual term checkboxes change.
        $(document).on('change', 'input[type="checkbox"][name^="preferences["]', function () {
            var $form = $(this).closest('form');
            syncSelectAll($form);
        });

        // Clear frequency required state when a option is selected.
        $(document).on('change', 'input[name="frequency"]', function () {
            clearFrequencyValidity($(this).closest('form'));
        });

        // Initial select-all sync on page load (for preferences form with pre-checked items).
        $('form').each(function () {
            syncSelectAll($(this));
        });

        // Post subscribe widget.
        $(document).on('submit', '.sn-post-subscribe-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $wrap = $form.closest('.subscriber-notifications-post-subscribe');
            var $button = $form.find('button[type="submit"]');
            var $message = $wrap.find('.sn-post-subscribe-message');
            var originalText = $button.text();

            var name = ($form.find('[name="subscriber_name"]').val() || '').trim();
            var email = ($form.find('[name="subscriber_email"]').val() || '').trim();
            if ($form.find('[name="subscriber_name"]').length && name.length < 2) {
                $message.removeClass('success').addClass('error').text(i18n.errorNameLength || 'Name must be at least 2 characters long.').show();
                return;
            }
            if ($form.find('[name="subscriber_email"]').length) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    $message.removeClass('success').addClass('error').text(i18n.errorEmail || 'Please enter a valid email address.').show();
                    return;
                }
            }

            $button.prop('disabled', true).text(i18n.subscribing || 'Subscribing...');
            $message.hide();

            var formData = $form.serialize();
            formData += '&action=subscriber_notifications_post_subscribe';

            $.ajax({
                url: window.subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response && response.success && response.data && response.data.html) {
                        $wrap.replaceWith(response.data.html);
                    } else if (response && response.success) {
                        $message.removeClass('error').addClass('success').text(response.data).show();
                    } else {
                        $message.removeClass('success').addClass('error').text((response && response.data) || i18n.genericError).show();
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function () {
                    $message.removeClass('success').addClass('error').text(i18n.genericError || 'An error occurred. Please try again.').show();
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
})(jQuery);

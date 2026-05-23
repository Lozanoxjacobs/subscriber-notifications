jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize date picker
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            showTime: true,
            timeFormat: 'HH:mm'
        });
    }
    
    // Handle CSV import
    $('#csv-import-form').on('submit', function(e) {
        var fileInput = $('#csv-file')[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file.');
            e.preventDefault();
            return false;
        }
        
        var file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a valid CSV file.');
            e.preventDefault();
            return false;
        }
    });
    
    // Handle CSV export
    $('.export-csv').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'subscriber_notifications_export_csv',
                nonce: subscriberNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                } else {
                    alert('Export failed: ' + response.data);
                }
            },
            error: function() {
                alert('Export failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Export CSV');
            }
        });
    });
    
    // Target Content has no HTML5 "required" for checkbox groups; sync editor before submit.
    $('#create-notification-form, #edit-notification-form').on('submit', function(e) {
        var $form = $(this);

        if (typeof tinymce !== 'undefined' && tinymce.get('notification_content')) {
            tinymce.get('notification_content').save();
        }

        if ($form.find('input[name^="target_preferences["]:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one target term in Target Content.');
            var $targets = $form.find('.sn-targets').first();
            if ($targets.length) {
                $('html, body').animate({ scrollTop: $targets.offset().top - 50 }, 200);
            }
            return false;
        }
    });

    // Handle notification preview
    $('#notification_content').on('input', function() {
        var content = $(this).val();
        if (content) {
            // Replace shortcodes with sample data
            content = content.replace(/\[subscriber_name\]/g, 'John Doe');
            content = content.replace(/\[subscriber_email\]/g, 'john@example.com');
            content = content.replace(/\[selected_subscriptions\]/g, 'Sample selections across configured taxonomies');
            content = content.replace(/\[selected_terms[^\]]*\]/g, 'Sample term, Another term');
            content = content.replace(/\[content_feed[^\]]*\]/g, '<ul><li>Sample feed item</li></ul>');
            content = content.replace(/\[delivery_frequency\]/g, 'Weekly');
            content = content.replace(/\[site_title\]/g, subscriberNotifications.siteTitle || 'Site Title');
            content = content.replace(/\[manage_preferences_link\]/g, '<a href="#">Manage Preferences</a>');
            content = content.replace(/\[manage_preferences_link text="([^"]+)"\]/g, '<a href="#">$1</a>');
            
            $('#preview-content').html(content);
        } else {
            $('#preview-content').html('<p>Preview will appear here when you type in the content field.</p>');
        }
    });
    
    // Handle notification checkbox toggle
    $(document).on('change', '#notify_subscribers', function() {
        var isChecked = $(this).is(':checked');
        if (isChecked) {
            $('#notification-options').slideDown();
        } else {
            $('#notification-options').slideUp();
        }
    });
    
    // Initialize notification checkbox to unchecked and hide options
    $(document).ready(function() {
        $('#notify_subscribers').prop('checked', false);
        $('#notification-options').hide();
    });
    
    function shouldAutoSaveDraft($form) {
        if ($form.hasClass('notification-form')) {
            return false;
        }

        var method = ($form.attr('method') || 'get').toLowerCase();
        if (method === 'get') {
            return false;
        }

        if ($form.attr('id') === 'sn-purge-logs-form') {
            return false;
        }

        return true;
    }

    function getFormDraftKey($form) {
        var formId = $form.attr('id');
        if (formId) {
            return 'subscriber_notifications_form_draft_' + formId;
        }

        var params = new URLSearchParams(window.location.search);
        var page = params.get('page') || 'unknown';
        var tab = params.get('tab') || '';
        return 'subscriber_notifications_form_draft_' + page + (tab ? '_' + tab : '');
    }

    function restoreFormDraft($form) {
        var savedData = localStorage.getItem(getFormDraftKey($form));
        if (!savedData) {
            return;
        }

        var hasData = $form.find('input[value!=""], textarea:not(:empty)').length > 0;
        if (hasData) {
            return;
        }

        var params = new URLSearchParams(savedData);
        params.forEach(function(value, key) {
            if (key === 'notify_subscribers') {
                return;
            }

            var $field = $form.find('[name="' + key + '"]');
            if ($field.length) {
                if ($field.is(':checkbox, :radio')) {
                    $field.filter('[value="' + value + '"]').prop('checked', true);
                } else {
                    $field.val(value);
                }
            }
        });
    }

    // Drop legacy shared draft key (pre-3.5.2).
    localStorage.removeItem('subscriber_notifications_form_data');

    $('form').each(function() {
        var $form = $(this);
        if (shouldAutoSaveDraft($form)) {
            restoreFormDraft($form);
        }
    });

    $(document).on('input change', 'form input, form textarea, form select', function() {
        var $form = $(this).closest('form');
        if (!shouldAutoSaveDraft($form)) {
            return;
        }

        localStorage.setItem(getFormDraftKey($form), $form.serialize());
    });

    $(document).on('submit', 'form', function() {
        var $form = $(this);
        if (!shouldAutoSaveDraft($form)) {
            return;
        }

        localStorage.removeItem(getFormDraftKey($form));
        $('#notify_subscribers').prop('checked', false);
        $('#notification-options').hide();
    });
    
    // Generic "Select all" for v3 target/preference checklists.
    //
    // Markup pattern (admin + frontend):
    //   <input type="checkbox" class="sn-select-all" data-target="target_preferences[post][category]">
    //   <input type="checkbox" name="target_preferences[post][category][]" value="...">
    //
    // The select-all box toggles every term input whose name begins with
    // "<data-target>[". Individual changes update the parent indeterminate state.

    function snFindTermInputs($scope, target) {
        return $scope.find('input[type="checkbox"]').filter(function () {
            var name = $(this).attr('name') || '';
            return name.indexOf(target + '[') === 0;
        });
    }

    function snSyncSelectAll($scope) {
        $scope.find('.sn-select-all').each(function () {
            var $box = $(this);
            var target = $box.data('target');
            if (!target) {
                return;
            }
            var $terms = snFindTermInputs($scope, target);
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

    $(document).on('change', '.sn-select-all', function () {
        var $box = $(this);
        var target = $box.data('target');
        if (!target) {
            return;
        }
        var $scope = $box.closest('form');
        if ($scope.length === 0) {
            $scope = $box.closest('.sn-targets');
        }
        if ($scope.length === 0) {
            $scope = $(document);
        }
        snFindTermInputs($scope, target).prop('checked', $box.prop('checked'));
        $box.prop('indeterminate', false);
    });

    $(document).on('change', 'input[type="checkbox"][name^="target_preferences["], input[type="checkbox"][name^="preferences["]', function () {
        var $scope = $(this).closest('form');
        if ($scope.length === 0) {
            $scope = $(this).closest('.sn-targets');
        }
        if ($scope.length === 0) {
            $scope = $(document);
        }
        snSyncSelectAll($scope);
    });

    $('form, .sn-targets').each(function () {
        snSyncSelectAll($(this));
    });

    // Dashboard: test email (reuses test_wp_mail AJAX handler).
    if ($('#sn-dashboard-test-wp-mail').length && typeof subscriberNotifications !== 'undefined' && subscriberNotifications.dashboard) {
        var dashCfg = subscriberNotifications.dashboard;
        $('#sn-dashboard-test-wp-mail').on('click', function () {
            var $button = $(this);
            var $result = $('#sn-dashboard-wp-mail-test-result');
            var testEmail = $('#sn-dashboard-test-email').val();

            if (!testEmail) {
                $result.html('<div class="notice notice-error inline"><p>' + dashCfg.testMailEnterEmail + '</p></div>');
                return;
            }

            $button.prop('disabled', true).text(dashCfg.testMailSending);
            $result.html('');

            $.ajax({
                url: subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'test_wp_mail',
                    test_email: testEmail,
                    nonce: dashCfg.testMailNonce
                },
                success: function (response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                    }
                },
                error: function () {
                    $result.html('<div class="notice notice-error inline"><p>' + dashCfg.testMailFailed + '</p></div>');
                },
                complete: function () {
                    $button.prop('disabled', false).text(dashCfg.testMailButton);
                }
            });
        });
    }

    // Notification list: preview modal (Notifications admin screen).
    if ($('.view-notification').length && subscriberNotifications.notificationList) {
        var nlCfg = subscriberNotifications.notificationList;
        var $modal = $('#notification-preview-modal');

        $('.view-notification').on('click', function (e) {
            e.preventDefault();
            var notificationId = $(this).data('id');

            $.ajax({
                url: subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_notification_preview',
                    notification_id: notificationId,
                    nonce: nlCfg.previewNonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#notification-preview-content').html(response.data);
                        $modal.addClass('is-open');
                    }
                }
            });
        });

        $('.notification-modal-close').on('click', function () {
            $modal.removeClass('is-open');
        });

        $(window).on('click', function (e) {
            if (e.target.id === 'notification-preview-modal') {
                $modal.removeClass('is-open');
            }
        });
    }

    // Send preview email (Create / Edit notification screens).
    if ($('#send-preview-email').length && subscriberNotifications.previewEmail) {
        var peCfg = subscriberNotifications.previewEmail;

        $('#send-preview-email').on('click', function (e) {
            e.preventDefault();

            var email = $('#preview_email').val();
            var subject = $('#notification_subject').val();
            var content = '';

            if (typeof tinymce !== 'undefined' && tinymce.get('notification_content')) {
                content = tinymce.get('notification_content').getContent();
            } else {
                content = $('#notification_content').val();
            }

            if (!email) {
                alert(peCfg.enterEmail);
                return;
            }
            if (!subject) {
                alert(peCfg.enterSubject);
                return;
            }
            if (!content) {
                alert(peCfg.enterContent);
                return;
            }

            var $button = $(this);
            var $resultDiv = $('#preview-email-result');

            $button.prop('disabled', true).text(peCfg.sending);
            $resultDiv.html('<p style="color: #666;">' + peCfg.sendingPreview + '</p>');

            $.ajax({
                url: subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'send_preview_email',
                    nonce: peCfg.nonce,
                    email: email,
                    subject: subject,
                    content: content
                },
                success: function (response) {
                    if (response.success) {
                        $resultDiv.html('<p style="color: #46b450;">' + peCfg.sentSuccess + '</p>');
                    } else {
                        $resultDiv.html('<p style="color: #dc3232;">' + peCfg.sentFailed + response.data + '</p>');
                    }
                },
                error: function () {
                    $resultDiv.html('<p style="color: #dc3232;">' + peCfg.sentError + '</p>');
                },
                complete: function () {
                    $button.prop('disabled', false).text(peCfg.buttonLabel);
                }
            });
        });
    }

    // Settings → General: test email button.
    if ($('#test-wp-mail').length && subscriberNotifications.settingsGeneral) {
        var sgCfg = subscriberNotifications.settingsGeneral;

        $('#test-wp-mail').on('click', function () {
            var $button = $(this);
            var $result = $('#wp-mail-test-result');
            var testEmail = $('#test_email').val();

            if (!testEmail) {
                $result.html('<div class="notice notice-error inline"><p>' + sgCfg.testMailEnterEmail + '</p></div>');
                return;
            }

            $button.prop('disabled', true).text(sgCfg.testMailSending);
            $result.html('');

            $.ajax({
                url: subscriberNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'test_wp_mail',
                    test_email: testEmail,
                    nonce: sgCfg.testMailNonce
                },
                success: function (response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                    }
                },
                error: function () {
                    $result.html('<div class="notice notice-error inline"><p>' + sgCfg.testMailFailed + '</p></div>');
                },
                complete: function () {
                    $button.prop('disabled', false).text(sgCfg.testMailButton);
                }
            });
        });
    }

    // Settings → Email Design: header logo media uploader.
    if ($('.upload-logo').length && subscriberNotifications.emailDesign) {
        var edCfg = subscriberNotifications.emailDesign;
        var mediaUploader;

        $('.upload-logo').on('click', function (e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: edCfg.mediaTitle,
                button: { text: edCfg.mediaButton },
                multiple: false,
                library: { type: 'image', uploadedTo: null },
                filterable: 'uploaded'
            });

            mediaUploader.on('select', function () {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

                if (allowedTypes.indexOf(attachment.mime) === -1) {
                    alert(edCfg.invalidMime);
                    return;
                }

                if (attachment.filesizeInBytes && attachment.filesizeInBytes > 200 * 1024) {
                    alert(edCfg.fileTooLarge);
                    return;
                }

                $('#global_header_logo').val(attachment.id);
                $('.logo-preview').html(
                    '<img src="' + attachment.url + '" alt="" />' +
                    '<br><button type="button" class="button remove-logo">' + edCfg.removeLogo + '</button>'
                );
            });

            mediaUploader.open();
        });

        $(document).on('click', '.remove-logo', function (e) {
            e.preventDefault();
            $('#global_header_logo').val('');
            $('.logo-preview').html('<div class="no-logo">' + edCfg.noLogo + '</div>');
        });
    }

    // Settings page: scroll to in-page anchor links (tab deep links).
    if (subscriberNotifications.settingsPage && window.location.hash) {
        setTimeout(function () {
            var $target = $(window.location.hash);
            if ($target.length) {
                $('html, body').animate({ scrollTop: $target.offset().top - 50 }, 500);
            }
        }, 100);
    }

});


(function ($) {
    function snFormatMaintenanceTemplate(template, count, days) {
        return String(template)
            .replace(/%1\$[ds]/g, String(count))
            .replace(/%2\$[ds]/g, String(days));
    }

    function snUpdatePurgeSummary() {
        var $form = $('#sn-purge-logs-form');
        if (!$form.length || !window.subscriberNotifications || !subscriberNotifications.logsMaintenance) {
            return;
        }

        var cfg = subscriberNotifications.logsMaintenance;
        var $select = $form.find('#sn-purge-days');
        var $summary = $('#sn-purge-match-summary');
        var $button = $form.find('button[type="submit"]');
        var count = parseInt($select.find(':selected').data('match-count'), 10) || 0;
        var days = $select.val();
        var summaryTemplate = count > 0 ? cfg.matchTemplate : cfg.matchNoneTemplate;

        $summary.text(snFormatMaintenanceTemplate(summaryTemplate, count, days));
        $button.prop('disabled', count === 0);

        $form.off('submit.snPurgeLogs').on('submit.snPurgeLogs', function (event) {
            if (count === 0) {
                event.preventDefault();
                return false;
            }

            var message = snFormatMaintenanceTemplate(cfg.confirmTemplate, count, days);

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }

    $(document).ready(snUpdatePurgeSummary);
    $(document).on('change', '#sn-purge-days', snUpdatePurgeSummary);
})(jQuery);

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
    
    // Handle bulk actions
    $('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function() {
        var action = $(this).val();
        if (action === 'delete' || action === 'deactivate') {
            if (!confirm('Are you sure you want to perform this action on the selected items?')) {
                $(this).val('');
                return false;
            }
        }
    });
    
    // Handle subscriber actions
    $('.subscriber-action').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var action = $button.data('action');
        var subscriberId = $button.data('subscriber-id');
        
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete this subscriber?')) {
                return false;
            }
        }
        
        // Show loading state
        $button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'subscriber_notifications_subscriber_action',
                subscriber_id: subscriberId,
                subscriber_action: action,
                nonce: subscriberNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text($button.data('original-text'));
            }
        });
    });
    
    // Store original button text
    $('.subscriber-action').each(function() {
        $(this).data('original-text', $(this).text());
    });
    
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
    
    // Handle notification creation
    $('#create-notification-form').on('submit', function(e) {
        var title = $('#notification_title').val();
        var content = $('#notification_content').val();
        
        if (!title.trim()) {
            alert('Please enter a notification title.');
            e.preventDefault();
            return false;
        }
        
        if (!content.trim()) {
            alert('Please enter notification content.');
            e.preventDefault();
            return false;
        }
        
        // Check that at least one term is selected across configured taxonomies.
        var selectedTerms = $('input[name^="target_preferences["]:checked').length;

        if (selectedTerms === 0) {
            alert('Please select at least one term across the configured taxonomies.');
            e.preventDefault();
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
    
    // Auto-save form data
    $('form').on('input change', 'input, textarea, select', function() {
        var $form = $(this).closest('form');
        var formData = $form.serialize();
        localStorage.setItem('subscriber_notifications_form_data', formData);
    });
    
    // Restore form data on page load
    var savedData = localStorage.getItem('subscriber_notifications_form_data');
    if (savedData) {
        // Only restore if form is empty
        var $form = $('form');
        var hasData = $form.find('input[value!=""], textarea:not(:empty), select option:selected').length > 0;
        
        if (!hasData) {
            // Parse and restore form data
            var params = new URLSearchParams(savedData);
            params.forEach(function(value, key) {
                // Skip the notify_subscribers checkbox to prevent accidental notifications
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
    }
    
    // Clear saved data on successful form submission and reset notify checkbox
    $('form').on('submit', function() {
        localStorage.removeItem('subscriber_notifications_form_data');
        // Reset notify checkbox to unchecked after form submission
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
});

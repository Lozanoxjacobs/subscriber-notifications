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
        
        // Check if at least one category is selected
        var newsCategories = $('input[name="news_categories[]"]:checked').length;
        var meetingCategories = $('input[name="meeting_categories[]"]:checked').length;
        
        if (newsCategories === 0 && meetingCategories === 0) {
            alert('Please select at least one category.');
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
            content = content.replace(/\[selected_news_categories\]/g, 'Announcements, News');
            content = content.replace(/\[selected_meeting_categories\]/g, 'City Council, Planning Commission');
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
    
    // Handle SendGrid connection test
    $('#test-sendgrid').on('click', function() {
        var $button = $(this);
        var $result = $('#sendgrid-test-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<p>Testing connection...</p>');
        
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_sendgrid_connection',
                nonce: subscriberNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<div class="notice notice-error inline"><p>Connection test failed: ' + error + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
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
    
    // Handle "Select All" functionality for admin forms
    $(document).on('change', '#select-all-news-admin, #select-all-news-admin-edit', function() {
        var isChecked = $(this).is(':checked');
        $('.news-category-checkbox').prop('checked', isChecked);
    });
    
    $(document).on('change', '#select-all-meetings-admin, #select-all-meetings-admin-edit', function() {
        var isChecked = $(this).is(':checked');
        $('.meeting-category-checkbox').prop('checked', isChecked);
    });
    
    // Update "Select All" checkboxes when individual categories are changed
    $(document).on('change', '.news-category-checkbox', function() {
        var $allNewsCheckboxes = $('.news-category-checkbox');
        var $checkedNewsCheckboxes = $('.news-category-checkbox:checked');
        var $selectAllNews = $('#select-all-news-admin, #select-all-news-admin-edit');
        
        if ($checkedNewsCheckboxes.length === 0) {
            $selectAllNews.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedNewsCheckboxes.length === $allNewsCheckboxes.length) {
            $selectAllNews.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllNews.prop('indeterminate', true);
        }
    });
    
    $(document).on('change', '.meeting-category-checkbox', function() {
        var $allMeetingCheckboxes = $('.meeting-category-checkbox');
        var $checkedMeetingCheckboxes = $('.meeting-category-checkbox:checked');
        var $selectAllMeetings = $('#select-all-meetings-admin, #select-all-meetings-admin-edit');
        
        if ($checkedMeetingCheckboxes.length === 0) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedMeetingCheckboxes.length === $allMeetingCheckboxes.length) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllMeetings.prop('indeterminate', true);
        }
    });
    
    // Initialize "Select All" state on page load for edit forms
    function initializeSelectAllState() {
        // News categories
        var $allNewsCheckboxes = $('.news-category-checkbox');
        var $checkedNewsCheckboxes = $('.news-category-checkbox:checked');
        var $selectAllNews = $('#select-all-news-admin, #select-all-news-admin-edit');
        
        if ($checkedNewsCheckboxes.length === 0) {
            $selectAllNews.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedNewsCheckboxes.length === $allNewsCheckboxes.length) {
            $selectAllNews.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllNews.prop('indeterminate', true);
        }
        
        // Meeting categories
        var $allMeetingCheckboxes = $('.meeting-category-checkbox');
        var $checkedMeetingCheckboxes = $('.meeting-category-checkbox:checked');
        var $selectAllMeetings = $('#select-all-meetings-admin, #select-all-meetings-admin-edit');
        
        if ($checkedMeetingCheckboxes.length === 0) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedMeetingCheckboxes.length === $allMeetingCheckboxes.length) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllMeetings.prop('indeterminate', true);
        }
    }
    
    // Initialize on page load
    initializeSelectAllState();
});

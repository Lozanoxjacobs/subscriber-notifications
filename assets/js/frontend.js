jQuery(document).ready(function($) {
    'use strict';
    
    // Handle subscription form submission
    $('#subscriber-notifications-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $message = $('#subscriber-message');
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Subscribing...');
        $message.hide();
        
        // Prepare form data
        var formData = $form.serialize();
        formData += '&action=subscriber_notifications_subscribe';
        
        // AJAX request
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data).show();
                    $form[0].reset();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data).show();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                $button.prop('disabled', false).text('Subscribe');
            }
        });
    });
    
    // Form validation
    $('#subscriber-notifications-form').on('input change', function() {
        var $form = $(this);
        var $name = $form.find('#subscriber_name');
        var $email = $form.find('#subscriber_email');
        var $frequency = $form.find('input[name="frequency"]:checked');
        var $newsCategories = $form.find('input[name="news_categories[]"]:checked');
        var $meetingCategories = $form.find('input[name="meeting_categories[]"]:checked');
        
        var isValid = true;
        var errors = [];
        
        // Validate name
        if ($name.val().trim().length < 2) {
            isValid = false;
            errors.push('Name must be at least 2 characters long.');
        }
        
        // Validate email
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test($email.val())) {
            isValid = false;
            errors.push('Please enter a valid email address.');
        }
        
        // Validate frequency selection
        if ($frequency.length === 0) {
            isValid = false;
            errors.push('Please select a frequency preference.');
        }
        
        // Validate category selection
        if ($newsCategories.length === 0 && $meetingCategories.length === 0) {
            isValid = false;
            errors.push('Please select at least one category for news or meetings.');
        }
        
        // Show/hide errors
        var $errorContainer = $form.find('.form-errors');
        if (!isValid) {
            if ($errorContainer.length === 0) {
                $errorContainer = $('<div class="form-errors"></div>');
                $form.prepend($errorContainer);
            }
            $errorContainer.html('<ul><li>' + errors.join('</li><li>') + '</li></ul>').show();
        } else {
            $errorContainer.hide();
        }
        
        // Enable/disable submit button
        $form.find('button[type="submit"]').prop('disabled', !isValid);
    });
    
    // Handle "Select All" functionality for news categories
    $(document).on('change', '#select-all-news', function() {
        var isChecked = $(this).is(':checked');
        $('.news-category-checkbox').prop('checked', isChecked);
    });
    
    // Handle "Select All" functionality for meeting categories
    $(document).on('change', '#select-all-meetings', function() {
        var isChecked = $(this).is(':checked');
        $('.meeting-category-checkbox').prop('checked', isChecked);
    });
    
    // Update "Select All" checkboxes when individual categories are changed
    $(document).on('change', '.news-category-checkbox', function() {
        var $allNewsCheckboxes = $('.news-category-checkbox');
        var $checkedNewsCheckboxes = $('.news-category-checkbox:checked');
        var $selectAllNews = $('#select-all-news');
        
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
        var $selectAllMeetings = $('#select-all-meetings');
        
        if ($checkedMeetingCheckboxes.length === 0) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedMeetingCheckboxes.length === $allMeetingCheckboxes.length) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllMeetings.prop('indeterminate', true);
        }
    });
    
    // Real-time form validation feedback
    $('#subscriber_name, #subscriber_email').on('blur', function() {
        var $field = $(this);
        var $error = $field.siblings('.field-error');
        
        if ($error.length === 0) {
            $error = $('<span class="field-error"></span>');
            $field.after($error);
        }
        
        if ($field.attr('id') === 'subscriber_name') {
            if ($field.val().trim().length < 2) {
                $error.text('Name must be at least 2 characters long.').show();
            } else {
                $error.hide();
            }
        } else if ($field.attr('id') === 'subscriber_email') {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test($field.val())) {
                $error.text('Please enter a valid email address.').show();
            } else {
                $error.hide();
            }
        }
    });
    
    // Handle preferences form submission
    $('#subscriber-preferences-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $message = $('#preferences-message');
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Updating...');
        $message.hide();
        
        // Prepare form data
        var formData = $form.serialize();
        formData += '&action=subscriber_notifications_update_preferences';
        
        // AJAX request
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data).show();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data).show();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                $button.prop('disabled', false).text('Update Preferences');
            }
        });
    });
    
    // Initialize "Select All" checkboxes on preferences form load
    // News categories
    var $allNewsCheckboxes = $('.news-category-checkbox');
    var $checkedNewsCheckboxes = $('.news-category-checkbox:checked');
    var $selectAllNews = $('#select-all-news-prefs');
    
    if ($selectAllNews.length > 0 && $allNewsCheckboxes.length > 0) {
        if ($checkedNewsCheckboxes.length === 0) {
            $selectAllNews.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedNewsCheckboxes.length === $allNewsCheckboxes.length) {
            $selectAllNews.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllNews.prop('indeterminate', true);
        }
    }
    
    // Meeting categories
    var $allMeetingCheckboxes = $('.meeting-category-checkbox');
    var $checkedMeetingCheckboxes = $('.meeting-category-checkbox:checked');
    var $selectAllMeetings = $('#select-all-meetings-prefs');
    
    if ($selectAllMeetings.length > 0 && $allMeetingCheckboxes.length > 0) {
        if ($checkedMeetingCheckboxes.length === 0) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', false);
        } else if ($checkedMeetingCheckboxes.length === $allMeetingCheckboxes.length) {
            $selectAllMeetings.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAllMeetings.prop('indeterminate', true);
        }
    }
    
    // Handle "Select All" functionality for preferences form news categories
    $(document).on('change', '#select-all-news-prefs', function() {
        var isChecked = $(this).is(':checked');
        $('.news-category-checkbox').prop('checked', isChecked);
        $(this).prop('indeterminate', false);
    });
    
    // Handle "Select All" functionality for preferences form meeting categories
    $(document).on('change', '#select-all-meetings-prefs', function() {
        var isChecked = $(this).is(':checked');
        $('.meeting-category-checkbox').prop('checked', isChecked);
        $(this).prop('indeterminate', false);
    });
    
    // Update "Select All" checkboxes when individual categories are changed in preferences form
    $(document).on('change', '.news-category-checkbox', function() {
        var $allNewsCheckboxes = $('.news-category-checkbox');
        var $checkedNewsCheckboxes = $('.news-category-checkbox:checked');
        var $selectAllNews = $('#select-all-news-prefs');
        
        if ($selectAllNews.length > 0) {
            if ($checkedNewsCheckboxes.length === 0) {
                $selectAllNews.prop('indeterminate', false).prop('checked', false);
            } else if ($checkedNewsCheckboxes.length === $allNewsCheckboxes.length) {
                $selectAllNews.prop('indeterminate', false).prop('checked', true);
            } else {
                $selectAllNews.prop('indeterminate', true);
            }
        }
    });
    
    $(document).on('change', '.meeting-category-checkbox', function() {
        var $allMeetingCheckboxes = $('.meeting-category-checkbox');
        var $checkedMeetingCheckboxes = $('.meeting-category-checkbox:checked');
        var $selectAllMeetings = $('#select-all-meetings-prefs');
        
        if ($selectAllMeetings.length > 0) {
            if ($checkedMeetingCheckboxes.length === 0) {
                $selectAllMeetings.prop('indeterminate', false).prop('checked', false);
            } else if ($checkedMeetingCheckboxes.length === $allMeetingCheckboxes.length) {
                $selectAllMeetings.prop('indeterminate', false).prop('checked', true);
            } else {
                $selectAllMeetings.prop('indeterminate', true);
            }
        }
    });
    
    // Handle unsubscribe button click
    $('#unsubscribe-button').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to unsubscribe? You will no longer receive any notifications.')) {
            return;
        }
        
        var $button = $(this);
        var $form = $('#subscriber-preferences-form');
        var token = $form.find('input[name="token"]').val();
        
        // Disable button
        $button.prop('disabled', true).text('Unsubscribing...');
        
        // AJAX request
        $.ajax({
            url: subscriberNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'subscriber_notifications_unsubscribe',
                token: token,
                unsubscribe_nonce: subscriberNotifications.unsubscribeNonce
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to home page
                    window.location.href = subscriberNotifications.homeUrl;
                } else {
                    alert(response.data);
                    $button.prop('disabled', false).text('Unsubscribe');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Unsubscribe');
            }
        });
    });
});

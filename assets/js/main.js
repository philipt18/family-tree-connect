/**
 * Family Tree Connect - Main JavaScript
 */

(function($) {
    'use strict';

    window.FTC = window.FTC || {};

    // Notifications
    FTC.notify = function(message, type) {
        type = type || 'info';
        var $container = $('.ftc-notifications');
        if (!$container.length) {
            $container = $('<div class="ftc-notifications"></div>').appendTo('body');
        }
        
        var $notification = $('<div class="ftc-notification ftc-notification-' + type + '">' + message + '</div>');
        $container.append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    };

    // AJAX Helper
    FTC.ajax = function(action, data, callback) {
        data = data || {};
        data.action = action;
        data.nonce = ftcData.nonce;
        
        return $.ajax({
            url: ftcData.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (callback) callback(null, response.data);
                } else {
                    var error = response.data && response.data.message ? response.data.message : ftcData.strings.error;
                    FTC.notify(error, 'error');
                    if (callback) callback(error);
                }
            },
            error: function(xhr, status, error) {
                FTC.notify(ftcData.strings.error, 'error');
                if (callback) callback(error);
            }
        });
    };

    // Modal
    FTC.modal = {
        open: function(content, options) {
            options = options || {};
            var $modal = $('<div class="ftc-modal"></div>');
            var $content = $('<div class="ftc-modal-content"></div>');
            
            var $header = $('<div class="ftc-modal-header"><h2>' + (options.title || '') + '</h2><button type="button" class="ftc-modal-close">&times;</button></div>');
            var $body = $('<div class="ftc-modal-body"></div>').html(content);
            var $footer = $('<div class="ftc-modal-footer"></div>');
            
            if (options.buttons) {
                options.buttons.forEach(function(btn) {
                    var $btn = $('<button type="button" class="ftc-btn ' + (btn.class || 'ftc-btn-secondary') + '">' + btn.text + '</button>');
                    if (btn.click) $btn.on('click', btn.click);
                    $footer.append($btn);
                });
            }
            
            $content.append($header, $body, $footer);
            $modal.append($content);
            $('body').append($modal);
            
            setTimeout(function() {
                $modal.addClass('active');
            }, 10);
            
            $modal.on('click', '.ftc-modal-close', function() {
                FTC.modal.close($modal);
            });
            
            $modal.on('click', function(e) {
                if ($(e.target).is('.ftc-modal')) {
                    FTC.modal.close($modal);
                }
            });
            
            return $modal;
        },
        
        close: function($modal) {
            $modal = $modal || $('.ftc-modal.active');
            $modal.removeClass('active');
            setTimeout(function() {
                $modal.remove();
            }, 300);
        }
    };

    // Search
    FTC.search = {
        init: function() {
            var self = this;
            
            $('.ftc-search-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $container = $form.closest('.ftc-search-container');
                var query = $form.find('.ftc-search-input').val();
                var treeId = $container.data('tree-id');
                
                self.search(query, treeId, $container.find('.ftc-search-results'));
            });
            
            var searchTimeout;
            $('.ftc-search-input').on('input', function() {
                var $input = $(this);
                var $container = $input.closest('.ftc-search-container');
                var $suggestions = $container.find('.ftc-search-suggestions');
                var query = $input.val();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $suggestions.hide().empty();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    self.getSuggestions(query, $suggestions);
                }, 300);
            });
            
            $(document).on('click', '.ftc-search-suggestion', function() {
                var $container = $(this).closest('.ftc-search-container');
                $container.find('.ftc-search-input').val($(this).text());
                $container.find('.ftc-search-suggestions').hide();
                $container.find('.ftc-search-form').submit();
            });
        },
        
        search: function(query, treeId, $results) {
            $results.html('<div class="ftc-loading"><span class="ftc-spinner"></span>' + ftcData.strings.loading + '</div>');
            
            $.get(ftcData.ajaxUrl, {
                action: 'ftc_search',
                q: query,
                type: 'person',
                tree_id: treeId
            }, function(response) {
                if (response.success && response.data.results.length) {
                    var html = '<ul class="ftc-search-results-list">';
                    response.data.results.forEach(function(person) {
                        html += '<li><a href="' + person.url + '">' + person.display_name;
                        if (person.birth_date || person.death_date) {
                            html += ' <span class="ftc-dates">(' + (person.birth_date || '?') + ' - ' + (person.death_date || '') + ')</span>';
                        }
                        html += '</a></li>';
                    });
                    html += '</ul>';
                    $results.html(html);
                } else {
                    $results.html('<p>' + ftcData.strings.noResults + '</p>');
                }
            });
        },
        
        getSuggestions: function(query, $suggestions) {
            $.get(ftcData.ajaxUrl, {
                action: 'ftc_suggestions',
                q: query,
                type: 'person'
            }, function(response) {
                if (response.success && response.data.suggestions.length) {
                    var html = '';
                    response.data.suggestions.forEach(function(item) {
                        html += '<div class="ftc-search-suggestion" data-id="' + item.id + '">' + item.value + '</div>';
                    });
                    $suggestions.html(html).show();
                } else {
                    $suggestions.hide();
                }
            });
        }
    };

    // Person form
    FTC.personForm = {
        init: function() {
            var self = this;
            
            $(document).on('click', '.ftc-add-person', function(e) {
                e.preventDefault();
                self.openForm();
            });
            
            $(document).on('click', '.ftc-edit-person', function(e) {
                e.preventDefault();
                var personId = $(this).data('person-id');
                self.openForm(personId);
            });
        },
        
        openForm: function(personId) {
            var self = this;
            var isEdit = !!personId;
            var title = isEdit ? 'Edit Person' : 'Add Person';
            
            var formHtml = self.getFormHtml();
            
            var $modal = FTC.modal.open(formHtml, {
                title: title,
                buttons: [
                    {
                        text: 'Cancel',
                        class: 'ftc-btn-secondary',
                        click: function() {
                            FTC.modal.close();
                        }
                    },
                    {
                        text: isEdit ? 'Save' : 'Add',
                        class: 'ftc-btn-primary',
                        click: function() {
                            self.submitForm($modal, personId);
                        }
                    }
                ]
            });
            
            if (isEdit) {
                self.loadPerson(personId, $modal);
            }
        },
        
        getFormHtml: function() {
            return `
                <form class="ftc-person-form">
                    <div class="ftc-form-row">
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">First Name</label>
                            <input type="text" name="first_name" class="ftc-form-input">
                        </div>
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="ftc-form-input">
                        </div>
                    </div>
                    <div class="ftc-form-row">
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Surname</label>
                            <input type="text" name="surname" class="ftc-form-input">
                        </div>
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Maiden Name</label>
                            <input type="text" name="maiden_name" class="ftc-form-input">
                        </div>
                    </div>
                    <div class="ftc-form-row">
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Nickname</label>
                            <input type="text" name="nickname" class="ftc-form-input">
                        </div>
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Gender</label>
                            <select name="gender" class="ftc-form-select">
                                <option value="unknown">Unknown</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="ftc-form-row">
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Birth Date</label>
                            <input type="text" name="birth_date" class="ftc-form-input" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Birth Location</label>
                            <input type="text" name="birth_location" class="ftc-form-input">
                        </div>
                    </div>
                    <div class="ftc-form-row">
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Death Date</label>
                            <input type="text" name="death_date" class="ftc-form-input" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="ftc-form-group">
                            <label class="ftc-form-label">Death Location</label>
                            <input type="text" name="death_location" class="ftc-form-input">
                        </div>
                    </div>
                    <div class="ftc-form-group">
                        <label class="ftc-form-label">Occupation</label>
                        <input type="text" name="occupation" class="ftc-form-input">
                    </div>
                    <div class="ftc-form-group">
                        <label class="ftc-form-label">Biography</label>
                        <textarea name="biography" class="ftc-form-textarea" rows="3"></textarea>
                    </div>
                </form>
            `;
        },
        
        loadPerson: function(personId, $modal) {
            FTC.ajax('ftc_get_person', { person_id: personId }, function(err, data) {
                if (!err && data.person) {
                    var person = data.person;
                    var $form = $modal.find('.ftc-person-form');
                    
                    Object.keys(person).forEach(function(key) {
                        var $field = $form.find('[name="' + key + '"]');
                        if ($field.length && person[key]) {
                            $field.val(person[key]);
                        }
                    });
                }
            });
        },
        
        submitForm: function($modal, personId) {
            var $form = $modal.find('.ftc-person-form');
            var data = {};
            
            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                if (name) {
                    data[name] = $field.val();
                }
            });
            
            var action = personId ? 'ftc_update_person' : 'ftc_create_person';
            var ajaxData = { person: data };
            if (personId) ajaxData.person_id = personId;
            
            FTC.ajax(action, ajaxData, function(err, response) {
                if (!err) {
                    FTC.notify(ftcData.strings.saved, 'success');
                    FTC.modal.close($modal);
                    location.reload();
                }
            });
        }
    };

    // Initialize
    $(document).ready(function() {
        FTC.search.init();
        FTC.personForm.init();
        
        // Mark notifications as read
        $(document).on('click', '.ftc-notification-item:not(.read)', function() {
            var $item = $(this);
            var notificationId = $item.data('id');
            
            FTC.ajax('ftc_mark_notification_read', { notification_id: notificationId });
            $item.addClass('read');
        });
        
        // Confirm delete
        $(document).on('click', '[data-confirm]', function(e) {
            var message = $(this).data('confirm') || ftcData.strings.confirmDelete;
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

})(jQuery);

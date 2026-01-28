/**
 * Family Tree Connect - Media JavaScript
 */

(function($) {
    'use strict';

    FTC.media = {
        cropper: null,
        
        init: function() {
            var self = this;
            
            $(document).on('click', '.ftc-upload-media', function(e) {
                e.preventDefault();
                var $input = $('<input type="file" accept="image/*,application/pdf" multiple>');
                var personId = $(this).data('person-id');
                var eventId = $(this).data('event-id');
                
                $input.on('change', function() {
                    self.uploadFiles(this.files, personId, eventId);
                });
                $input.click();
            });
            
            $(document).on('click', '.ftc-crop-photo', function(e) {
                e.preventDefault();
                self.openCropper($(this).data('media-id'), $(this).data('person-id'), $(this).data('image-url'));
            });
            
            $(document).on('click', '.ftc-set-default-photo', function(e) {
                e.preventDefault();
                self.setDefaultPhoto($(this).data('media-id'), $(this).data('person-id'));
            });
            
            $(document).on('click', '.ftc-media-item img', function() {
                self.openLightbox($(this).attr('src'), $(this).closest('.ftc-media-item').data('title'));
            });
        },
        
        uploadFiles: function(files, personId, eventId) {
            Array.from(files).forEach(function(file) {
                var formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'ftc_upload_media');
                formData.append('nonce', ftcData.nonce);
                if (personId) formData.append('person_ids[]', personId);
                if (eventId) formData.append('event_id', eventId);
                
                $.ajax({
                    url: ftcData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            FTC.notify('File uploaded', 'success');
                            location.reload();
                        } else {
                            FTC.notify(response.data.message || 'Error', 'error');
                        }
                    }
                });
            });
        },
        
        openCropper: function(mediaId, personId, imageUrl) {
            var self = this;
            var $modal = FTC.modal.open('<div class="ftc-cropper-container"><img src="' + imageUrl + '" id="ftc-crop-image"></div>', {
                title: 'Crop Photo',
                buttons: [
                    { text: 'Cancel', class: 'ftc-btn-secondary', click: function() { if (self.cropper) self.cropper.destroy(); FTC.modal.close(); }},
                    { text: 'Save', class: 'ftc-btn-primary', click: function() { self.saveCrop(mediaId, personId); }}
                ]
            });
            
            setTimeout(function() {
                var img = document.getElementById('ftc-crop-image');
                if (img && typeof Cropper !== 'undefined') {
                    self.cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1 });
                }
            }, 100);
        },
        
        saveCrop: function(mediaId, personId) {
            var self = this;
            if (!self.cropper) return;
            
            var data = self.cropper.getData(true);
            FTC.ajax('ftc_save_crop', {
                media_id: mediaId,
                person_id: personId,
                crop_x: Math.round(data.x),
                crop_y: Math.round(data.y),
                crop_width: Math.round(data.width),
                crop_height: Math.round(data.height),
                is_primary: true
            }, function(err) {
                if (!err) {
                    self.cropper.destroy();
                    FTC.modal.close();
                    location.reload();
                }
            });
        },
        
        setDefaultPhoto: function(mediaId, personId) {
            FTC.ajax('ftc_set_default_photo', { media_id: mediaId, person_id: personId }, function(err) {
                if (!err) location.reload();
            });
        },
        
        openLightbox: function(url, title) {
            var $lb = $('<div class="ftc-lightbox"><div class="ftc-lightbox-content"><button class="ftc-lightbox-close">&times;</button><img src="' + url + '"></div></div>');
            $('body').append($lb);
            setTimeout(function() { $lb.addClass('active'); }, 10);
            
            $lb.on('click', function(e) {
                if ($(e.target).is('.ftc-lightbox,.ftc-lightbox-close')) {
                    $lb.removeClass('active');
                    setTimeout(function() { $lb.remove(); }, 300);
                }
            });
        }
    };

    $(document).ready(function() { FTC.media.init(); });
})(jQuery);

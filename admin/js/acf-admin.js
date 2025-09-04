/**
 * ACF Admin Support JavaScript
 * Handles ACF field interactions on the admin archive pages
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initializeAcfAdmin();
    });
    
    function initializeAcfAdmin() {
        // Handle image field selection
        $(document).on('click', '.acf-image-select', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var field = button.closest('.acf-input');
            var input = field.find('.acf-image-field');
            var preview = field.find('.acf-image-preview');
            var removeBtn = field.find('.acf-image-remove');
            
            // WordPress media uploader
            var mediaUploader = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                
                input.val(attachment.id);
                
                // Update preview
                if (attachment.sizes && attachment.sizes.medium) {
                    preview.html('<img src="' + attachment.sizes.medium.url + '" alt="' + attachment.alt + '">');
                } else {
                    preview.html('<img src="' + attachment.url + '" alt="' + attachment.alt + '" style="max-width: 300px;">');
                }
                
                removeBtn.show();
            });
            
            mediaUploader.open();
        });
        
        // Handle image removal
        $(document).on('click', '.acf-image-remove', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var field = button.closest('.acf-input');
            var input = field.find('.acf-image-field');
            var preview = field.find('.acf-image-preview');
            
            input.val('');
            preview.empty();
            button.hide();
        });
        
        // Handle form submission with ACF data
        $('.silo-archive-form').on('submit', function(e) {
            var form = $(this);
            
            // Collect ACF data from all sections
            var acfData = {};
            
            $('.acf-archive-section').each(function() {
                var section = $(this);
                var archiveType = section.data('archive-type');
                
                // Collect all ACF fields in this section
                section.find('input, textarea, select').each(function() {
                    var field = $(this);
                    var name = field.attr('name');
                    
                    if (name && name.indexOf('acf[') === 0) {
                        acfData[name] = field.val();
                    }
                });
                
                // Handle WYSIWYG editors
                section.find('.wp-editor-area').each(function() {
                    var editor = $(this);
                    var id = editor.attr('id');
                    var name = editor.closest('.wp-editor-wrap').find('textarea').attr('name');
                    
                    if (name && name.indexOf('acf[') === 0) {
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(id)) {
                            acfData[name] = tinyMCE.get(id).getContent();
                        } else {
                            acfData[name] = editor.val();
                        }
                    }
                });
            });
            
            // Add ACF data to form
            Object.keys(acfData).forEach(function(key) {
                if (!form.find('input[name="' + key + '"]').length) {
                    form.append('<input type="hidden" name="' + key + '" value="' + acfData[key] + '">');
                }
            });
        });
        
        // Initialize ACF scripts if available
        if (typeof acf !== 'undefined') {
            // Re-initialize ACF for dynamically loaded content
            $(document).on('acf-fields-loaded', function() {
                acf.do_action('ready');
            });
        }
    }
    
    // Function to save ACF data via AJAX
    function saveAcfData(archiveType, termId, acfData) {
        return $.ajax({
            url: siloAcfAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_acf_archive_fields',
                archive_type: archiveType,
                term_id: termId,
                acf: acfData,
                nonce: siloAcfAdmin.nonce
            }
        });
    }
    
    // Export functions for global use
    window.siloAcfAdmin = window.siloAcfAdmin || {};
    window.siloAcfAdmin.saveAcfData = saveAcfData;
    
})(jQuery);
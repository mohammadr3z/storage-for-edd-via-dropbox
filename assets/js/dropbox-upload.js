jQuery(function ($) {
    // File size validation
    $(document).on('change', 'input[name="dbxe_file"]', function () {
        if (this.files && this.files[0]) {
            var fileSize = this.files[0].size;
            var maxSize = dbxe_max_upload_size;
            if (fileSize > maxSize) {
                alert(dbxe_i18n.file_size_too_large + ' ' + (maxSize / 1024 / 1024).toFixed(2) + 'MB');
                this.value = '';
            }
        }
    });

    // Helper to show notice
    function showUploadError(message) {
        $('.dbxe-notice').remove();
        var errorHtml = '<div class="dbxe-notice warning"><p>' + message + '</p></div>';
        var $uploadSection = $('#dbxe-upload-section');
        if ($uploadSection.length && $uploadSection.is(':visible')) {
            $uploadSection.prepend(errorHtml);
        } else {
            // Fallback
            $('#dbxe-modal-container').prepend(errorHtml);
        }
    }

    // Handle Upload Form Submission
    $(document).on('submit', '.dbxe-upload-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('input[type="submit"]');
        var $fileInput = $form.find('input[name="dbxe_file"]');
        var file = $fileInput[0].files[0];

        if (!file) {
            showUploadError(dbxe_i18n.file_selected_error || 'Please select a file.');
            return;
        }

        // Prepare FormData
        var formData = new FormData();
        formData.append('action', 'dbxe_ajax_upload');
        formData.append('dbxe_file', file);
        formData.append('dbxe_nonce', $form.find('input[name="dbxe_nonce"]').val());
        // Path input is updated by media library JS on navigation
        formData.append('dbxe_path', $form.find('input[name="dbxe_path"]').val());

        $btn.prop('disabled', true).val('Uploading...');

        // Remove previous notices
        $('.dbxe-notice').remove();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Refresh library
                    if (window.DBXEMediaLibrary) {
                        // Reload current path (which is what we uploaded to)
                        var currentPath = $form.find('input[name="dbxe_path"]').val();

                        // Wait for content to be loaded before showing notice
                        $(document).one('dbxe_content_loaded', function () {
                            // Create success notice HTML
                            var filename = response.data.filename;
                            var path = response.data.path;

                            // Use explicit link if provided, otherwise parse path
                            if (response.data.dbxe_link) {
                                path = response.data.dbxe_link;
                            } else if (path.charAt(0) === '/') {
                                path = path.substring(1);
                            }

                            var successHtml =
                                '<div class="dbxe-notice success">' +
                                '<h4>' + (response.data.message || 'Upload Successful') + '</h4>' +
                                '<p>File <strong>' + filename + '</strong> uploaded successfully.</p>' +
                                '<p>' +
                                '<button type="button" class="button button-primary save-dbxe-file" ' +
                                'data-dbxe-filename="' + filename + '" ' +
                                'data-dbxe-link="' + path + '">' +
                                'Use this file' +
                                '</button>' +
                                '</p>' +
                                '</div>';

                            // Inject notice after the upload section (or before table if upload section hidden)
                            var $uploadSection = $('#dbxe-upload-section');
                            if ($uploadSection.length) {
                                $uploadSection.after(successHtml);
                            } else {
                                // Fallback: prepend to container
                                $('#dbxe-modal-container').prepend(successHtml);
                            }
                        });

                        window.DBXEMediaLibrary.load(currentPath);
                    }

                    // Reset form
                    $fileInput.val('');
                    // Remove existing notices
                    $('.dbxe-notice, .dbxe-no-search-results').remove();
                } else {
                    var errorMsg = 'Unknown error';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (typeof response.data === 'object') {
                            if (response.data.message) {
                                errorMsg = response.data.message;
                            } else if (Array.isArray(response.data) && response.data.length > 0) {
                                errorMsg = response.data[0];
                            } else {
                                var values = Object.values(response.data);
                                if (values.length > 0) {
                                    errorMsg = values.join(', ');
                                }
                            }
                        }
                    }
                    showUploadError('Upload Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                var errorDetails = '';
                if (xhr.status) {
                    errorDetails += ' (Status: ' + xhr.status + ')';
                }
                if (xhr.responseText) {
                    var text = xhr.responseText.substring(0, 100);
                    errorDetails += '<br>Response: ' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                }
                showUploadError('Connection error during upload.' + errorDetails);
            },
            complete: function () {
                $btn.prop('disabled', false).val('Upload');
            }
        });
    });
});

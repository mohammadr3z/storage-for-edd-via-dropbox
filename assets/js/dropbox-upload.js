/**
 * Dropbox Upload Tab JavaScript
 * Matching S3 plugin pattern
 */
jQuery(function ($) {
    // Handler for "Use this file in your Download" button after upload
    $('#dbxe_save_link').click(function () {
        $(parent.window.edd_filename).val($(this).data('dbxe-fn'));
        $(parent.window.edd_fileurl).val(dbxe_url_prefix + $(this).data('dbxe-path'));
        parent.window.tb_remove();
    });

    // File size validation before upload
    $('input[name="dbxe_file"]').on('change', function () {
        var fileSize = this.files[0].size;
        var maxSize = dbxe_max_upload_size;
        if (fileSize > maxSize) {
            alert(dbxe_i18n.file_size_too_large + ' ' + (maxSize / 1024 / 1024) + 'MB');
            this.value = '';
        }
    });
});

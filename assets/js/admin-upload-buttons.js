/**
 * Admin Upload Buttons Handler for Dropbox
 * 
 * Extends EDD's default upload button behavior to work with Dropbox.
 * This sets up the edd_filename and edd_fileurl variables when
 * the upload button is clicked, so the Dropbox library can populate them.
 */
jQuery(function ($) {
    // When EDD upload button is clicked, store references to the input fields
    $('body').on('click', '.edd_upload_file_button', function () {
        window.edd_fileurl = $(this).parent().prev().find('input');
        window.edd_filename = $(this).parent().parent().parent().prev().find('input');
    });

    // Handler for "Use this file in your Download" button after upload
    $('#dbxe_save_link').click(function () {
        if (window.edd_filename && window.edd_fileurl) {
            $(window.edd_filename).val($(this).data('dbxe-fn'));
            $(window.edd_fileurl).val(dbxe_url_prefix + $(this).data('dbxe-path'));
            try { parent.window.tb_remove(); } catch (e) { window.tb_remove(); }
        }
        return false;
    });
});

/**
 * Dropbox Media Library JavaScript
 */
jQuery(function ($) {
    // File selection handler
    $('.save-dbxe-file').click(function () {
        var filename = $(this).data('dbxe-filename');
        var fileurl = dbxe_url_prefix + $(this).data('dbxe-link');
        var success = false;

        // Try new modal method (Browse button sets these)
        if (parent.window && parent.window !== window) {
            // New modal method: use specific row inputs
            if (parent.window.dbxe_current_name_input && parent.window.dbxe_current_url_input) {
                $(parent.window.dbxe_current_name_input).val(filename);
                $(parent.window.dbxe_current_url_input).val(fileurl);
                success = true;
                // Close new modal
                if (parent.DBXEModal) {
                    parent.DBXEModal.close();
                }
            }
        }

        if (!success) {
            alert(dbxe_i18n.file_selected_error);
        }

        return false;
    });

    // Search functionality for Dropbox files
    $('#dbxe-file-search').on('input search', function () {
        var searchTerm = $(this).val().toLowerCase();
        var $fileRows = $('.dbxe-files-table tbody tr');
        var visibleCount = 0;

        $fileRows.each(function () {
            var $row = $(this);
            var fileName = $row.find('.file-name').text().toLowerCase();

            if (fileName.indexOf(searchTerm) !== -1) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        // Show/hide "no results" message
        var $noResults = $('.dbxe-no-search-results');
        if (visibleCount === 0 && searchTerm.length > 0) {
            if ($noResults.length === 0) {
                $('.dbxe-files-table').after('<div class="dbxe-no-search-results" style="padding: 20px; text-align: center; color: #666; font-style: italic;">No files found matching your search.</div>');
            } else {
                $noResults.show();
            }
        } else {
            $noResults.hide();
        }
    });


    // Keyboard shortcut for search
    $(document).keydown(function (e) {
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#dbxe-file-search').focus();
        }
    });

    // Toggle upload form
    $('#dbxe-toggle-upload').click(function () {
        $('#dbxe-upload-section').slideToggle(200);
    });
});

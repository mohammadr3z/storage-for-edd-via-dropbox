/**
 * DBXE Modal JS
 */
var DBXEModal = (function ($) {
    var $modal, $overlay, $container, $closeBtn, $skeleton;

    // Skeleton rows - shared with DBXEMediaLibrary
    var skeletonRowsHtml =
        '<tr><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 60%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 80%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="dbxe-skeleton-cell" style="width: 55%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 50%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 75%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="dbxe-skeleton-cell" style="width: 80%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 45%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="dbxe-skeleton-cell" style="width: 65%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 55%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 85%;"></div></td><td><div class="dbxe-skeleton-cell" style="width: 70%;"></div></td></tr>';

    function init() {
        if ($('#dbxe-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure - uses real table with skeleton rows
        var skeletonHtml =
            '<div class="dbxe-skeleton-loader">' +
            '<div class="dbxe-header-row">' +
            '<h3 class="media-title">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_select_file || 'Select a file from Dropbox') + '</h3>' +
            '<div class="dbxe-header-buttons">' +
            '<button type="button" class="button button-primary" id="dbxe-toggle-upload">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_upload || 'Upload File') + '</button>' +
            '</div>' +
            '</div>' +
            '<div class="dbxe-breadcrumb-nav dbxe-skeleton-breadcrumb">' +
            '<div class="dbxe-nav-group">' +
            '<span class="dbxe-nav-back disabled"><span class="dashicons dashicons-arrow-left-alt2"></span></span>' +
            '<div class="dbxe-breadcrumbs"><div class="dbxe-skeleton-cell" style="width: 120px; height: 18px;"></div></div>' +
            '</div>' +
            '<div class="dbxe-search-inline"><input type="search" class="dbxe-search-input" placeholder="' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_search || 'Search files...') + '" disabled></div>' +
            '</div>' +
            '<table class="wp-list-table widefat fixed dbxe-files-table">' +
            '<thead><tr>' +
            '<th class="column-primary" style="width: 40%;">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_file_name || 'File Name') + '</th>' +
            '<th class="column-size" style="width: 20%;">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_file_size || 'File Size') + '</th>' +
            '<th class="column-date" style="width: 25%;">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_last_modified || 'Last Modified') + '</th>' +
            '<th class="column-actions" style="width: 15%;">' + (typeof dbxe_browse_button !== 'undefined' && dbxe_browse_button.i18n_actions || 'Actions') + '</th>' +
            '</tr></thead>' +
            '<tbody>' + skeletonRowsHtml + '</tbody></table>' +
            '</div>';

        // Create DOM structure with skeleton
        var html =
            '<div id="dbxe-modal-overlay" class="dbxe-modal-overlay">' +
            '<div class="dbxe-modal">' +
            '<div class="dbxe-modal-header">' +
            '<h1 class="dbxe-modal-title"></h1>' +
            '<button type="button" class="dbxe-modal-close">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>' +
            '</div>' +
            '<div class="dbxe-modal-content">' +
            skeletonHtml +
            '<div id="dbxe-modal-container" class="dbxe-modal-container hidden"></div>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#dbxe-modal-overlay');
        $modal = $overlay.find('.dbxe-modal');
        $container = $overlay.find('#dbxe-modal-container');
        $title = $overlay.find('.dbxe-modal-title');
        $closeBtn = $overlay.find('.dbxe-modal-close');
        $skeleton = $overlay.find('.dbxe-skeleton-loader');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) {
                close();
            }
        });

        // Global event for content loaded
        $(document).on('dbxe_content_loaded', function () {
            $skeleton.addClass('hidden');
            $container.removeClass('hidden');
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide container
        $skeleton.removeClass('hidden');
        $container.addClass('hidden');

        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');

        // Trigger library load
        if (window.DBXEMediaLibrary) {
            window.DBXEMediaLibrary.load(url || '');
        }
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $container.empty().addClass('hidden');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close,
        getSkeletonRows: function () { return skeletonRowsHtml; }
    };

})(jQuery);

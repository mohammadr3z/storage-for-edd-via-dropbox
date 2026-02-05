/**
 * DBXE Modal JS
 */
var DBXEModal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn, $skeleton;

    function init() {
        if ($('#dbxe-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure
        var skeletonHtml =
            '<div class="dbxe-skeleton-loader">' +
            '<div class="dbxe-skeleton-header">' +
            '<div class="dbxe-skeleton-title"></div>' +
            '<div class="dbxe-skeleton-button"></div>' +
            '</div>' +
            '<div class="dbxe-skeleton-breadcrumb">' +
            '<div class="dbxe-skeleton-back-btn"></div>' +
            '<div class="dbxe-skeleton-path"></div>' +
            '<div class="dbxe-skeleton-search"></div>' +
            '</div>' +
            '<div class="dbxe-skeleton-table">' +
            '<div class="dbxe-skeleton-thead">' +
            '<div class="dbxe-skeleton-row">' +
            '<div class="dbxe-skeleton-cell name"></div>' +
            '<div class="dbxe-skeleton-cell size"></div>' +
            '<div class="dbxe-skeleton-cell date"></div>' +
            '<div class="dbxe-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
            '<div class="dbxe-skeleton-row">' +
            '<div class="dbxe-skeleton-cell name"></div>' +
            '<div class="dbxe-skeleton-cell size"></div>' +
            '<div class="dbxe-skeleton-cell date"></div>' +
            '<div class="dbxe-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
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
            '<iframe class="dbxe-modal-frame loading" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#dbxe-modal-overlay');
        $modal = $overlay.find('.dbxe-modal');
        $iframe = $overlay.find('.dbxe-modal-frame');
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

        // Handle iframe load event
        $iframe.on('load', function () {
            $skeleton.addClass('hidden');
            $iframe.removeClass('loading').addClass('loaded');
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide iframe
        $skeleton.removeClass('hidden');
        $iframe.removeClass('loaded').addClass('loading');

        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', '');
            $iframe.removeClass('loaded').addClass('loading');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);

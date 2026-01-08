/**
 * DBXE Modal JS
 */
var DBXEModal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn;

    function init() {
        if ($('#dbxe-modal-overlay').length) {
            return;
        }

        // Create DOM structure
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
            '<iframe class="dbxe-modal-frame" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#dbxe-modal-overlay');
        $modal = $overlay.find('.dbxe-modal');
        $iframe = $overlay.find('.dbxe-modal-frame');
        $title = $overlay.find('.dbxe-modal-title');
        $closeBtn = $overlay.find('.dbxe-modal-close');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) { // ESC
                close();
            }
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');
        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden'); // Prevent body scroll
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', ''); // Stop loading
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);

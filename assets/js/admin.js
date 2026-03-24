/**
 * Mega Menu Ajax - Admin JavaScript
 */
(function ($) {
    'use strict';

    var MegaMenuAjaxAdmin = {
        init: function () {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('change', '.mega-menu-ajax-location-enabled', function () {
                var $checkbox = $(this);
                var $card = $checkbox.closest('.mma-location-card');
                var $fields = $card.find('.mma-location-field');

                if ($checkbox.is(':checked')) {
                    $fields.prop('disabled', false);
                } else {
                    $fields.prop('disabled', true);
                }
            });

            $('.mega-menu-ajax-location-enabled').trigger('change');

            $('#mma-clear-cache-btn').on('click', function () {
                self.clearCache($(this));
            });
        },

        clearCache: function ($btn) {
            if ($btn.prop('disabled')) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.text('Clearing...');

            var $status = $('#mma-clear-cache-status');

            $.ajax({
                url: megaMenuAjaxAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mega_menu_ajax_clear_cache',
                    nonce: megaMenuAjaxAdmin.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $status
                            .removeClass('mma-cache-error')
                            .addClass('mma-cache-success')
                            .text(response.data.message)
                            .show();
                        $btn.text('Cleared!');
                    } else {
                        $status
                            .removeClass('mma-cache-success')
                            .addClass('mma-cache-error')
                            .text(response.data.message || 'Error.')
                            .show();
                        $btn.prop('disabled', false);
                        $btn.html(
                            '<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px; margin-right: 3px;"></span>Clear Cache'
                        );
                    }
                    setTimeout(function () {
                        $status.fadeOut(300);
                        $btn.prop('disabled', false);
                        $btn.html(
                            '<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px; margin-right: 3px;"></span>Clear Cache'
                        );
                    }, 3000);
                },
                error: function () {
                    $status
                        .removeClass('mma-cache-success')
                        .addClass('mma-cache-error')
                        .text('Request failed.')
                        .show();
                    $btn.prop('disabled', false);
                    $btn.html(
                        '<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px; margin-right: 3px;"></span>Clear Cache'
                    );
                    setTimeout(function () {
                        $status.fadeOut(300);
                    }, 3000);
                }
            });
        },

        initSortable: function () {
            if ($.fn.sortable) {
                $('.mega-menu-ajax-sortable').sortable({
                    handle: '.mega-menu-ajax-drag-handle',
                    placeholder: 'mega-menu-ajax-sortable-placeholder',
                    update: function (event, ui) {
                    }
                });
            }
        }
    };

    $(document).ready(function () {
        MegaMenuAjaxAdmin.init();
    });

})(jQuery);

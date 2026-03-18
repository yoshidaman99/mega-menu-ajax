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

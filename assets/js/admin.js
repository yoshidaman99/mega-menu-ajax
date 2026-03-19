/**
 * Mega Menu Ajax - Admin JavaScript
 */
(function ($) {
    'use strict';

    var MegaMenuAjaxAdmin = {
        init: function () {
            this.bindEvents();
            this.initSortable();
            this.initTabs();
            this.initClearCache();
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
        },

        initTabs: function () {
            $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.mega-menu-ajax-tab').removeClass('active');
                $(target + '-tab').addClass('active');
            });
        },

        initClearCache: function () {
            $('#mega-menu-ajax-clear-performance-cache').on('click', function (e) {
                e.preventDefault();
                
                var $button = $(this);
                var $status = $('#mega-menu-ajax-cache-clear-status');
                var nonce = $button.data('nonce');
                
                $button.prop('disabled', true);
                $status.removeClass('success error').text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mega_menu_ajax_clear_performance_cache',
                        nonce: nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $status.addClass('success').text(response.data.message);
                        } else {
                            $status.addClass('error').text(response.data.message || 'Error clearing cache');
                        }
                    },
                    error: function () {
                        $status.addClass('error').text('Error clearing cache');
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                        setTimeout(function () {
                            $status.fadeOut(function () {
                                $status.text('').removeClass('success error').show();
                            });
                        }, 3000);
                    }
                });
            });
        }
    };

    $(document).ready(function () {
        MegaMenuAjaxAdmin.init();
    });

})(jQuery);

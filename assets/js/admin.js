(function($) {
    'use strict';

    const SenseiModuleProducts = {
        init: function() {
            this.initSelect2();
            this.bindEvents();
        },

        initSelect2: function() {
            $('#module_product').select2({
                width: '100%',
                placeholder: SenseiModuleProductsL10n.selectPlaceholder,
                allowClear: true
            });
        },

        bindEvents: function() {
            $('#module_product').on('change', this.handleProductChange);
        },

        handleProductChange: function(e) {
            const $select = $(this);
            const $description = $select.siblings('.description');
            
            if ($select.val()) {
                $description.show();
            } else {
                $description.hide();
            }
        }
    };

    $(document).ready(function() {
        SenseiModuleProducts.init();
    });

})(jQuery);
/* global woocommerce_settings_params, wp */
jQuery(function ($) {
    'use strict';
    const wc_novatum_admin = {
        isTestMode: function () {
            return $('#woocommerce_novatum_testmode').is(":checked")
        }, init: function () {
            $(document.body).on('change', '#woocommerce_novatum_testmode', function () {
                const test_api_merchant_id = $('#woocommerce_novatum_sandbox_merchant_id').parents('tr').eq(0),
                    test_api_merchant_account_id = $('#woocommerce_novatum_sandbox_merchant_account_id').parents('tr').eq(0),
                    test_api_merchant_account_key = $('#woocommerce_novatum_sandbox_merchant_account_key').parents('tr').eq(0),
                    test_api_merchant_account_user_name = $('#woocommerce_novatum_sandbox_merchant_account_user_name').parents('tr').eq(0),
                    test_api_merchant_account_password = $('#woocommerce_novatum_sandbox_merchant_account_password').parents('tr').eq(0),

                    api_merchant_id = $('#woocommerce_novatum_merchant_id').parents('tr').eq(0),
                    api_merchant_account_id = $('#woocommerce_novatum_merchant_account_id').parents('tr').eq(0),
                    api_merchant_account_key = $('#woocommerce_novatum_merchant_account_key').parents('tr').eq(0),
                    api_merchant_account_user_name = $('#woocommerce_novatum_merchant_account_user_name').parents('tr').eq(0),
                    api_merchant_account_password = $('#woocommerce_novatum_merchant_account_password').parents('tr').eq(0);

                if ($(this).is(':checked')) {
                    test_api_merchant_id.show();
                    test_api_merchant_account_id.show();
                    test_api_merchant_account_key.show();
                    test_api_merchant_account_user_name.show();
                    test_api_merchant_account_password.show();
                    api_merchant_id.hide();
                    api_merchant_account_id.hide();
                    api_merchant_account_key.hide();
                    api_merchant_account_user_name.hide();
                    api_merchant_account_password.hide();
                } else {
                    test_api_merchant_id.hide();
                    test_api_merchant_account_id.hide();
                    test_api_merchant_account_key.hide();
                    test_api_merchant_account_user_name.hide();
                    test_api_merchant_account_password.hide();
                    api_merchant_id.show();
                    api_merchant_account_id.show();
                    api_merchant_account_key.show();
                    api_merchant_account_user_name.show();
                    api_merchant_account_password.show();
                }
            });
            $('#woocommerce_novatum_testmode').change()
        }
    };
    wc_novatum_admin.init()
})

function formatCardNumber(ccnum) {
    let newval = '';
    const val = ccnum.replace(/\s/g, '');
    for (let i = 0; i < val.length; i++) {
        if (i % 4 === 0 && i > 0) newval = newval.concat(' ');
        newval = newval.concat(val[i]);
    }
    jQuery("#novatum_ccNo").val(newval);
}

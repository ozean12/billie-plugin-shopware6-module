;(function ($, window) {
    $.plugin('BilliePayment', {

        defaults: {
            formSelector: '#confirm--form',
            checkoutSessionId: null,
            merchantName: null,
            validateAddressUrl: null,
            defaultErrorMessage: null
        },
        $form: null,
        $submitButton: null,

        init: function () {
            var me = this;
            me.applyDataAttributes();

            me.$form = $($.find(me.opts.formSelector));
            me.$submitButton = $('button[type=submit][form="' + me.$form.prop('id') + '"]');

            me._on(me.$form, 'submit', $.proxy(me.submitForm, me));
        },

        setAddressConfirmed: function (flag) {
            var me = this;
            me.$form.data('addressConfirmed', flag ? 1 : 0)
        },
        isAddressConfirmed: function () {
            var me = this;
            return me.$form.data('addressConfirmed') == 1;
        },
        unlockSubmitButton: function () {
            var me = this;
            me.$submitButton.data('plugin_swPreloaderButton').reset();
        },

        submitForm: function (event) {
            var me = this;
            if (me.isAddressConfirmed()) {
                //address has been already confirmed and validated. So we will process the order.
                return true;
            } else {
                // address has not been confirmed and must be validated
                event.preventDefault();

                BillieCheckoutWidget.mount({
                    billie_config_data: {
                        'session_id': me.opts.checkoutSessionId,
                        'merchant_name': me.opts.merchantName
                    },
                    billie_order_data: window.billiePaymentData
                }).then(function success(data) {
                    jQuery.ajax({
                        type: "POST",
                        url: me.opts.validateAddressUrl,
                        data: data,
                        dataType: 'json',
                        success: function (response) {
                            if(response.status) {
                                me.setAddressConfirmed(true);
                                me.$form.submit();
                            } else {
                                if(response.redirect) {
                                    window.location.href = response.redirect;
                                } else {
                                    alert(me.opts.defaultErrorMessage);
                                    me.unlockSubmitButton();
                                }
                            }
                        },
                    });
                }).catch(function failure(err) {
                    // code to execute when there is an error or when order is rejected
                    if(err.state !== 'declined') {
                        //we assume, that the error popup of billie will be displayed, when the order got declined
                        console.log('Error occurred', err);
                        alert(me.opts.defaultErrorMessage);
                    }
                    me.unlockSubmitButton();
                });
            }
        },

    });
    window.StateManager.addPlugin('#billie-payment', 'BilliePayment', null, null);
})(jQuery, window);

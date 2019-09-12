$(function () {
    window.events.subscribe('create-prompt-message', function() {
        $('.external-invoice-number', this.parent.document).closest('table').next().find('input').parent().hide();
    });

    /**
     * Calls the confirm payment endpoint.
     * @param {string} url URL to endpoint
     * @param {number} order Order ID
     * @param {number} amount Amount that is paid
     */
    var callConfirmPaymentEndpoint = function (url, order, amount) {
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                'order_id': order,
                'amount': amount
            },
            success: function (response) {
                postMessageApi.createAlertMessage(
                    response.success ? _BILLIE_SNIPPETS_.errorCodes.success : _BILLIE_SNIPPETS_.errorCodes.error,
                    response.success ? _BILLIE_SNIPPETS_.confirm_payment.success : _BILLIE_SNIPPETS_.errorCodes[response.data]
                );
            }
        });
    };

    /**
     * Confirm (partial) payment by merchant.
     * @param {Event} event 
     */
    var onConfirmPayment = function (event) {
        var $target = $(this);
        event.preventDefault();

        postMessageApi.createPromptMessage(
            _BILLIE_SNIPPETS_.confirm_payment.title,
            _BILLIE_SNIPPETS_.confirm_payment.desc,
            function (data) {
                if (data.btn == 'ok') {
                    callConfirmPaymentEndpoint($target.data('action'), $target.data('order_id'), data.text);
                }
            }
        );
    };

    /**
     * Calls the cancel order action.
     * @param {jQuery} $target Button that was clicked
     */
    var callCancelOrderEndpoint = function ($target) {
        $.ajax({
            url: $target.data('action'),
            method: 'POST',
            data: {
                'order_id': $target.data('order_id')
            },
            success: function (response) {
                if (response.success) {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.success, _BILLIE_SNIPPETS_.cancel_order.success);
                    $target.closest('.wrapper').addClass('danger').find('.state').text(_BILLIE_SNIPPETS_.states.canceled);
                    $target.attr('disabled', 'disabled');
                }
                else {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.error, _BILLIE_SNIPPETS_.errorCodes[response.data]);
                }
            }
        });
    };

    /**
     * Cancel the order.
     * @param {Event} event
     */
    var onCancelOrder = function (event) {
        var $target = $(this);
        event.preventDefault();

        postMessageApi.createConfirmMessage(
            _BILLIE_SNIPPETS_.cancel_order.title,
            _BILLIE_SNIPPETS_.cancel_order.desc,
            function (data) {
                if ('yes' == data) {
                    callCancelOrderEndpoint($target);
                }
            }
        );
    };

    /**
    * Ship the order.
    * @param {Event} event
    */
    var onShipOrder = function (event) {
        var $target = $(this);
        event.preventDefault();

        shipOrderRequest($target, {'order_id': $target.data('order_id')});
    };

    /**
     * Shipping Ajax Requst
     * @param {jQuery<HTMLElement>} $target
     * @param {Object} data
     */
    var shipOrderRequest = function($target, data) {
        $.ajax({
            url: $target.data('action'),
            method: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.success, _BILLIE_SNIPPETS_.ship_order.success);
                    $target.closest('.wrapper').removeClass('info').addClass('success').find('.state').text(_BILLIE_SNIPPETS_.states.shipped)
                    $target.attr('disabled', 'disabled');
                }
                else if (response.data === 'MISSING_DOCUMENTS') {
                    missingShippingDocuments($target);
                }
                else {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.error, _BILLIE_SNIPPETS_.errorCodes[response.data]); 
                }
            }
        });
    };

    /**
     * Error Handling missing documents
     * @param {jQuery<HTMLElement>} $target
     */
    var missingShippingDocuments = function($target) {
        postMessageApi.createPromptMessage(
            _BILLIE_SNIPPETS_.errorCodes.error,
            _BILLIE_SNIPPETS_.ship_order.add_external_invoice +
            '<div style="margin-top: 8px;"><input type="text" class="x-form-field x-form-text external-invoice-number" name="external-invoice-number" style="width: 100%;" placeholder="' + _BILLIE_SNIPPETS_.ship_order.external_invoice_placeholder + '" autofocus required></div>' + 
            '<div style="margin-top: 8px;"><input type="text" class="x-form-field x-form-text external-invoice-url" name="external-invoice-url" style="width: 100%;" placeholder="' + _BILLIE_SNIPPETS_.ship_order.external_url_placeholder + '"></div>',
            function (data) {
                if (data.btn === 'ok') {
                    var invoice = $('.external-invoice-number', window.parent.document).val();
                    var url = $('.external-invoice-url', window.parent.document).val();

                    if (invoice.trim() == '') {
                        postMessageApi.createGrowlMessage(
                            _BILLIE_SNIPPETS_.errorCodes.error,
                            _BILLIE_SNIPPETS_.ship_order.missing_invoice_number,
                            true,
                            false
                        );
                        return;
                    }

                    shipOrderRequest($target, {
                        'order_id': $target.data('order_id'),
                        'invoice': invoice,
                        'url': url
                    });
                }
            },
        );
    }

    // Bind Events
    $('.confirm-payment').on('click', onConfirmPayment);
    $('.cancel-order').on('click', onCancelOrder);
    $('.ship-order').on('click', onShipOrder);
});
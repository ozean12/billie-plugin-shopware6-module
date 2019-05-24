$(function () {
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
        var $target = $(event.target);
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
        var $target = $(event.target);
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
        var $target = $(event.target);
        event.preventDefault();

        $.ajax({
            url: $target.data('action'),
            method: 'POST',
            data: {
                'order_id': $target.data('order_id')
            },
            success: function (response) {
                if (response.success) {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.success, _BILLIE_SNIPPETS_.ship_order.success);
                    $target.closest('.wrapper').removeClass('info').addClass('success').find('.state').text(_BILLIE_SNIPPETS_.states.shipped)
                    $target.attr('disabled', 'disabled');
                }
                else {
                    postMessageApi.createAlertMessage(_BILLIE_SNIPPETS_.errorCodes.error, _BILLIE_SNIPPETS_.errorCodes[response.data]); 
                }
            }
        });
    };

    // Bind Events
    $('.confirm-payment').on('click', onConfirmPayment);
    $('.cancel-order').on('click', onCancelOrder);
    $('.ship-order').on('click', onShipOrder);
});
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
                postMessageApi.createGrowlMessage(response.title, response.data);
            }
        });
    };

    /**
     * Confirm (partial) payment by merchant.
     * @param {Event} event 
     */
    var onConfirmPayment = function (event) {
        var target = $(event.target);

        postMessageApi.createPromptMessage(
            _BILLIE_SNIPPETS_.confirm_payment.title,
            _BILLIE_SNIPPETS_.confirm_payment.desc,
            function (data) {
                if (data.btn == 'ok') {
                    callConfirmPaymentEndpoint(target.data('action'), target.data('order_id'), data.text);
                }
            }
        );
    };

    /**
     * Calls the cancel order action.
     * @param {string} url URL to endpoint
     * @param {number} order Order ID
     */
    var callCancelOrderEndpoint = function (url, order) {
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                'order_id': order
            },
            success: function (response) {
                postMessageApi.createGrowlMessage(response.title, response.data);
            }
        });
    };

    /**
     * Cancel the order.
     * @param {Event} event
     */
    var onCancelOrder = function(event) {
        var target = $(event.target);

        postMessageApi.createConfirmMessage(
            _BILLIE_SNIPPETS_.cancel_order.title,
            _BILLIE_SNIPPETS_.cancel_order.desc,
            function (data) {
                if ('yes' == data) {
                    callCancelOrderEndpoint(target.data('action'), target.data('order_id'));
                }
            }
        );
    };

    // Bind Events
    $('.confirm-payment').on('click', onConfirmPayment);
    $('.cancel-order').on('click', onCancelOrder);
});
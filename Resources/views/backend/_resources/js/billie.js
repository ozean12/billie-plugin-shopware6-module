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
        event.preventDefault();

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
     * @param {HTMLElement} target Button that was clicked
     */
    var callCancelOrderEndpoint = function (target) {
        $.ajax({
            url: target.data('action'),
            method: 'POST',
            data: {
                'order_id': target.data('order_id')
            },
            success: function (response) {
                postMessageApi.createGrowlMessage(response.title, response.data);
                target.closest('.wrapper').addClass('danger').find('.state').text(_BILLIE_SNIPPETS_.states.canceled)
            }
        });
    };

    /**
     * Cancel the order.
     * @param {Event} event
     */
    var onCancelOrder = function(event) {
        var target = $(event.target);
        event.preventDefault();

        postMessageApi.createConfirmMessage(
            _BILLIE_SNIPPETS_.cancel_order.title,
            _BILLIE_SNIPPETS_.cancel_order.desc,
            function (data) {
                if ('yes' == data) {
                    callCancelOrderEndpoint(target);
                }
            }
        );
    };

    // Bind Events
    $('.confirm-payment').on('click', onConfirmPayment);
    $('.cancel-order').on('click', onCancelOrder);
});
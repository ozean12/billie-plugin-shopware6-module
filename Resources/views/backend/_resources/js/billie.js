$(function () {
    $('.confirm-payment').on('click', function (event) {
        var target = $(event.target);

        postMessageApi.createPromptMessage(_BILLIE_SNIPPETS_.confirm_payment.title, _BILLIE_SNIPPETS_.confirm_payment.desc, function(data) {
            if (data.btn == 'ok') {
                $.ajax({
                    url: target.data('action'),
                    method: 'POST',
                    data: {
                        'order_id': target.data('order_id'),
                        'amount': data.text
                    },
                    success: function (response) {
                        postMessageApi.createGrowlMessage(response.title, response.data);
                    }
                });
            }
        });
    });
});
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var cc = new woocommerce_card_connect_1.default($);
    var $form = $('form.checkout');
    function formSubmit(ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (0 === $('input.card-connect-token').size()) {
            var creditCard = $form.find('.wc-credit-card-form-card-number').val();
            if (!creditCard) {
                alert('Please enter a credit card number');
                return false;
            }
            cc.getToken(creditCard, function (token, error) {
                if (error)
                    alert(error);
                $('<input />')
                    .attr('name', 'card_connect_token')
                    .attr('type', 'hidden')
                    .addClass('card-connect-token')
                    .val(token)
                    .appendTo($form);
                $form.submit();
            });
            return false;
        }
        return true;
    }
    $form.on('checkout_place_order_card_connect', formSubmit);
    $('body').on('checkout_error', function () { return $('.card-connect-token').remove(); });
    $('form.checkout').on('change', '.wc-credit-card-form-card-number', function () {
        $('.card-connect-token').remove();
    });
});

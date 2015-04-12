/// <reference path="typings/tsd.d.ts"/>
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var cc = new woocommerce_card_connect_1.default($, Boolean(wooCardConnect.isLive));
    var $form = $('form.checkout');
    function formSubmit(ev) {
        if (0 === $('input.card-connect-token').size()) {
            $form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
            var creditCard = $form.find('.wc-credit-card-form-card-number').val();
            if (!creditCard) {
                printWooError('Please enter a credit card number');
                return false;
            }
            else if (!checkCardType(creditCard)) {
                printWooError('Credit card type not accepted');
                return false;
            }
            cc.getToken(creditCard, function (token, error) {
                if (error) {
                    printWooError(error);
                    return false;
                }
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
    function checkCardType(cardNumber) {
        var cardType = $.payment.cardType(cardNumber);
        for (var i = 0; i < wooCardConnect.allowedCards.length; i++) {
            if (wooCardConnect.allowedCards[i] === cardType)
                return true;
        }
        return false;
    }
    function printWooError(error) {
        $('.woocommerce-error', $form).remove();
        var errorText;
        if (error.constructor === Array) {
            errorText = Array(error).reduce(function (prev, curr) { return prev += "<li>" + curr + "</li>"; });
        }
        else {
            errorText = error;
        }
        $form.prepend("<ul class=\"woocommerce-error\">" + errorText + "</ul>");
        $form.unblock();
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    }
    $form.on('checkout_place_order_card_connect', formSubmit);
    $('body').on('checkout_error', function () { return $('.card-connect-token').remove(); });
    $('form.checkout').on('change', '.wc-credit-card-form-card-number', function () {
        $('.card-connect-token').remove();
    });
});

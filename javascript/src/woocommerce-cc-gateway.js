/// <reference path="./typings/tsd.d.ts"/>
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var isLive = Boolean(wooCardConnect.isLive);
    var cc = new woocommerce_card_connect_1.default($, wooCardConnect.apiEndpoint);
    var $form = $('form.checkout, form#order_review');
    var $errors;
    if (!isLive) {
        $(document).ajaxComplete(function (event, request, settings) {
            $form.find('#card_connect-cc-form input').change().keyup();
        });
    }
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
        if (!$errors)
            $errors = $('.js-card-connect-errors', $form);
        var errorText;
        if (error.constructor === Array) {
            errorText = Array(error).reduce(function (prev, curr) { return prev += "<li>" + curr + "</li>"; });
        }
        else {
            errorText = "<li>" + error + "</li>";
        }
        $errors.html("<ul class=\"woocommerce-error\">" + errorText + "</ul>");
        $form.unblock();
    }
    $form.on('checkout_place_order_card_connect', formSubmit);
    $('form#order_review').on('submit', formSubmit);
    $('body').on('checkout_error', function () { return $('.card-connect-token').remove(); });
    $form.on('change', '.wc-credit-card-form-card-number', function () {
        $('.card-connect-token').remove();
    });
});

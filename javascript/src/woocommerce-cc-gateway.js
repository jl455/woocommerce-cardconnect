/// <reference path="./typings/tsd.d.ts"/>
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var isLive = Boolean(wooCardConnect.isLive);
    var cc = new woocommerce_card_connect_1.default($, wooCardConnect.apiEndpoint);
    var $form = $('form.checkout, form#order_review');
    var $errors;
    if (!isLive) {
        setTimeout(function () {
            $form.find('#card_connect-cc-form input').change().keyup();
        }, 1000);
    }
    function getToken() {
        if (checkAllowSubmit())
            return false;
        var $ccInput = $form.find('.wc-credit-card-form-card-number');
        var creditCard = $ccInput.val();
        $form.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
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
            $ccInput.val($.map(creditCard.split(''), function (char, index) {
                if (creditCard.length - (index + 1) > 4) {
                    return char !== ' ' ? '\u2022' : ' ';
                }
                else {
                    return char;
                }
            }).join(''));
        });
        $form.unblock();
        return true;
    }
    function checkAllowSubmit() {
        return 0 !== $('input.card-connect-token', $form).size();
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
    $form.on('blur', '#card_connect-card-number', function () {
        if ($errors)
            $errors.html('');
        return getToken();
    });
    $form.on('checkout_place_order_card_connect', function () { return checkAllowSubmit(); });
    $('form#order_review').on('submit', function () { return checkAllowSubmit(); });
    $('document.body').on('checkout_error', function () {
        if ($errors)
            $errors.html('');
        $('.card-connect-token').remove();
    });
    $form.on('keyup change', '#card_connect-card-number', function () {
        $('.card-connect-token').remove();
    });
});

(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
var WoocommereCardConnect = (function () {
    function WoocommereCardConnect(jQuery, isLive) {
        var _this = this;
        if (isLive === void 0) { isLive = true; }
        this.getToken = function (number, callback) {
            if (!_this.validateCard(number))
                return callback(null, 'Invalid Credit Card Number');
            _this.$.get(_this.baseUrl + "&data=" + _this.cardNumber)
                .done(function (data) { return _this.processRequest(data, callback); })
                .fail(function (data) { return _this.failedRequest(data, callback); });
        };
        this.validateCard = function (number) {
            _this.cardNumber = number;
            // @TODO : Additional card number validation here maybe?
            return _this.cardNumber.length > 0;
        };
        this.processRequest = function (data, callback) {
            var processToken = function (response) {
                var action = response.action, data = response.data;
                if (action === 'CE')
                    callback(data, null);
                else
                    callback(null, data);
            };
            eval(data);
        };
        this.failedRequest = function (data, callback) {
            return callback(null, 'Failed to connect to server');
        };
        this.$ = jQuery;
        this.baseUrl = "https://fts.cardconnect.com:" + (isLive ? '8443' : '6443') + "/cardsecure/cs?action=CE&type=json";
    }
    return WoocommereCardConnect;
})();
exports.default = WoocommereCardConnect;

},{}],2:[function(require,module,exports){
/// <reference path="typings/tsd.d.ts"/>
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var isLive = Boolean(wooCardConnect.isLive);
    var cc = new woocommerce_card_connect_1.default($, isLive);
    var $form = $('form.checkout');
    // Simulate some text entry to get jQuery Payment to reformat numbers
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
        $('.woocommerce-error', $form).remove();
        var errorText; // This should only be a string, TS doesn't like the reduce output though
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

},{"./woocommerce-card-connect":1}]},{},[2]);

(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
var WoocommereCardConnect = (function () {
    function WoocommereCardConnect(jQuery, csApiEndpoint) {
        var _this = this;
        this.getToken = function (number, callback) {
            if (!_this.validateCard(number))
                return callback(null, 'Invalid Credit Card Number');
            _this.$.get(_this.baseUrl + "&data=" + _this.cardNumber)
                .done(function (data) { return _this.processRequest(data, callback); })
                .fail(function (data) { return _this.failedRequest(data, callback); });
        };
        this.validateCard = function (number) {
            _this.cardNumber = number;
            if (_this.$.payment) {
                return _this.$.payment.validateCardNumber(_this.cardNumber);
            }
            else {
                return _this.cardNumber.length > 0;
            }
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
        this.baseUrl = csApiEndpoint + '?action=CE&type=json';
    }
    return WoocommereCardConnect;
})();
exports.default = WoocommereCardConnect;

},{}],2:[function(require,module,exports){
/// <reference path="./typings/tsd.d.ts"/>
var card_connect_tokenizer_1 = require("./card-connect-tokenizer");
jQuery(function ($) {
    var isLive = Boolean(wooCardConnect.isLive);
    var cc = new card_connect_tokenizer_1.default($, wooCardConnect.apiEndpoint);
    var $form = $('form.checkout, form#order_review');
    var $errors;
    // Simulate some text entry to get jQuery Payment to reformat numbers
    if (!isLive) {
        $('body').on('updated_checkout', function () {
            getToken();
        });
    }
    function getToken() {
        if (checkAllowSubmit())
            return false;
        var $ccInput = $form.find('#card_connect-card-number');
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
            // Append token as hidden input
            $('<input />')
                .attr('name', 'card_connect_token')
                .attr('type', 'hidden')
                .addClass('card-connect-token')
                .val(token)
                .appendTo($form);
            // Mask user entered CC number
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
        var errorText; // This should only be a string, TS doesn't like the reduce output though
        if (error.constructor === Array) {
            errorText = Array(error).reduce(function (prev, curr) { return prev += "<li>" + curr + "</li>"; });
        }
        else {
            errorText = "<li>" + error + "</li>";
        }
        $errors.html("<ul class=\"woocommerce-error\">" + errorText + "</ul>");
        $form.unblock();
    }
    // Get token when focus of CC field is lost
    $form.on('blur', '#card_connect-card-number', function () {
        if ($errors)
            $errors.html('');
        return getToken();
    });
    // Bind Submit Listeners
    $form.on('checkout_place_order_card_connect', function () { return checkAllowSubmit(); });
    $('form#order_review').on('submit', function () { return checkAllowSubmit(); });
    // Remove token on checkout err
    $('document.body').on('checkout_error', function () {
        if ($errors)
            $errors.html('');
        $('.card-connect-token').remove();
    });
    // Clear token if form is changed
    $form.on('keyup change', '#card_connect-card-number', function () {
        $('.card-connect-token').remove();
    });
});

},{"./card-connect-tokenizer":1}]},{},[2]);

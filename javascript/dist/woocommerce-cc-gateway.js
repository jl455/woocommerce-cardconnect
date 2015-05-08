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
var SavedCards = (function () {
    function SavedCards() {
    }
    SavedCards.init = function () {
        var SAVE_CARD_TOGGLE = '#card_connect-save-card';
        var CARD_NICKNAME = '#card_connect-new-card-alias';
        var WOOCOMMERCE_CREATE_ACCOUNT = '#createaccount';
        var SAVED_CARD = '#card_connect-cards';
        var CARDHOLDER_NAME = '#card_connect-card-name';
        var CARD_NUMBER = '#card_connect-card-number';
        var EXPIRY = '#card_connect-card-expiry';
        var CVV = '#card_connect-card-cvc';
        var CREATE_ACCOUNT_DISABLED_MESSAGE = 'You must check "Create an account" above in order to save your card.';
        var $ = jQuery;
        var $cardToggle = $(SAVE_CARD_TOGGLE);
        var $cardNickname = $(CARD_NICKNAME);
        var $createAccount = $(WOOCOMMERCE_CREATE_ACCOUNT);
        var $savedCard = $(SAVED_CARD);
        var $paymentFields = $([
            SAVE_CARD_TOGGLE,
            CARD_NICKNAME,
            CARDHOLDER_NAME,
            CARD_NUMBER,
            EXPIRY
        ].join(','));
        var userSignedIn = wooCardConnect.userSignedIn;
        if ($createAccount.length === 0 && !userSignedIn)
            return;
        $cardToggle.on('change', controlNicknameField);
        $createAccount.on('change', controlSaveCardToggle);
        $savedCard.on('change', controlCardInputFields);
        function controlNicknameField() {
            var isSet = $(this).is(':checked');
            $cardNickname.prop('disabled', !isSet);
            if (!isSet)
                $cardNickname.val('');
        }
        function controlSaveCardToggle() {
            if (userSignedIn)
                return;
            var isSet = $(this).is(':checked');
            $cardToggle.prop('disabled', !isSet);
            if (!isSet)
                $cardToggle.prop('checked', false);
            setTooltips(isSet);
        }
        controlSaveCardToggle();
        function controlCardInputFields() {
            var value = $(this).find(':selected').val();
            $paymentFields.prop('disabled', !!value);
            if (value) {
                $paymentFields.val('');
            }
        }
        function setTooltips(isEnabled) {
            var titleText = isEnabled ? '' : CREATE_ACCOUNT_DISABLED_MESSAGE;
            $cardToggle.attr('title', titleText);
            $cardNickname.attr('title', titleText);
        }
    };
    SavedCards.submitHandler = function () {
    };
    return SavedCards;
})();
exports.default = SavedCards;

},{}],3:[function(require,module,exports){
/// <reference path="./typings/tsd.d.ts"/>
var card_connect_tokenizer_1 = require("./card-connect-tokenizer");
var saved_cards_1 = require('./saved-cards');
var SAVED_CARDS_SELECT = '#card_connect-cards';
jQuery(function ($) {
    var isLive = Boolean(wooCardConnect.isLive);
    var cc = new card_connect_tokenizer_1.default($, wooCardConnect.apiEndpoint);
    var $form = $('form.checkout, form#order_review');
    var $errors;
    $('body').on('updated_checkout', function () {
        if (wooCardConnect.profilesEnabled)
            saved_cards_1.default.init();
    });
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
        if (creditCard.indexOf('\u2022') > -1)
            return;
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
        return 0 !== $('input.card-connect-token', $form).size() || $(SAVED_CARDS_SELECT).val();
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
    $form.on('keyup change', "#card_connect-card-number, " + SAVED_CARDS_SELECT, function () {
        $('.card-connect-token').remove();
    });
});

},{"./card-connect-tokenizer":1,"./saved-cards":2}]},{},[3]);

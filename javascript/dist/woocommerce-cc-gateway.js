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
        var $cardToggleLabelText = $('#card_connect-save-card-label-text');
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
        if ($createAccount.length === 0 && !userSignedIn) {
            // there is no 'create account' checkbox because either:
            // A) WooCommerce > Settings > Checkout > Enable Guest Checkout (is UNCHECKED)
            // or
            // B) There is a Subscription (as opposed to a regular product) in the cart and
            //    Subscriptions always require a WP account to be created.  Thus no 'create account' checkbox.
            //
            // In this case, it is REQUIRED that the user create a WP account and this unnecessary to display the
            // 'create account' checkbox.
            //console.log('no \'create account\' checkbox');
            // ensure that the 'card nickname' field enable/disable corresponds to the 'save this card' checkbox setting
            $cardToggle.on('change', controlNicknameField);
            // enable the 'save this card' checkbox
            $cardToggle.prop('disabled', false);
        }
        else {
            //console.log('\'create account\' checkbox');
            $cardToggle.on('change', controlNicknameField);
            $createAccount.on('change', controlSaveCardToggle);
            $savedCard.on('change', controlCardInputFields);
        }
        function controlNicknameField() {
            //console.log('controlNicknameField()');
            // is the 'save this card' checkbox checked or not?
            var isSet = $(this).is(':checked');
            $cardNickname.prop('disabled', !isSet);
            if (!isSet) {
                $cardNickname.val('');
            }
        }
        function controlSaveCardToggle() {
            //console.log('controlSaveCardToggle()');
            if (userSignedIn) {
                return;
            }
            // is the 'create an account' checkbox checked or not?
            var isSet;
            if ($createAccount.length === 0) {
                // there is no $createAccount checkbox so we know that the user will be forced to create an account anyway
                isSet = true;
            }
            else {
                isSet = $(this).is(':checked');
            }
            $cardToggle.prop('disabled', !isSet);
            if (!isSet) {
                $cardToggle.prop('checked', false);
            }
            setTooltips(isSet);
        }
        controlSaveCardToggle();
        function controlCardInputFields() {
            //console.log('controlCardInputFields()');
            // which of the 'saved cards' dropdown items is selected?
            var value = $(this).find(':selected').val();
            $paymentFields.prop('disabled', !!value);
            if (value) {
                $paymentFields.val('');
            }
        }
        function setTooltips(isEnabled) {
            //console.log('setTooltips(isEnabled= ' + isEnabled + ' )');
            // isEnabled corresponds to whether 'create an account' is checked or not.
            var labelText;
            if (isEnabled) {
                labelText = 'Save this card';
            }
            else {
                labelText = 'Save this card (' + CREATE_ACCOUNT_DISABLED_MESSAGE + ')';
            }
            $cardToggleLabelText.text(labelText);
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
    // !! 'updated_checkout' is not fired for the 'payment method change' form aka 'form#order_review'
    $('body').on('updated_checkout', function () {
        //console.log("!!! caught updated_checkout");
        if (wooCardConnect.profilesEnabled)
            saved_cards_1.default.init();
    });
    //'updated_checkout' (above) was not fired for the 'payment method change' form aka 'form#order_review'
    // so this was added.
    $('form#order_review').ready(function () {
        //console.log('ready');
        if (wooCardConnect.profilesEnabled) {
            saved_cards_1.default.init();
        }
    });
    // Simulate some text entry to get jQuery Payment to reformat numbers
    if (!isLive) {
        $('body').on('updated_checkout', function () {
            getToken();
        });
    }
    function getToken() {
        // why is/was this here?
        //if (checkAllowSubmit()) {
        //    return false;
        //}
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
        // if we have a token OR a 'saved card' is selected, return FALSE
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

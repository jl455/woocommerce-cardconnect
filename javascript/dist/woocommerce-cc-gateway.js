(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({"./javascript/src/woocommerce-cc-gateway.js":[function(require,module,exports){
var woocommerce_card_connect_1 = require("./woocommerce-card-connect");
jQuery(function ($) {
    var cc = new woocommerce_card_connect_1.default($, Boolean(wooCardConnect.isLive));
    var $form = $('form.checkout');
    function formSubmit(ev) {
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

},{"./woocommerce-card-connect":"/Users/eran/Repositories/VVV/www/cardconnect/htdocs/wp-content/plugins/cardconnect-payment-gateway/javascript/src/woocommerce-card-connect.js"}],"/Users/eran/Repositories/VVV/www/cardconnect/htdocs/wp-content/plugins/cardconnect-payment-gateway/javascript/src/woocommerce-card-connect.js":[function(require,module,exports){
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

},{}]},{},["./javascript/src/woocommerce-cc-gateway.js"]);

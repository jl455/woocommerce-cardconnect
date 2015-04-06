var WoocommereCardConnect = (function () {
    function WoocommereCardConnect(jQuery) {
        var _this = this;
        this.baseUrl = 'https://fts.prinpay.com/cardsecure/cs?action=CE&type=json';
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
    }
    return WoocommereCardConnect;
})();
exports.default = WoocommereCardConnect;

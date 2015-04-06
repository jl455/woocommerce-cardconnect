var woocommerce_card_connect_1 = require("../src/woocommerce-card-connect");
var chai = require("chai");
var expect = chai.expect;
var $ = require('jquery');
var cc = new woocommerce_card_connect_1.default($);
describe('CardConnect Token Generator', function () {
    it("should generate token with valid credit card number", function (done) {
        cc.getToken('4242424242424242', function (token, error) {
            expect(error).to.be.null;
            expect(token).to.be.a('string');
            expect(token).to.equal('9428873934894242');
            done();
        });
    });
    it("should generate error with non-numerical entry", function (done) {
        cc.getToken('frisbee', function (token, error) {
            expect(token).to.be.null;
            expect(error).to.equal('0008::Data not decimal digits');
            done();
        });
    });
    it("should generate error with oversized entry", function (done) {
        cc.getToken('42424242424242424242424242424242', function (token, error) {
            expect(token).to.be.null;
            expect(error).to.equal('0013::Data too long');
            done();
        });
    });
    it("should generate error when no entry made", function (done) {
        cc.getToken('', function (token, error) {
            expect(token).to.be.null;
            expect(error).to.equal('Invalid Credit Card Number');
            done();
        });
    });
});

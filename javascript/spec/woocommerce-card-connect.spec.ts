declare let require : any;
declare let describe : any;
declare let it : any;
import WoocommereCardConnect from "../src/woocommerce-card-connect";

let chai = require("chai");
let expect = chai.expect;
let $ = require('jquery');
let cc = new WoocommereCardConnect($);

describe('CardConnect Token Generator', () => {

  it("should generate token with valid credit card number", (done) => {
    cc.getToken('4242424242424242', (token, error) => {
      expect(error).to.be.null;
      expect(token).to.be.a('string');
      expect(token).to.equal('9428873934894242');
      done();
    });
  });

  it("should generate error with non-numerical entry", (done) => {
    cc.getToken('frisbee', (token, error) => {
      expect(token).to.be.null;
      expect(error).to.equal('0008::Data not decimal digits');
      done();
    });
  });

  it("should generate error with oversized entry", (done) => {
    cc.getToken('42424242424242424242424242424242', (token, error) => {
      expect(token).to.be.null;
      expect(error).to.equal('0013::Data too long');
      done();
    });
  });

  it("should generate error when no entry made", (done) => {
    cc.getToken('', (token, error) => {
      expect(token).to.be.null;
      expect(error).to.equal('Invalid Credit Card Number');
      done();
    });
  });

});

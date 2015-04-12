/// <reference path="typings/tsd.d.ts"/>
declare let jQuery : any;
declare let wooCardConnect : any;
import WoocommereCardConnect from "./woocommerce-card-connect";

jQuery($ => {

  let cc = new WoocommereCardConnect($, Boolean(wooCardConnect.isLive));
  let $form = $('form.checkout');

  function formSubmit(ev) : boolean {

    if ( 0 === $( 'input.card-connect-token' ).size()){

      $form.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });

      let creditCard = $form.find('.wc-credit-card-form-card-number').val();
      if(!creditCard){
        printWooError('Please enter a credit card number');
        return false;
      }

      cc.getToken(creditCard, function(token, error){
        if(error){
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

  function printWooError(error : string | string[]) : void {
    $('.woocommerce-error', $form).remove();

    let errorText : string | string[]; // This should only be a string, TS doesn't like the reduce output though
    if(error.constructor === Array){
      errorText = Array(error).reduce((prev, curr) => prev += `<li>${curr}</li>`);
    }else{
      errorText = error;
    }

    $form.prepend(`<ul class="woocommerce-error">${errorText}</ul>`);

    $form.unblock();
    $('html, body').animate({ scrollTop: 0 }, 'slow');
  }

  $form.on('checkout_place_order_card_connect', formSubmit);

  $('body').on('checkout_error', () => $('.card-connect-token').remove());

  $('form.checkout').on('change', '.wc-credit-card-form-card-number', () => {
    $('.card-connect-token').remove();
  });

});

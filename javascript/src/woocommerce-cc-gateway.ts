/// <reference path="./typings/tsd.d.ts"/>
declare let jQuery : any;
declare let wooCardConnect : any;
declare let window : any;
import WoocommereCardConnect from "./woocommerce-card-connect";

jQuery($ => {

  let isLive : boolean = Boolean(wooCardConnect.isLive);
  let cc = new WoocommereCardConnect($, wooCardConnect.apiEndpoint);
  let $form = $('form.checkout, form#order_review');
  let $errors;

  // Simulate some text entry to get jQuery Payment to reformat numbers
  if(!isLive){
    // Arbitrary set timout to delay for ajax events
    // no biggie if this fails, it's just for looks in sandbox mode..
    setTimeout(() => {
      $form.find('#card_connect-cc-form input').change().keyup();
    }, 1000);
  }

  function getToken() : boolean {

    if(checkAllowSubmit()) return false;

    let $ccInput = $form.find('#card_connect-card-number');
    let creditCard = $ccInput.val();
    $form.block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
    if(!creditCard){
      printWooError('Please enter a credit card number');
      return false;
    }else if(!checkCardType(creditCard)){
      printWooError('Credit card type not accepted');
      return false;
    }
    cc.getToken(creditCard, function(token, error){
      if(error){
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
      $ccInput.val($.map(creditCard.split(''), (char, index) => {
        if(creditCard.length - (index + 1) > 4 ){
          return char !== ' ' ? '\u2022' : ' ';
        }else{
          return char;
        }
      }).join(''));

    });
    $form.unblock();
    return true;
  }

  function checkAllowSubmit() : boolean {
    return 0 !== $('input.card-connect-token', $form).size();
  }

  function checkCardType(cardNumber : string) : boolean {
    let cardType = $.payment.cardType(cardNumber);
    for(let i = 0; i < wooCardConnect.allowedCards.length; i++) {
      if(wooCardConnect.allowedCards[i] === cardType) return true;
    }
    return false;
  }

  function printWooError(error : string | string[]) : void {

    if(!$errors) $errors = $('.js-card-connect-errors', $form);

    let errorText : string | string[]; // This should only be a string, TS doesn't like the reduce output though
    if(error.constructor === Array){
      errorText = Array(error).reduce((prev, curr) => prev += `<li>${curr}</li>`);
    }else{
      errorText = `<li>${error}</li>`;
    }

    $errors.html(`<ul class="woocommerce-error">${errorText}</ul>`);
    $form.unblock();
  }

  // Get token when focus of CC field is lost
  $form.on('blur', '#card_connect-card-number', () => {
    if($errors) $errors.html('');
    return getToken();
  });

  // Bind Submit Listeners
  $form.on('checkout_place_order_card_connect', () => checkAllowSubmit());
  $('form#order_review').on('submit', () => checkAllowSubmit());

  // Remove token on checkout err
  $('document.body').on('checkout_error', () => {
    if($errors) $errors.html('');
    $('.card-connect-token').remove();
  });

  // Clear token if form is changed
  $form.on('keyup change', '#card_connect-card-number', () => {
    $('.card-connect-token').remove();
  });

});

declare let jQuery : any;
import WoocommereCardConnect from "./woocommerce-card-connect";

jQuery($ => {

  let cc = new WoocommereCardConnect($);
  let $form = $('form.checkout');

  function formSubmit(ev) : boolean {

    ev.preventDefault();
    ev.stopPropagation();

    if ( 0 === $( 'input.card-connect-token' ).size()){

      let creditCard = $form.find('.wc-credit-card-form-card-number').val();
      if(!creditCard){
        alert('Please enter a credit card number'); // @TODO : Handle error
        return false;
      }

      cc.getToken(creditCard, function(token, error){
        if(error) alert(error); // @TODO : Handle error
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

  $('body').on('checkout_error', () => $('.card-connect-token').remove());

  $('form.checkout').on('change', '.wc-credit-card-form-card-number', () => {
    $('.card-connect-token').remove();
  });

});

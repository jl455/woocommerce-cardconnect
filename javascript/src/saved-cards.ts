declare let jQuery : any;
declare let wooCardConnect : any;

export default class SavedCards {

	static init() : void {

		const SAVE_CARD_TOGGLE = '#card_connect-save-card';
		const CARD_NICKNAME = '#card_connect-new-card-alias';
		const WOOCOMMERCE_CREATE_ACCOUNT = '#createaccount';
		const SAVED_CARD = '#card_connect-cards';
		const CARDHOLDER_NAME = '#card_connect-card-name';
		const CARD_NUMBER = '#card_connect-card-number';
		const EXPIRY = '#card_connect-card-expiry';
		const CVV = '#card_connect-card-cvc';
		const CREATE_ACCOUNT_DISABLED_MESSAGE = 'You must check "Create an account" above in order to save your card.';
		const $ = jQuery;

		let $cardToggle = $(SAVE_CARD_TOGGLE);
		let $cardToggleLabelText = $('#card_connect-save-card-label-text');
		let $cardNickname = $(CARD_NICKNAME);
		let $createAccount = $(WOOCOMMERCE_CREATE_ACCOUNT);
		let $savedCard = $(SAVED_CARD);
		let $paymentFields = $([
			SAVE_CARD_TOGGLE, // While not technically payment fields,
			CARD_NICKNAME,    // these should be disabled with the rest
			CARDHOLDER_NAME,
			CARD_NUMBER,
			EXPIRY
		].join(','));
		let userSignedIn = wooCardConnect.userSignedIn;

		if($createAccount.length === 0 && !userSignedIn) {
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

		} else {
			//console.log('\'create account\' checkbox');

			$cardToggle.on('change', controlNicknameField);
			$createAccount.on('change', controlSaveCardToggle);
			$savedCard.on('change', controlCardInputFields);
		}




		function controlNicknameField(){
			//console.log('controlNicknameField()');

			// is the 'save this card' checkbox checked or not?
			let isSet = $(this).is(':checked');

			$cardNickname.prop('disabled', !isSet);
			if(!isSet) {
				$cardNickname.val('');
			}
		}

		function controlSaveCardToggle(){
			//console.log('controlSaveCardToggle()');

			if(userSignedIn) {
				return;
			}

			// is the 'create an account' checkbox checked or not?
			var isSet;
			if ( $createAccount.length === 0 ) {
				// there is no $createAccount checkbox so we know that the user will be forced to create an account anyway
				isSet = true;
			} else {
				isSet = $(this).is(':checked');
			}

			$cardToggle.prop('disabled', !isSet);
			if(!isSet) {
				$cardToggle.prop('checked', false);
			}
			setTooltips(isSet);
		}
		controlSaveCardToggle();

		function controlCardInputFields(){
			//console.log('controlCardInputFields()');

			// which of the 'saved cards' dropdown items is selected?
			let value = $(this).find(':selected').val();

			$paymentFields.prop('disabled', !!value)
			if(value){
				$paymentFields.val('');
			}
		}

		function setTooltips(isEnabled){
			//console.log('setTooltips(isEnabled= ' + isEnabled + ' )');

			// isEnabled corresponds to whether 'create an account' is checked or not.

			var labelText;
			if ( isEnabled ) {
				labelText = 'Save this card';
			} else {
				labelText = 'Save this card (' + CREATE_ACCOUNT_DISABLED_MESSAGE + ')';
			}

			$cardToggleLabelText.text(labelText);
		}

	}

	static submitHandler() : void {

	}
}

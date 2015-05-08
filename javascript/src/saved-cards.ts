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
		let $cardNickname = $(CARD_NICKNAME);
		let $createAccount = $(WOOCOMMERCE_CREATE_ACCOUNT);
		let $savedCard = $(SAVED_CARD);
		let $paymentFields = $([
			SAVE_CARD_TOGGLE, // While not technically a payment field, it should be disabled with the rest
			CARDHOLDER_NAME,
			CARD_NUMBER,
			EXPIRY
		].join(','));
		let userSignedIn = wooCardConnect.userSignedIn;

		if($createAccount.length === 0 && !userSignedIn) return;

		$cardToggle.on('change', controlNicknameField);
		$createAccount.on('change', controlSaveCardToggle);
		$savedCard.on('change', controlCardInputFields);

		function controlNicknameField(){
			let isSet = $(this).is(':checked');
			$cardNickname.prop('disabled', !isSet);
			if(!isSet) $cardNickname.val('');
		}

		function controlSaveCardToggle(){
			if(userSignedIn) return;
			let isSet = $(this).is(':checked');
			$cardToggle.prop('disabled', !isSet);
			if(!isSet) $cardToggle.prop('checked', false);
			setTooltips(isSet);
		}
		controlSaveCardToggle();

		function controlCardInputFields(){
			let value = $(this).find(':selected').val();
			$paymentFields.prop('disabled', !!value)
			if(value){
				$paymentFields.val('');
			}
		}

		function setTooltips(isEnabled){
			let titleText = isEnabled ? '' : CREATE_ACCOUNT_DISABLED_MESSAGE;
			$cardToggle.attr('title', titleText);
			$cardNickname.attr('title', titleText);
		}

	}

	static submitHandler() : void {

	}
}
